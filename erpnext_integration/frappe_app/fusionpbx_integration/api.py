# Copyright (c) 2026, VOIPEGYPT and contributors
# License: MPL-1.1
#
# Whitelisted API endpoints exposed at /api/method/fusionpbx_integration.api.<name>
#
#   incoming_call    (POST) - FusionPBX notifies of an inbound call -> screen-pop
#   lookup_contact   (GET)  - FusionPBX asks for the caller-ID name of a number
#   export_contacts  (GET)  - FusionPBX contact-sync worker pulls contacts
#   click_to_dial    (POST) - ERPNext desk originates a call through FusionPBX
#   ingest_call_log  (POST) - alternative CDR ingest (creates a Call Log doc)

import re
import requests

import frappe
from frappe import _


# ---------------------------------------------------------------------------
# helpers
# ---------------------------------------------------------------------------

def _settings():
    return frappe.get_single("FusionPBX Settings")


def _require_authenticated():
    """These endpoints are reachable as guest so FusionPBX can call them with an
    API key/secret token. Frappe resolves the token to a real user before the
    method runs; a genuinely anonymous caller stays 'Guest' and is rejected."""
    if frappe.session.user == "Guest":
        frappe.throw(_("Authentication required"), frappe.AuthenticationError)


def _normalize(number, match_digits=9):
    """Digits-only, keep trailing significant digits for comparison."""
    digits = re.sub(r"\D+", "", number or "")
    if match_digits and len(digits) > match_digits:
        digits = digits[-match_digits:]
    return digits


# phone-bearing doctypes we search, in priority order.
# (doctype, [candidate phone fieldnames])
_CONTACT_SOURCES = [
    ("Contact", ["phone", "mobile_no"]),
    ("Lead", ["phone", "mobile_no", "whatsapp_no"]),
    ("Customer", ["mobile_no"]),
    ("Supplier", ["mobile_no"]),
]


def _find_contact(number):
    """Return the best-matching contact for a phone number, or None."""
    settings = _settings()
    match_digits = int(settings.match_digits or 9)
    key = _normalize(number, match_digits)
    if not key:
        return None

    # Contacts store numbers in the child table `Contact Phone`; also check the
    # flat fields. We compare on the trailing-digits key using a LIKE on the
    # right-most digits, then verify in Python.
    like = "%" + key

    # 1) Contact + its phone child table
    rows = frappe.db.sql(
        """
        select parent as name
        from `tabContact Phone`
        where replace(replace(replace(replace(phone,'-',''),' ',''),'(',''),')','') like %s
        limit 5
        """,
        (like,),
        as_dict=True,
    )
    for r in rows:
        if _match_contact_field("Contact", r["name"], key, match_digits):
            full = frappe.db.get_value(
                "Contact", r["name"], ["name", "first_name", "last_name", "company_name"], as_dict=True
            )
            display = " ".join(filter(None, [full.get("first_name"), full.get("last_name")])) or full.get("company_name") or full["name"]
            return {"doctype": "Contact", "docname": full["name"], "name": display}

    # 2) flat phone/mobile fields on the other doctypes
    for doctype, fields in _CONTACT_SOURCES:
        for field in fields:
            if not frappe.db.has_column(doctype, field):
                continue
            hit = frappe.db.sql(
                """
                select name, {field} as ph
                from `tab{doctype}`
                where replace(replace(replace(replace({field},'-',''),' ',''),'(',''),')','') like %s
                limit 5
                """.format(field=field, doctype=doctype),
                (like,),
                as_dict=True,
            )
            for row in hit:
                if _normalize(row["ph"], match_digits) == key:
                    display = _display_name(doctype, row["name"])
                    return {"doctype": doctype, "docname": row["name"], "name": display}
    return None


def _match_contact_field(doctype, name, key, match_digits):
    phones = frappe.get_all(
        "Contact Phone", filters={"parent": name, "parenttype": doctype}, fields=["phone"]
    )
    return any(_normalize(p["phone"], match_digits) == key for p in phones)


def _display_name(doctype, name):
    if doctype == "Lead":
        v = frappe.db.get_value("Lead", name, ["lead_name", "company_name"], as_dict=True)
        return (v and (v.get("lead_name") or v.get("company_name"))) or name
    if doctype in ("Customer", "Supplier"):
        return name
    return name


def _link_call_log(call_log_name, contact):
    """Attach a Dynamic Link row on the Call Log pointing to the matched doc."""
    if not contact:
        return
    try:
        doc = frappe.get_doc("Call Log", call_log_name)
        doc.append("links", {"link_doctype": contact["doctype"], "link_name": contact["docname"]})
        doc.save(ignore_permissions=True)
    except Exception:
        frappe.log_error(frappe.get_traceback(), "fusionpbx_integration: link_call_log")


# ---------------------------------------------------------------------------
# inbound: FusionPBX -> ERPNext
# ---------------------------------------------------------------------------

@frappe.whitelist(allow_guest=True, methods=["POST"])
def incoming_call(**kwargs):
    """Raise a realtime screen-pop event for the target agent."""
    _require_authenticated()
    settings = _settings()
    if not settings.enabled or not settings.screen_pop_enabled:
        return {"ok": False, "reason": "disabled"}

    data = frappe.local.form_dict
    number = data.get("from") or kwargs.get("from")
    agent = data.get("agent") or kwargs.get("agent")
    call_uuid = data.get("call_uuid") or kwargs.get("call_uuid")

    contact = _find_contact(number)

    route = None
    if contact:
        tmpl = settings.screen_pop_route or "/app/{doctype}/{docname}"
        route = tmpl.format(
            doctype=frappe.scrub(contact["doctype"]).replace("_", "-"),
            docname=contact["docname"],
        )

    payload = {
        "event": "fusionpbx_incoming_call",
        "from": number,
        "call_uuid": call_uuid,
        "contact": contact,
        "route": route,
    }

    # Push to the specific agent's realtime room if we can map the extension to a
    # user; otherwise broadcast to all desk users.
    user = _user_for_extension(agent)
    if user:
        frappe.publish_realtime("fusionpbx_incoming_call", payload, user=user)
    else:
        frappe.publish_realtime("fusionpbx_incoming_call", payload)

    return {"ok": True, "matched": bool(contact), "route": route}


def _user_for_extension(extension):
    """Map a FusionPBX extension to an ERPNext user.
    Primary match is the custom fusionpbx_extension field on User; phone/mobile
    are checked as fallbacks."""
    if not extension:
        return None
    extension = str(extension).strip()
    for field in ("fusionpbx_extension", "phone", "mobile_no"):
        if frappe.db.has_column("User", field):
            user = frappe.db.get_value("User", {field: extension, "enabled": 1}, "name")
            if user:
                return user
    return None


@frappe.whitelist(allow_guest=True, methods=["GET"])
def lookup_contact(number=None):
    """Return {name, doctype, docname} for a phone number, or empty."""
    _require_authenticated()
    settings = _settings()
    if not settings.enabled:
        return {}
    contact = _find_contact(number)
    return contact or {}


@frappe.whitelist(allow_guest=True, methods=["GET"])
def export_contacts(limit=500, offset=0):
    """Flat list of {phone, name, doctype, docname} for the sync worker."""
    _require_authenticated()
    settings = _settings()
    if not settings.enabled:
        return []

    limit = min(int(limit or 500), 1000)
    offset = int(offset or 0)
    out = []

    # Contact Phone child table drives the primary export (paginated).
    rows = frappe.db.sql(
        """
        select cp.phone as phone, cp.parent as docname
        from `tabContact Phone` cp
        where cp.parenttype = 'Contact' and coalesce(cp.phone,'') != ''
        order by cp.parent
        limit %s offset %s
        """,
        (limit, offset),
        as_dict=True,
    )
    for r in rows:
        out.append({
            "phone": r["phone"],
            "name": _display_name("Contact", r["docname"]),
            "doctype": "Contact",
            "docname": r["docname"],
        })
    return out


@frappe.whitelist(allow_guest=True, methods=["POST"])
def ingest_call_log(**kwargs):
    """Create/update a Call Log doc from a FusionPBX CDR payload.
    Alternative to FusionPBX posting directly to /api/resource/Call Log; use this
    when you want ERPNext to also auto-link the matched contact."""
    _require_authenticated()
    settings = _settings()
    if not settings.enabled:
        frappe.throw(_("Integration disabled"))

    d = frappe.local.form_dict
    call_id = d.get("id")
    if not call_id:
        frappe.throw(_("id required"))

    if frappe.db.exists("Call Log", call_id):
        doc = frappe.get_doc("Call Log", call_id)
    else:
        doc = frappe.new_doc("Call Log")
        doc.id = call_id

    for field in ("from", "to", "type", "status", "duration", "medium",
                  "start_time", "end_time", "recording_url"):
        if d.get(field) is not None:
            doc.set(field, d.get(field))
    doc.save(ignore_permissions=True)

    # link the far-end party to a contact
    far_end = doc.get("from") if doc.get("type") == "Incoming" else doc.get("to")
    contact = _find_contact(far_end)
    if contact:
        _link_call_log(doc.name, contact)

    frappe.db.commit()
    return {"ok": True, "name": doc.name, "linked": bool(contact)}


# ---------------------------------------------------------------------------
# outbound: ERPNext -> FusionPBX (click to dial)
# ---------------------------------------------------------------------------

@frappe.whitelist(methods=["POST"])
def click_to_dial(destination=None, extension=None):
    """Originate a call through FusionPBX. Rings the agent's extension, then the
    destination. The agent's extension is resolved from their User phone unless
    passed explicitly."""
    settings = _settings()
    if not settings.enabled:
        frappe.throw(_("Integration disabled"))
    if not destination:
        frappe.throw(_("destination required"))

    if not extension:
        extension = _extension_for_user(frappe.session.user)
    if not extension:
        frappe.throw(_("No extension found for the current user; pass extension explicitly."))

    base = (settings.fusionpbx_url or "").rstrip("/")
    if not base:
        frappe.throw(_("FusionPBX URL not configured"))

    url = base + "/app/erpnext_integration/api/originate.php"
    body = {
        "domain_name": settings.domain_name,
        "extension": extension,
        "destination": destination,
        "caller_id_name": settings.originate_caller_id_name or "ERPNext",
    }
    headers = {
        "X-Fusionpbx-Secret": settings.get_password("shared_secret") or "",
        "Content-Type": "application/json",
    }

    try:
        resp = requests.post(url, json=body, headers=headers, timeout=15)
        resp.raise_for_status()
        return resp.json()
    except Exception as e:
        frappe.log_error(frappe.get_traceback(), "fusionpbx_integration: click_to_dial")
        frappe.throw(_("Click to dial failed: {0}").format(str(e)))


def _extension_for_user(user):
    for field in ("fusionpbx_extension", "phone", "mobile_no"):
        if frappe.db.has_column("User", field):
            val = frappe.db.get_value("User", user, field)
            if val:
                # extension field is stored as-is; strip formatting only for phone/mobile
                return val.strip() if field == "fusionpbx_extension" else re.sub(r"\D+", "", val)
    return None
