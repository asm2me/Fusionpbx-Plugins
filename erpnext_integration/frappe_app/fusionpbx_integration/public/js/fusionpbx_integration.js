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

	if (data.contact) {
		// Known caller: toast + auto-open the matched record
		frappe.show_alert(
			{
				message: __("Incoming call from <b>{0}</b> ({1})", [name, caller]),
				indicator: "green",
			},
			12
		);
		if (data.route) {
			frappe.set_route(data.route.replace(/^\/app\//, "").split("/"));
		}
	} else {
		// Unknown caller: toast with a one-click "Create Contact" action,
		// pre-filled with the caller's number. Nothing is created automatically.
		var create_link =
			"<a href='#' onclick=\"fusionpbx_integration.create_contact('" +
			(caller || "").replace(/'/g, "") +
			"'); return false;\">" +
			__("Create Contact") +
			"</a>";
		frappe.show_alert(
			{
				message: __("Incoming call from <b>{0}</b> &nbsp; {1}", [caller, create_link]),
				indicator: "orange",
			},
			20
		);
	}
});

// Open a new Contact form pre-filled with the caller's number.
fusionpbx_integration.create_contact = function (number) {
	frappe.new_doc("Contact", {}, function (doc) {
		if (number) {
			// set the primary phone on the new contact
			doc.phone_nos = [{ phone: number, is_primary_phone: 1 }];
			frappe.set_route("Form", "Contact", doc.name);
		}
	});
};
