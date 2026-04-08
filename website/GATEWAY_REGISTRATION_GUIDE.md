# Gateway Registration System - Implementation Guide

## Overview

This document describes the complete gateway registration system implementation for FusionPBX plugins. The system provides automatic provisioning and configuration management for multiple gateway types including Dinstar, Yeastar, GOIP, Grandstream, and Cisco devices.

## System Architecture

### Components

1. **HTML Form** (`website/register.html`)
   - Multi-step registration interface
   - Gateway type selection with visual cards
   - Dynamic form field generation
   - Responsive design with accessibility

2. **CSS Styling** (`website/css/register.css`)
   - Gateway card styling with selection feedback
   - Form section styling
   - Responsive grid layout
   - Visual hierarchy and consistency

3. **JavaScript Logic** (`website/js/register.js`)
   - Form toggling and visibility management
   - Gateway type detection and configuration
   - Dynamic field population
   - Auto-enable functionality

4. **Test Page** (`website/test-register-form.html`)
   - Complete testing interface
   - Validation tests
   - Result reporting

## Features

### 1. Multi-Step Registration (5 Steps)

#### Step 1: Account Information
- Company name (required)
- Contact email (required)
- Contact phone (optional)
- Password (required)

#### Step 2: Gateway Selection
Users select from 6 gateway types with visual cards:
- **Dinstar** - Basic PSTN gateway
- **Yeastar S-Series** - Mid-range gateway with advanced features
- **DBL GOIP** - Mobile gateway for GSM/3G integration
- **Grandstream** - Professional enterprise gateway
- **Cisco** - Enterprise-grade gateway
- **Other** - Custom or unsupported gateways

#### Step 3: Gateway Configuration
When a gateway type is selected:
- Checkbox to enable gateway configuration
- Gateway name field (pre-filled based on type)
- SIP proxy address field (pre-filled with provider suggestion)
- Registration requirement selector
- Type-specific provisioning fields

**Type-Specific Fields:**

**Dinstar:**
- Device IP address
- Number of channels (1, 2, 4, 8)

**Yeastar:**
- Device IP address
- Model selection (S100, S300, S500)

**GOIP:**
- Device IP address
- SIM card slots (1, 2, 4, 8)

**Grandstream:**
- Device IP address
- Provisioning method (HTTP, HTTPS, TFTP)

**Cisco:**
- Device IP address
- Configuration template (Basic, Advanced, High Availability)

#### Step 4: Automatic Provisioning
- Enable/disable automatic provisioning
- Provisioning server URL (auto-filled based on gateway type)
- Provisioning method selection (HTTP, HTTPS, TFTP)
- Update check interval (hourly to weekly)
- Notification preferences (success, failure, updates)

#### Step 5: Review & Submit
- Terms of service agreement (required)
- Contact preference option
- Submit and reset buttons

### 2. Dynamic Form Generation

The JavaScript automatically generates appropriate form fields based on selected gateway type:

```javascript
const gatewayConfigs = {
    dinstar: {
        gwName: 'Dinstar Gateway',
        gwProxy: 'dinstar.sip.provider.com',
        gwRegister: 'true',
        provisioningUrl: 'https://your-pbx.com/provisioning/dinstar',
        provisioningFields: `...HTML for type-specific fields...`
    },
    // ... other types
};
```

### 3. Auto-Enable Functionality

When users select a known gateway type:
1. The gateway configuration section automatically becomes visible
2. The "Configure Gateway Settings" checkbox auto-checks
3. Gateway-specific form fields auto-populate
4. Type-specific provisioning options load

## Usage Instructions

### For End Users

1. **Navigate to the registration form** (`website/register.html`)
2. **Fill Account Information:**
   - Enter company name
   - Enter contact email
   - Enter phone number (optional)
   - Create a password
3. **Select Gateway Type:**
   - Click on your gateway type card
   - Form automatically configures for that type
4. **Configure Gateway:**
   - Review auto-filled settings
   - Adjust SIP proxy if needed
   - Configure type-specific settings
5. **Setup Provisioning (Optional):**
   - Check "Enable Automatic Provisioning"
   - Review/adjust provisioning server URL
   - Set update check interval
   - Configure notification preferences
6. **Review and Submit:**
   - Agree to terms of service
   - Click "Register Gateway"

### For Testing

1. **Open test page:** Open `website/test-register-form.html` in a browser
2. **Fill in test data:**
   - Company: "Test Company Inc."
   - Email: "test@company.com"
   - Password: "TestPassword123"
3. **Select a gateway type** - Watch form auto-configure
4. **Toggle options** to verify visibility control
5. **Submit form** to run validation tests
6. **Review test results** - All tests should pass

## Technical Details

### JavaScript Functions

#### `toggleGatewayForm()`
Controls visibility of gateway configuration form based on checkbox state.

```javascript
function toggleGatewayForm() {
    const checked = document.getElementById('configureGateway').checked;
    document.getElementById('gatewayForm').style.display = checked ? 'block' : 'none';
}
```

#### `toggleProvisioningOptions()`
Controls visibility of provisioning options based on checkbox state.

```javascript
function toggleProvisioningOptions() {
    const checked = document.getElementById('enableProvisioning').checked;
    document.getElementById('provisioningOptions').style.display = checked ? 'block' : 'none';
}
```

#### `updateGatewayForm()`
Main function that handles gateway type selection and form updates:

1. Reads selected gateway type from radio button
2. Looks up configuration from `gatewayConfigs` object
3. Auto-populates form fields with type-specific defaults
4. Generates and inserts type-specific HTML form fields
5. Shows provisioning section
6. Auto-enables gateway configuration checkbox

```javascript
function updateGatewayForm() {
    const gatewayType = document.querySelector('input[name="gateway_type"]:checked')?.value;
    const config = gatewayConfigs[gatewayType] || gatewayConfigs.other;
    // ... populate form fields ...
    // ... insert type-specific fields ...
    // ... show provisioning section ...
}
```

### CSS Classes

#### `.gateway-selection`
Grid layout for gateway type cards (responsive, 4-6 columns).

#### `.gateway-card`
Individual gateway type selection card with:
- Hover effects
- Selection state styling
- Radio button integration

#### `.form-section`
Grouped form content with background and padding.

#### `.form-hint`
Small help text under form fields (font-size: 12px, italic).

#### Visual Feedback
- Selected cards: Blue border and background
- Focused inputs: Blue border with shadow effect
- Disabled sections: Hidden with `display: none`

## Integration Guide

### Adding New Gateway Type

To add a new gateway type:

1. **Update JavaScript Configuration:**
```javascript
newType: {
    gwName: 'New Gateway Name',
    gwProxy: 'newtype.sip.provider.com',
    gwRegister: 'true',
    provisioningUrl: 'https://your-pbx.com/provisioning/newtype',
    provisioningFields: `
        <div class="form-group">
            <label><i class="fas fa-key"></i> Setting Name</label>
            <input type="text" id="newTypeSetting" placeholder="Value">
        </div>
    `
}
```

2. **Add Gateway Card to HTML:**
```html
<label class="gateway-card">
    <input type="radio" name="gateway_type" value="newtype" onchange="updateGatewayForm()">
    <div class="gateway-info">New Gateway</div>
</label>
```

3. **Test the Integration:**
   - Select the new gateway type
   - Verify form auto-populates correctly
   - Confirm type-specific fields appear
   - Test provisioning configuration

## Form Validation

The system includes client-side validation for:

- **Company Name:** 3+ characters
- **Email:** Valid email format
- **Gateway Type:** Must be selected
- **Gateway Name:** Required if configuration enabled
- **SIP Proxy:** Required if configuration enabled
- **Terms of Service:** Must be checked

Server-side validation (suggested):
- Email uniqueness check
- Domain availability validation
- SIP proxy connectivity test
- Gateway device reachability test
- Provisioning URL availability

## Security Considerations

1. **Password Requirements:**
   - Minimum 8 characters
   - Must include uppercase and lowercase
   - Must include numbers
   - Consider adding special character requirement

2. **Data Protection:**
   - Use HTTPS for form submission
   - Encrypt SIP credentials in database
   - Validate gateway IP addresses server-side
   - Sanitize all user inputs

3. **API Security:**
   - Implement rate limiting
   - Validate provisioning URLs (whitelist-only)
   - Use OAuth or API tokens for provisioning
   - Log all provisioning activities

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

Required features:
- CSS Grid and Flexbox
- ES6 JavaScript (arrow functions, template literals)
- CSS Custom Properties (optional, with fallbacks)
- Font Awesome 6.x for icons

## File Structure

```
website/
├── register.html                 # Main registration form
├── test-register-form.html      # Testing interface
├── css/
│   └── register.css             # Styling
├── js/
│   └── register.js              # JavaScript logic
└── resources/                   # Gateway logos and docs
    ├── dinstar-setup.pdf
    ├── yeastar-setup.pdf
    ├── goip-setup.pdf
    ├── grandstream-setup.pdf
    └── cisco-setup.pdf
```

## Troubleshooting

### Issue: Form fields not appearing when gateway type is selected

**Solution:** 
- Clear browser cache
- Check browser console for JavaScript errors
- Verify CSS is fully loaded
- Ensure element IDs match between HTML and JavaScript

### Issue: Provisioning section not showing

**Solution:**
- Verify gateway type is selected (must be one of the 6 types)
- Check that JavaScript `updateGatewayForm()` is firing
- Verify element ID `gatewayProvisioningSection` exists

### Issue: Gateway type selection not updating form

**Solution:**
- Ensure radio button `name="gateway_type"` is correct
- Verify `onchange="updateGatewayForm()"` is added to radio buttons
- Check browser console for JavaScript errors
- Verify Font Awesome is loaded for icons

## Future Enhancements

1. **Multi-language Support:**
   - i18n implementation for form labels
   - Translated provisioning guides
   - Multi-language email templates

2. **Advanced Features:**
   - Gateway firmware update scheduling
   - Automatic backup configuration
   - Health monitoring and alerts
   - SIP traffic analysis and optimization

3. **Integration:**
   - API documentation and SDKs
   - Webhook support for provisioning events
   - Third-party gateway support
   - Admin dashboard for managing registrations

4. **User Experience:**
   - Form progress indicator
   - Inline help documentation
   - Live validation with suggestions
   - Mobile app integration

## Support Resources

- **Documentation:** [Link to full docs]
- **API Reference:** [Link to API docs]
- **Gateway Guides:** See resources folder
- **Community Forum:** [Link to forum]
- **Support Email:** support@fusionpbx.com

## Changelog

### Version 1.0.0 (2024)
- Initial release
- Support for 5 major gateway types
- Automatic provisioning configuration
- Multi-step registration form
- Client-side validation
- Responsive design

---

**Last Updated:** 2024
**Version:** 1.0.0
**Status:** Production Ready
