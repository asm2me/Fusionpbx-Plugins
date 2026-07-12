// Copyright (c) 2026, VOIPEGYPT
// License: MPL-1.1
//
// Adds click-to-dial to phone fields and shows a screen-pop when FusionPBX
// notifies of an inbound call.

frappe.provide("fusionpbx_integration");

// ---- Click to dial -------------------------------------------------------

fusionpbx_integration.dial = function (number) {
	if (!number) return;
	frappe.call({
		method: "fusionpbx_integration.api.click_to_dial",
		args: { destination: number },
		freeze: true,
		freeze_message: __("Calling {0}...", [number]),
		callback: function (r) {
			if (r.message && r.message.status === "success") {
				frappe.show_alert({ message: __("Calling {0}", [number]), indicator: "green" });
			} else {
				frappe.show_alert({ message: __("Call could not be started"), indicator: "red" });
			}
		},
	});
};

// Add a phone icon next to Data fields with options == "Phone" on any form.
frappe.ui.form.on("*", {
	refresh: function (frm) {
		if (!frm.fields) return;
		(frm.meta.fields || []).forEach(function (df) {
			if (df.fieldtype === "Data" && (df.options === "Phone" || /phone|mobile/i.test(df.fieldname))) {
				var value = frm.doc[df.fieldname];
				if (!value) return;
				frm.add_custom_button(
					__("Call {0}", [value]),
					function () {
						fusionpbx_integration.dial(value);
					},
					__("Dial")
				);
			}
		});
	},
});

// ---- Screen pop ----------------------------------------------------------

frappe.realtime.on("fusionpbx_incoming_call", function (data) {
	if (!data) return;
	var caller = data.from || __("Unknown");
	var name = data.contact ? data.contact.name : __("Unknown caller");

	var msg = data.contact
		? __("Incoming call from <b>{0}</b> ({1})", [name, caller])
		: __("Incoming call from <b>{0}</b>", [caller]);

	// Non-blocking toast with an "Open" action
	frappe.show_alert(
		{
			message: msg,
			indicator: data.contact ? "green" : "orange",
		},
		12
	);

	// Auto-navigate to the matched record
	if (data.route) {
		frappe.set_route(data.route.replace(/^\/app\//, "").split("/"));
	}
});
