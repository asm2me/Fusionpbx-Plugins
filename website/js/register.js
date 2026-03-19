/* ========================================
   VOIP@ Cloud - Registration JavaScript
   ======================================== */

// Configuration - UPDATE THESE VALUES
const CONFIG = {
    // The FusionPBX server URL where domain_wizard_register.php is deployed
    apiUrl: 'https://mt.voipat.com/app/domain_wizard/domain_wizard_register.php',
    // The server IP users need for DNS A records
    serverIp: 'YOUR_SERVER_IP',
    // Temporary access URL pattern
    tempUrlPattern: 'https://mt.voipat.com'
};

let currentStep = 1;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check URL params for pre-selected plan
    const params = new URLSearchParams(window.location.search);
    const plan = params.get('plan');
    if (plan) {
        const radio = document.querySelector(`input[name="plan"][value="${plan}"]`);
        if (radio) {
            radio.checked = true;
        }
        showSelectedPlan(plan);
    }

    // Domain name validation on blur
    const domainInput = document.getElementById('domainName');
    if (domainInput) {
        domainInput.addEventListener('blur', checkDomain);
        domainInput.addEventListener('input', function() {
            document.getElementById('domainCheck').innerHTML = '';
        });
    }

    // Password strength indicator
    const passwordInput = document.getElementById('adminPassword');
    if (passwordInput) {
        passwordInput.addEventListener('input', updatePasswordStrength);
    }
});

// Show selected plan info
function showSelectedPlan(plan) {
    const box = document.getElementById('selectedPlanBox');
    const display = document.getElementById('planDisplay');
    if (!box || !display) return;

    const plans = {
        starter: { name: 'Starter', price: '$29/mo', features: '10 Extensions, 1 Gateway, 2 IVRs' },
        business: { name: 'Business', price: '$79/mo', features: '50 Extensions, 5 Gateways, 10 IVRs' },
        enterprise: { name: 'Enterprise', price: '$199/mo', features: 'Unlimited Extensions, Gateways, IVRs' }
    };

    const p = plans[plan];
    if (p) {
        display.innerHTML = `<strong>${p.name}</strong> - ${p.price}<br><small style="color:var(--gray-500)">${p.features}</small>`;
        box.style.display = 'block';
    }
}

// Step navigation
function nextStep(step) {
    // Validate current step
    if (!validateStep(currentStep)) return;

    // Mark current step as completed
    const indicators = document.querySelectorAll('.form-step-indicator');
    const lines = document.querySelectorAll('.step-line');

    indicators[currentStep - 1].classList.remove('active');
    indicators[currentStep - 1].classList.add('completed');
    indicators[currentStep - 1].querySelector('.step-dot').innerHTML = '<i class="fas fa-check" style="font-size:0.75rem"></i>';

    if (currentStep - 1 < lines.length) {
        lines[currentStep - 1].classList.add('completed');
    }

    // Show next step
    document.getElementById('step' + currentStep).classList.remove('active');
    document.getElementById('step' + step).classList.add('active');
    indicators[step - 1].classList.add('active');

    currentStep = step;
}

function prevStep(step) {
    const indicators = document.querySelectorAll('.form-step-indicator');
    const lines = document.querySelectorAll('.step-line');

    indicators[currentStep - 1].classList.remove('active');
    document.getElementById('step' + currentStep).classList.remove('active');

    // Restore previous step
    indicators[step - 1].classList.remove('completed');
    indicators[step - 1].classList.add('active');
    indicators[step - 1].querySelector('.step-dot').textContent = step;

    if (step - 1 < lines.length) {
        lines[step - 1].classList.remove('completed');
    }

    document.getElementById('step' + step).classList.add('active');
    currentStep = step;
}

// Validate step fields
function validateStep(step) {
    const stepEl = document.getElementById('step' + step);
    const inputs = stepEl.querySelectorAll('input[required], select[required]');
    let valid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = '#EF4444';
            input.addEventListener('input', function handler() {
                this.style.borderColor = '';
                this.removeEventListener('input', handler);
            });
            valid = false;
        }
    });

    if (step === 2) {
        // Validate domain format
        const domain = document.getElementById('domainName').value.trim();
        if (domain && !isValidDomain(domain)) {
            document.getElementById('domainCheck').innerHTML = '<span style="color:#EF4444"><i class="fas fa-times-circle"></i> Please enter a valid domain name (e.g., pbx.yourcompany.com)</span>';
            valid = false;
        }

        // Validate password match
        const pass = document.getElementById('adminPassword').value;
        const confirm = document.getElementById('confirmPassword').value;
        if (pass && confirm && pass !== confirm) {
            document.getElementById('confirmPassword').style.borderColor = '#EF4444';
            valid = false;
            alert('Passwords do not match');
        }

        // Validate password length
        if (pass && pass.length < 8) {
            document.getElementById('adminPassword').style.borderColor = '#EF4444';
            valid = false;
            alert('Password must be at least 8 characters');
        }
    }

    if (!valid) {
        // Shake the form
        const form = document.querySelector('.register-form');
        form.style.animation = 'shake 0.5s ease';
        setTimeout(() => form.style.animation = '', 500);
    }

    return valid;
}

function isValidDomain(domain) {
    const pattern = /^([a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/;
    return pattern.test(domain);
}

// Check domain availability
function checkDomain() {
    const domain = document.getElementById('domainName').value.trim();
    const checkEl = document.getElementById('domainCheck');

    if (!domain || !isValidDomain(domain)) {
        checkEl.innerHTML = '';
        return;
    }

    checkEl.innerHTML = '<span class="checking"><i class="fas fa-spinner fa-spin"></i> Checking availability...</span>';

    // Call the registration API to check domain
    fetch(CONFIG.apiUrl + '?action=check_domain&domain=' + encodeURIComponent(domain))
        .then(r => r.json())
        .then(data => {
            if (data.available) {
                checkEl.innerHTML = '<span class="available"><i class="fas fa-check-circle"></i> Domain is available!</span>';
            } else {
                checkEl.innerHTML = '<span class="taken"><i class="fas fa-times-circle"></i> Domain is already in use. Try another.</span>';
            }
        })
        .catch(() => {
            // If API is not reachable, skip the check
            checkEl.innerHTML = '<span style="color:var(--gray-400)"><i class="fas fa-info-circle"></i> Domain will be verified during registration</span>';
        });
}

// Password strength
function updatePasswordStrength() {
    const password = document.getElementById('adminPassword').value;
    const strengthEl = document.getElementById('passwordStrength');

    if (!password) {
        strengthEl.innerHTML = '';
        return;
    }

    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    const colors = ['#EF4444', '#F59E0B', '#F59E0B', '#10B981', '#059669'];
    const labels = ['Weak', 'Fair', 'Fair', 'Strong', 'Very Strong'];
    const width = (score / 5) * 100;

    strengthEl.innerHTML = `<div class="strength-bar" style="width:${width}%;background:${colors[score-1] || colors[0]}"></div>`;
}

// Toggle password visibility
function togglePassword(btn) {
    const input = btn.previousElementSibling;
    const icon = btn.querySelector('i');

    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Registration form submission
function handleRegistration(e) {
    e.preventDefault();

    if (!document.getElementById('agreeTerms').checked) {
        alert('Please agree to the Terms of Service and Privacy Policy');
        return;
    }

    // Collect form data
    const formData = {
        full_name: document.getElementById('fullName').value.trim(),
        email: document.getElementById('email').value.trim(),
        phone: document.getElementById('phone').value.trim(),
        company: document.getElementById('company').value.trim(),
        domain_name: document.getElementById('domainName').value.trim(),
        admin_username: document.getElementById('adminUsername').value.trim(),
        admin_password: document.getElementById('adminPassword').value,
        plan: document.querySelector('input[name="plan"]:checked').value
    };

    // Show processing state
    showProcessing(formData);
}

function showProcessing(formData) {
    // Hide form steps and show processing
    document.querySelectorAll('.form-step-content').forEach(el => el.classList.remove('active'));
    document.querySelector('.form-steps').style.display = 'none';
    document.querySelector('.register-form h2').textContent = 'Creating Your PBX';

    const processing = document.getElementById('stepProcessing');
    processing.style.display = 'block';
    processing.classList.add('active');

    const log = document.getElementById('progressLog');

    // Log progress
    addLog(log, 'Validating registration details...', 'info');

    setTimeout(() => {
        addLog(log, 'Connecting to provisioning server...', 'info');

        // Send registration request
        const body = new URLSearchParams();
        body.append('action', 'register');
        for (const [key, val] of Object.entries(formData)) {
            body.append(key, val);
        }

        fetch(CONFIG.apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                addLog(log, 'Domain created successfully!', 'success');
                addLog(log, 'Extensions provisioned: ' + (data.extensions_count || 'N/A'), 'success');
                addLog(log, 'Admin user created.', 'success');
                addLog(log, 'Setup complete!', 'success');

                setTimeout(() => showSuccess(formData, data), 1500);
            } else {
                addLog(log, 'Error: ' + (data.message || 'Registration failed'), 'error');
                if (data.details) {
                    addLog(log, 'Details: ' + data.details, 'error');
                }
                setTimeout(() => showError(data.message || 'Registration failed. Please try again.'), 1500);
            }
        })
        .catch(err => {
            addLog(log, 'Connection error: ' + err.message, 'error');
            setTimeout(() => showError('Could not connect to the provisioning server. Please try again later.'), 1500);
        });
    }, 800);
}

function addLog(container, message, type) {
    const entry = document.createElement('div');
    entry.className = 'log-entry ' + (type || '');
    const timestamp = new Date().toLocaleTimeString();
    entry.textContent = `[${timestamp}] ${message}`;
    container.appendChild(entry);
    container.scrollTop = container.scrollHeight;
}

function showSuccess(formData, response) {
    document.getElementById('stepProcessing').style.display = 'none';
    document.getElementById('stepProcessing').classList.remove('active');

    const success = document.getElementById('stepSuccess');
    success.style.display = 'block';
    success.classList.add('active');

    document.querySelector('.register-form h2').textContent = '';

    // Fill in the details
    const domain = formData.domain_name;
    const url = 'https://' + domain;

    document.getElementById('resultUrl').textContent = url;
    document.getElementById('resultUsername').textContent = formData.admin_username;
    document.getElementById('resultDomain').textContent = domain;
    document.getElementById('resultIp').textContent = response.server_ip || CONFIG.serverIp;

    const tempUrl = document.getElementById('tempUrl');
    tempUrl.href = CONFIG.tempUrlPattern;
    tempUrl.textContent = CONFIG.tempUrlPattern;

    const loginBtn = document.getElementById('loginBtn');
    loginBtn.href = url;
}

function showError(message) {
    document.getElementById('stepProcessing').style.display = 'none';
    document.getElementById('stepProcessing').classList.remove('active');

    const error = document.getElementById('stepError');
    error.style.display = 'block';
    error.classList.add('active');

    document.getElementById('errorMessage').textContent = message;
}

function resetForm() {
    document.getElementById('stepError').style.display = 'none';
    document.getElementById('stepError').classList.remove('active');

    // Reset to step 1
    currentStep = 1;
    document.querySelector('.form-steps').style.display = 'flex';
    document.querySelector('.register-form h2').textContent = 'Create Your Account';

    // Reset step indicators
    document.querySelectorAll('.form-step-indicator').forEach((ind, i) => {
        ind.classList.remove('active', 'completed');
        ind.querySelector('.step-dot').textContent = i + 1;
        if (i === 0) ind.classList.add('active');
    });

    document.querySelectorAll('.step-line').forEach(line => {
        line.classList.remove('completed');
    });

    document.getElementById('step1').classList.add('active');
    document.getElementById('progressLog').innerHTML = '';
}

// Add shake animation
const shakeStyle = document.createElement('style');
shakeStyle.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-8px); }
        75% { transform: translateX(8px); }
    }
`;
document.head.appendChild(shakeStyle);
