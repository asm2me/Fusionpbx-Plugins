--[[
	FusionPBX - ERPNext / Frappe Integration
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Dialplan shim for the inbound screen-pop.

	FreeSWITCH blocks the `system` dialplan application by default (it needs
	threaded-system-exec + a restart), so the dialplan calls this Lua script via
	`luarun` instead, which is not gated and never blocks the call.

	It reads the call's channel variables and launches the PHP notifier in the
	background so ERPNext gets an incoming-call screen-pop.

	Dialplan action:
		<action application="luarun"
		        data="app/erpnext_integration/resources/scripts/erpnext_screen_pop.lua"/>
	(FusionPBX resolves lua scripts relative to /usr/share/freeswitch/scripts and
	 also the app path; see install notes.)
]]

local domain_uuid  = session ~= nil and session:getVariable("domain_uuid") or nil
local caller       = nil
local destination  = nil
local call_uuid    = nil

-- luarun runs without a session bridged; use the message/env when present
if session ~= nil then
	domain_uuid = session:getVariable("domain_uuid")
	caller      = session:getVariable("caller_id_number")
	destination = session:getVariable("destination_number")
	call_uuid   = session:getVariable("uuid")
else
	-- when invoked via luarun the channel vars arrive as argv
	domain_uuid = argv and argv[1] or nil
	caller      = argv and argv[2] or nil
	destination = argv and argv[3] or nil
	call_uuid   = argv and argv[4] or nil
end

if domain_uuid == nil or caller == nil then
	freeswitch.consoleLog("warning", "[erpnext_screen_pop] missing domain_uuid or caller\n")
	return
end

-- sanitize (defensive) - allow digits, +, letters (named extensions), dash
local function clean(s)
	if s == nil then return "" end
	return (tostring(s):gsub("[^%w%+%-%.@]", ""))
end

local php = "/usr/bin/php"
local script = "/var/www/fusionpbx/app/erpnext_integration/resources/cron/erpnext_screen_pop.php"

local cmd = string.format(
	"%s %s %s %s %s %s >/dev/null 2>&1 &",
	php, script, clean(domain_uuid), clean(caller), clean(destination), clean(call_uuid)
)

os.execute(cmd)
freeswitch.consoleLog("info", "[erpnext_screen_pop] launched pop for caller " .. clean(caller) .. "\n")
