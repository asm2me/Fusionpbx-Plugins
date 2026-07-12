# Copyright (c) 2026, VOIPEGYPT and contributors
# License: MPL-1.1

import frappe
from frappe.model.document import Document


class FusionPBXSettings(Document):
    pass


def get_settings():
    """Return the FusionPBX Settings single doc."""
    return frappe.get_single("FusionPBX Settings")
