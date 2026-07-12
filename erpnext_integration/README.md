# FusionPBX ⇄ ERPNext / Frappe Integration

Bidirectional integration between **FusionPBX** and **ERPNext / Frappe**.

| Feature | Direction | How |
|---|---|---|
| **CDR → Call Logs** | FusionPBX → ERPNext | A worker drains completed calls from `v_xml_cdr` and creates ERPNext **Call Log** documents (idempotent by CDR uuid). |
| **Recording links** | FusionPBX → ERPNext | The recording download URL is attached to each Call Log. |
| **Screen-pop** | FusionPBX → ERPNext | On inbound calls the dialplan notifies ERPNext, which raises a realtime screen-pop of the matching Contact/Lead/Customer for the agent. |
| **Click-to-dial** | ERPNext → FusionPBX | A "Call" button on phone fields originates a call: rings the agent's extension, then dials the destination. |
| **Contact sync** | ERPNext → FusionPBX | A worker caches ERPNext contacts locally for fast caller-ID name lookup. |

It ships as **two components** that live together in this folder:

```
erpnext_integration/                 <- FusionPBX app (PHP)
├── app_config.php / app_defaults.php / app_menu.php / app_permissions.php
├── erpnext_integration.php          <- settings page (Advanced > ERPNext Integration)
├── resources/classes/erpnext.php    <- ERPNext REST client
├── resources/cron/                  <- CDR push, contact sync, screen-pop workers
├── api/originate.php                <- click-to-dial endpoint (ERPNext calls this)
├── api/lookup.php                   <- caller-ID name lookup endpoint
└── frappe_app/                      <- ERPNext/Frappe app (Python)
    └── fusionpbx_integration/
        ├── hooks.py
        ├── api.py                   <- whitelisted endpoints
        ├── public/js/               <- click-to-dial button + screen-pop listener
        └── fusionpbx_integration/doctype/fusionpbx_settings/
```

---

## 1. Install the FusionPBX app

```bash
cd /var/www/fusionpbx/app
git clone <this-repo> tmp && cp -r tmp/erpnext_integration ./ && rm -rf tmp
# or copy the erpnext_integration/ folder into /var/www/fusionpbx/app/
chown -R www-data:www-data /var/www/fusionpbx/app/erpnext_integration
```

Then in the GUI: **Advanced → Upgrade → App Defaults + Schema + Permissions + Menu** (check all, execute). This creates the `v_erpnext_cdr_queue` and `v_erpnext_contacts` tables and the menu entry.

### Configure

**Advanced → ERPNext Integration**:

- **Enabled**: True
- **ERPNext URL**: `https://erp.example.com`
- **API Key / API Secret**: from ERPNext **User → Settings → API Access → Generate Keys**
- **Inbound Shared Secret**: any strong random string (ERPNext must send it back)
- **Recording Base URL**: `https://pbx.example.com/app/xml_cdr/xml_cdr_download.php?id=`

Click **Test Connection**.

### Cron jobs

```cron
# push completed calls to ERPNext every minute
* * * * * www-data php /var/www/fusionpbx/app/erpnext_integration/resources/cron/erpnext_cdr_push.php >/dev/null 2>&1
# sync ERPNext contacts for caller-ID lookup every 15 minutes
*/15 * * * * www-data php /var/www/fusionpbx/app/erpnext_integration/resources/cron/erpnext_contact_sync.php >/dev/null 2>&1
```

### Screen-pop dialplan action

On your inbound route (**Dialplan → Dialplan Manager**), add an action **before** the bridge:

```
application: system
data: php /var/www/fusionpbx/app/erpnext_integration/resources/cron/erpnext_screen_pop.php ${domain_uuid} ${caller_id_number} ${destination_number} ${uuid}
```

(For a non-blocking version wrap it: `data: php ... &` — the notifier already fails fast, but `&` guarantees zero call latency.)

---

## 2. Install the Frappe app

```bash
cd /home/frappe/frappe-bench
bench get-app fusionpbx_integration /path/to/erpnext_integration/frappe_app
bench --site your-site install-app fusionpbx_integration
bench build && bench --site your-site clear-cache
```

### Configure (ERPNext side)

Open **FusionPBX Settings** (single doctype):

- **Enabled**: ✔
- **FusionPBX Base URL**: `https://pbx.example.com`
- **FusionPBX Domain Name**: the sofia domain, e.g. `pbx.example.com`
- **Shared Secret**: **same** string as the FusionPBX "Inbound Shared Secret"
- **Trailing Digits to Match**: `9` (how many trailing digits to compare for contact matching)

For click-to-dial, each agent's ERPNext **User** should have their extension in the User's phone/mobile field (or the extension can be passed explicitly).

---

## How the pieces talk

```
Inbound call ─┐
              │  dialplan system action
              ▼
  erpnext_screen_pop.php ──POST /api/method/…incoming_call──► ERPNext
                                                              publish_realtime
                                                              ► agent screen-pop

Completed call ─► v_xml_cdr ─► erpnext_cdr_push.php ──POST /api/resource/Call Log──► ERPNext

Agent clicks "Call" in ERPNext
   ──POST …click_to_dial──► click_to_dial() ──POST /app/erpnext_integration/api/originate.php──►
      FusionPBX event_socket originate ─► rings agent, bridges to destination

Caller-ID lookup:  FusionPBX lookup.php ─► local cache ─► (fallback) …lookup_contact
Contact sync:      erpnext_contact_sync.php ──GET …export_contacts──► cache in v_erpnext_contacts
```

## Security notes

- ERPNext → FusionPBX endpoints (`originate.php`, `lookup.php`) are protected by the **shared secret** (`X-Fusionpbx-Secret`, compared with `hash_equals`). Serve them over HTTPS only.
- FusionPBX → ERPNext uses ERPNext **API key/secret** token auth.
- The Frappe whitelisted methods are `allow_guest=True` because FusionPBX authenticates with key/secret token headers (which Frappe validates) — keep the FusionPBX box on a trusted network / behind TLS.
- Set **Verify TLS = True** in production; disable only for self-signed lab certs.

## License

Mozilla Public License 1.1 — © VOIPEGYPT
