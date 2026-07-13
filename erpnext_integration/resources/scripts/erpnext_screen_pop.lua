--[[
	FusionPBX - ERPNext / Frappe Integration
	Copyright (c) VOIPEGYPT - https://voipegypt.com
	License: MPL 1.1

	Inbound screen-pop shim (self-contained: no PHP, no os.execute, no system app).

	FreeSWITCH blocks the `system` dialplan application by default, and Lua's
	os.execute is unreliable inside the FS sandbox, so this script does the work
	itself: it reads the domain's ERPNext settings from the FusionPBX database and
	POSTs the incoming-call notification to the companion Frappe app via mod_curl.
	It never blocks the call.

	Create the dialplan action in the FusionPBX GUI (Dialplan Manager) as:
		application: lua
		data:        app/erpnext_integration/resources/scripts/erpnext_screen_pop.lua

	Channel variables (domain_uuid, caller_id_number, destination_number, uuid) are
	read from the session. For standalone testing you can also run it via luarun
	with those four values as arguments.
]]

-- ---------------------------------------------------------------------------
-- gather call context
-- ---------------------------------------------------------------------------
local domain_uuid, caller, destination, call_uuid

if session ~= nil then
	domain_uuid = session:getVariable("domain_uuid")
	caller      = session:getVariable("caller_id_number")
	destination = session:getVariable("destination_number")
	call_uuid   = session:getVariable("uuid")
elseif argv ~= nil then
	domain_uuid, caller, destination, call_uuid = argv[1], argv[2], argv[3], argv[4]
end

local function log(level, msg)
	freeswitch.consoleLog(level, "[erpnext_screen_pop] " .. msg .. "\n")
end

if domain_uuid == nil or domain_uuid == "" or caller == nil or caller == "" then
	log("warning", "missing domain_uuid or caller; skipping")
	return
end

-- ---------------------------------------------------------------------------
-- load this domain's ERPNext settings from the database
-- ---------------------------------------------------------------------------
-- Use FusionPBX's own database resource so the DSN/credentials are resolved the
-- same way as every other FusionPBX Lua script (works across pgsql/odbc setups).
local settings = {}
local safe_uuid = domain_uuid:gsub("[^%x%-]", "")
local sql =
	"select domain_setting_subcategory as k, domain_setting_value as v " ..
	"from v_domain_settings where domain_uuid = '" .. safe_uuid .. "' " ..
	"and domain_setting_category = 'erpnext' and domain_setting_enabled = 'true'"

local ok_db, Database = pcall(require, "resources.functions.database")
if ok_db and Database then
	local dbh = Database.new('system')
	if dbh then
		dbh:query(sql, function(row)
			settings[row.k] = row.v
		end)
		dbh:release()
	end
end

-- fall back to a direct Dbh if the FusionPBX resource was unavailable
if next(settings) == nil then
	require 'resources.functions.config'
	local dsn
	if database and database.system then
		dsn = "pgsql://hostaddr=" .. (database.system.host or "127.0.0.1") ..
			" dbname=" .. (database.system.database or "fusionpbx") ..
			" user=" .. (database.system.username or "fusionpbx") ..
			" password=" .. (database.system.password or "")
	end
	if dsn then
		local dbh = freeswitch.Dbh(dsn)
		if dbh and dbh:connected() then
			dbh:query(sql, function(row) settings[row.k] = row.v end)
			dbh:release()
		end
	end
end

if next(settings) == nil then
	log("err", "cannot read erpnext settings from database; skipping")
	return
end

if settings["enabled"] ~= "true" or settings["screen_pop"] ~= "true" then
	log("info", "screen-pop disabled for this domain; skipping")
	return
end

local url    = settings["url"]
local apikey = settings["api_key"]
local apisec = settings["api_secret"]
if not url or url == "" or not apikey or apikey == "" or not apisec or apisec == "" then
	log("warning", "erpnext connection not fully configured; skipping")
	return
end

-- ---------------------------------------------------------------------------
-- POST the incoming-call notification via mod_curl (non-blocking, short timeout)
-- ---------------------------------------------------------------------------
local endpoint = url:gsub("/+$", "") .. "/api/method/fusionpbx_integration.api.incoming_call"

local function enc(s)
	return tostring(s or ""):gsub("[^%w]", function(c)
		return string.format("%%%02X", string.byte(c))
	end)
end

local post =
	"from="         .. enc(caller) ..
	"&agent="       .. enc(destination) ..
	"&call_uuid="   .. enc(call_uuid) ..
	"&domain_uuid=" .. enc(domain_uuid)

local api = freeswitch.API()
local args = string.format(
	"%s post %s connect_timeout 3 timeout 6 headers %%Authorization: token %s:%s%%",
	endpoint, post, apikey, apisec)
api:execute("curl", args)

log("info", "notified ERPNext for caller " .. caller)
