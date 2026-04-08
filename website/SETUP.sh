#!/bin/bash
# Gateway Registration System - Quick Setup Guide
# This script sets up the gateway registration system files

echo "=========================================="
echo "FusionPBX Gateway Registration Setup"
echo "=========================================="
echo ""

# Check if we're in the right directory
if [ ! -f "website/register.html" ]; then
    echo "Error: register.html not found in website directory"
    echo "Please run this script from the project root directory"
    exit 1
fi

echo "✓ Project structure verified"
echo ""

# Create required directories if they don't exist
echo "Creating directory structure..."
mkdir -p website/resources
mkdir -p website/assets/images
mkdir -p website/api

echo "✓ Directories created"
echo ""

# Check for required files
echo "Checking for required files..."

files=(
    "website/register.html"
    "website/test-register-form.html"
    "website/css/register.css"
    "website/js/register.js"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "✓ Found: $file"
    else
        echo "✗ Missing: $file"
    fi
done

echo ""
echo "=========================================="
echo "Setup Instructions"
echo "=========================================="
echo ""
echo "1. Review the configuration:"
echo "   - Edit website/js/register.js"
echo "   - Update provisioning URLs for your environment"
echo "   - Customize gateway types as needed"
echo ""
echo "2. Customize styling (optional):"
echo "   - Edit website/css/register.css"
echo "   - Match your brand colors and fonts"
echo ""
echo "3. Test the system:"
echo "   - Open website/test-register-form.html in a browser"
echo "   - Or run: python -m http.server 8000"
echo "   - Navigate to http://localhost:8000/website/register.html"
echo ""
echo "4. Set up API endpoint:"
echo "   - Create /api/register-gateway endpoint"
echo "   - Handle POST requests with form data"
echo "   - Example:"
echo "     POST /api/register-gateway"
echo "     Body: {company_name, contact_email, gateway_type, ...}"
echo ""
echo "5. Deploy to production:"
echo "   - Copy files to your web server"
echo "   - Ensure HTTPS is enabled"
echo "   - Update form action URL to production endpoint"
echo ""
echo "=========================================="
echo "Quick Testing"
echo "=========================================="
echo ""
echo "To test locally:"
echo "  1. cd website/"
echo "  2. python -m http.server 8080"
echo "  3. Open http://localhost:8080/test-register-form.html"
echo ""
echo "=========================================="
echo "Documentation"
echo "=========================================="
echo ""
echo "Full documentation: website/GATEWAY_REGISTRATION_GUIDE.md"
echo ""
echo "Key features:"
echo "  • Multi-step registration form (5 steps)"
echo "  • Support for 6 gateway types"
echo "  • Automatic provisioning configuration"
echo "  • Type-specific form fields"
echo "  • Responsive design"
echo "  • Client-side validation"
echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
