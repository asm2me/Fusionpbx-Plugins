# Domain Wizard Registration - Implementation Guide

## Overview
The enhanced domain wizard now supports a multi-step registration process with the following features:

## Wizard Steps

### Step 1: Basic Information
- Full Name
- Email
- Phone (international format)
- Company

### Step 2: Domain Selection (MODIFIED)
**Validation**: If domain already exists, prevent proceeding to next step
- Only accepts voipat.com subdomains
- Subdomain entry (e.g., "acmecorp" → acmecorp.voipat.com)
- Real-time availability check via `action=check_domain`
- Auto-registers subdomain in Namecheap with server IP

**API Call**:
```javascript
POST domain_wizard_register.php
action=check_domain&domain=acmecorp.voipat.com
```

### Step 3: Plan & Installation Type (NEW)
**Installation Types**:
- **Company PBX**: Small to medium businesses with extensions, call routing, voicemail, basic IVR
- **Call Center**: Customer service with agent queues, recording, advanced IVR, CRM integration
- **Auto Dialer**: Campaigns with call lists, auto-dialing, campaign management

**Hints Available for**:
- Installation Type: "Choose the deployment type that best matches your organization"
- Extension Start: "The first extension number to assign (typically 100, 1000, or 2001)"
- Extensions Count: "Total number of extensions your plan includes"

**API Call**:
```javascript
GET action=get_installation_types
GET action=get_field_hints
```

### Step 4: Configuration (MODIFIED with Hints)
**Fields**: (all with hints via `action=get_field_hints`)
- Admin Username 
- Admin Password
- Extension Start Number
- Extensions Count

### Step 5: IVR & Call Routing (NEW - Chart Designer)
**Features**:
- Visual IVR chart designer
- Drag-and-drop nodes
- Support for multiple sub-IVRs based on plan
- Node types: IVR, Extension, Ring Group, Voicemail, Transfer, Disconnect
- DTMF routing configuration

**Classes to use**:
- `ivr_chart_designer` - Create, validate, and visualize IVR charts
- Methods:
  - `create_node()` - Create a node
  - `add_child_node()` - Add DTMF routing
  - `generate_visualization()` - Get visualization data
  - `validate_chart()` - Validate structure
  - `export_json()` - Save as JSON
  - `generate_html_visualization()` - HTML/SVG output

### Step 6: Devices & Trunks (NEW)
**Device Types** (via `action=get_device_types`):
- Yealink GSM/LTE Gateway
- Dynstar GSM/LTE Gateway
- DBL GOIP (4/8/16 channels)
- eJoin Gateway

**Trunk Services**:
- VoIP Service Provider (requires: username, password, server)
- Mobile Carrier Gateway (requires: gateway type, channels)
- Local Telecom Carrier (requires: account number, auth code)

**Features**:
- Select device type
- Configure trunk authentication data
- Export provision/backup file

### Step 7: Outbound Configuration (NEW)
**Outbound Gateway Selection**:
- Choose gateway for outbound calls
- Configure outbound dialplan
- Set default route

### Step 8: Review & Finalize (NEW)
**Display**:
- Complete inbound call routing tree (from gateway to extension)
- Visual representation of call flow
- Summary of all configuration
- Final confirmations

**Class to use**: `call_routing_tree`
- Methods:
  - `generate_inbound_tree()` - Create tree structure
  - `generate_html_visualization()` - Visual representation
  - `generate_text_representation()` - Text summary

## Helper Classes

### 1. namecheap_integration.php
```php
$nc = new namecheap_integration($api_user, $api_key);
$result = $nc->register_subdomain($subdomain, $target_ip);
// or use convenience function:
register_namecheap_subdomain($subdomain, $target_ip);
```

### 2. ivr_chart_designer.php
```php
$node = ivr_chart_designer::create_node('ivr', 'main_menu', [
    'label' => 'Main Menu',
    'prompt' => 'Press 1 for Sales...',
    'timeout' => 5,
    'max_attempts' => 3,
]);

$child = ivr_chart_designer::create_node('extension', 'ext_sales', [
    'extension' => '101'
]);

ivr_chart_designer::add_child_node($node, $child, 1);

$viz = ivr_chart_designer::generate_visualization($node);
$html = ivr_chart_designer::generate_html_visualization($node);
```

### 3. call_routing_tree.php
```php
$tree = call_routing_tree::generate_inbound_tree(
    $domain_uuid,
    $gateways_list,
    $ivr_config,
    $extensions_list
);

$html = call_routing_tree::generate_html_visualization($tree);
$json = call_routing_tree::export_json($tree);
$text = call_routing_tree::generate_text_representation($tree);
```

## Frontend Implementation

### Quick Start Example
```javascript
// Step 1: Check domain availability
fetch('domain_wizard_register.php', {
    method: 'GET',
    body: new URLSearchParams({
        action: 'check_domain',
        subdomain: 'acmecorp'
    })
})
.then(r => r.json())
.then(data => {
    if (data.available) {
        // Can proceed to Step 3
    }
});

// Step 3: Get installation types
fetch('domain_wizard_register.php?action=get_installation_types')
    .then(r => r.json())
    .then(data => {
        // Display types with hints
    });

// Step 4: Get field hints
fetch('domain_wizard_register.php?action=get_field_hints')
    .then(r => r.json())
    .then(hints => {
        // Show hints when user focuses on field
    });

// Step 6: Get device types
fetch('domain_wizard_register.php?action=get_device_types')
    .then(r => r.json())
    .then(data => {
        // Display devices and trunk services
    });

// Final: Register domain
fetch('domain_wizard_register.php', {
    method: 'POST',
    body: formData, // Include all configuration
})
.then(r => r.json())
.then(result => {
    if (result.status === 'success') {
        window.location = 'https://' + result.domain_name;
    }
});
```

## Configuration Storage

All wizard configuration is stored as domain settings:
- `registration.contact_name`
- `registration.contact_email`
- `registration.contact_phone`
- `registration.company`
- `registration.plan`
- `registration.installation_type`
- `registration.extension_start`
- `registration.namecheap_registered`
- `registration.ivr_chart_config` (JSON)
- `registration.device_config` (JSON)
- `registration.outbound_config` (JSON)

## Error Handling

The API returns structured JSON:
```json
{
    "status": "success|error",
    "message": "Description",
    "available": true/false,
    "details": "Additional info",
    "domain": "acmecorp.voipat.com",
    "hints": {...},
    "types": {...}
}
```

## Security

- Rate limiting: 10 registrations per hour per IP
- CORS validation: Only from voipat.com and localhost
- Subdomain format validation: Alphanumeric and hyphens only
- Email validation: RFC compliant
- Password: Minimum 8 characters
- Namecheap API credentials via environment variables (NAMECHEAP_API_USER, NAMECHEAP_API_KEY)

## Future Enhancements

- SIP device provisioning templates
- Automatic call recording setup
- CRM integration templates
- Mobile app provisioning
- Failover gateway configuration
- Advanced analytics setup
