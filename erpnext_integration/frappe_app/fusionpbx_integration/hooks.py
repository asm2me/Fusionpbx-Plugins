app_name = "fusionpbx_integration"
app_title = "FusionPBX Integration"
app_publisher = "VOIPEGYPT"
app_description = "Bidirectional integration between ERPNext/Frappe and FusionPBX"
app_email = "info@voipegypt.com"
app_license = "MPL-1.1"

# Includes in <head>
# ------------------
# Client-side script that adds a click-to-dial button on phone fields and
# listens for inbound-call screen-pop events.
app_include_js = "/assets/fusionpbx_integration/js/fusionpbx_integration.js"

# Website / desk realtime rooms are used to push screen-pop events to agents.

# Document Events
# ---------------
# (none needed by default; CDRs are pushed from FusionPBX into Call Log)

# Fixtures / Roles could be added here if desired.

# Whitelisted methods exposed at /api/method/fusionpbx_integration.api.*
# are defined in fusionpbx_integration/api.py

# Fixtures
# --------
# Ship the custom "FusionPBX Extension" field on User so each agent's extension
# can be stored (used to route inbound screen-pops to the right person).
fixtures = [
    {
        "dt": "Custom Field",
        "filters": [["name", "in", ["User-fusionpbx_extension"]]],
    }
]
