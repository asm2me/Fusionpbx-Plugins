# FusionPBX Integration (Frappe app)

Frappe/ERPNext side of the FusionPBX ⇄ ERPNext integration.

Provides:

- **FusionPBX Settings** single DocType (connection details + shared secret)
- Whitelisted API (`fusionpbx_integration.api.*`): `incoming_call` (screen-pop),
  `lookup_contact`, `export_contacts`, `ingest_call_log`, `click_to_dial`
- Desk client script: a **Call** button on phone fields and an inbound-call
  screen-pop listener

## Install

```bash
cd /home/frappe/frappe-bench
bench get-app fusionpbx_integration /path/to/erpnext_integration/frappe_app
bench --site <site> install-app fusionpbx_integration
bench build && bench --site <site> clear-cache && bench restart
```

Then configure **FusionPBX Settings** (Enabled, FusionPBX URL, Domain Name,
Shared Secret matching the FusionPBX side).

See the parent `../README.md` for the full two-sided deployment guide.

License: MPL-1.1 — © VOIPEGYPT
