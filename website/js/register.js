/* ========================================
   VOIP@ Cloud - Registration Wizard JS
   ======================================== */

const CONFIG = {
    apiUrl: 'https://mt.voipat.com/app/domain_wizard/domain_wizard_register.php',
    serverIp: 'YOUR_SERVER_IP',
    tempUrlPattern: 'https://mt.voipat.com'
};

const PLANS = {
    starter:    { name: 'Starter',    price: '$29/mo',  extensions: 10,  gateways: 1,  ivrs: 2,  ring_groups: 2  },
    business:   { name: 'Business',   price: '$79/mo',  extensions: 50,  gateways: 5,  ivrs: 10, ring_groups: 10 },
    enterprise: { name: 'Enterprise', price: '$199/mo', extensions: 100, gateways: 10, ivrs: 20, ring_groups: 20 }
};

// ============ TRANSLATION HELPER ============
function getCurrentLanguage() {
    const langItem = document.querySelector('.lang-item.active');
    return langItem ? langItem.dataset.lang : 'en';
}

function t(key) {
    // Get translation for key in current language
    if (typeof translations === 'undefined' || !translations[key]) return key;
    const lang = getCurrentLanguage();
    return translations[key][lang] || translations[key]['en'] || key;
}

const TOTAL_STEPS = 9;
let currentStep = 1;
let installationTypes = {};
let deviceTypes = {};
let fieldHints = {};
let ivrMenus = {}; // Track IVR menus: { 0: {name, options}, 1: {name, options}, ... }
let currentIvrModalTarget = null; // Track which IVR/action we're creating for

// ============ INIT ============
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const plan = params.get('plan');
    if (plan && document.querySelector(`input[name="plan"][value="${plan}"]`)) {
        document.querySelector(`input[name="plan"][value="${plan}"]`).checked = true;
    }
    onPlanChange();

    const domainInput = document.getElementById('domainName');
    if (domainInput) {
        domainInput.addEventListener('blur', checkDomain);
        domainInput.addEventListener('input', () => { document.getElementById('domainCheck').innerHTML = ''; });
    }

    const passwordInput = document.getElementById('adminPassword');
    if (passwordInput) passwordInput.addEventListener('input', updatePasswordStrength);

    updateIvrCount(1);
    
    // Fetch API data
    loadInstallationTypes();
    loadDeviceTypes();
    loadFieldHints();
});

// ============ API DATA LOADING ============
function loadInstallationTypes() {
    fetch(CONFIG.apiUrl + '?action=get_installation_types')
        .then(r => r.json())
        .then(data => {
            installationTypes = data.types || {};
            populateInstallationTypes();
        })
        .catch(err => console.warn('Could not load installation types:', err));
}

function loadDeviceTypes() {
    fetch(CONFIG.apiUrl + '?action=get_device_types')
        .then(r => r.json())
        .then(data => {
            deviceTypes = { devices: data.devices || [], trunks: data.trunks || [] };
            populateDeviceTypes();
        })
        .catch(err => console.warn('Could not load device types:', err));
}

function loadFieldHints() {
    fetch(CONFIG.apiUrl + '?action=get_field_hints')
        .then(r => r.json())
        .then(data => {
            fieldHints = data.hints || {};
            applyFieldHints();
        })
        .catch(err => console.warn('Could not load field hints:', err));
}

function populateInstallationTypes() {
    const container = document.getElementById('installationTypes');
    if (!container) return;
    container.innerHTML = '';
    for (const [key, type] of Object.entries(installationTypes)) {
        const label = document.createElement('label');
        label.className = 'installation-option';
        label.innerHTML = `
            <input type="radio" name="installation_type" value="${key}">
            <div class="installation-option-content">
                <h4><i class="fas fa-${type.icon || 'building'}"></i> ${type.name}</h4>
                <p>${type.description}</p>
                <small>${type.features.join(', ')}</small>
            </div>
        `;
        container.appendChild(label);
    }
}

function populateDeviceTypes() {
    const deviceContainer = document.getElementById('deviceTypes');
    if (deviceContainer) {
        deviceContainer.innerHTML = '';
        deviceTypes.devices?.forEach(device => {
            const label = document.createElement('label');
            label.className = 'device-option';
            label.innerHTML = `
                <input type="checkbox" class="device-checkbox" value="${device.id}">
                <span>${device.name}</span> - <small>${device.description}</small>
            `;
            deviceContainer.appendChild(label);
        });
    }
    
    const trunkContainer = document.getElementById('trunkServices');
    if (trunkContainer) {
        trunkContainer.innerHTML = '';
        deviceTypes.trunks?.forEach(trunk => {
            const label = document.createElement('label');
            label.className = 'trunk-option';
            label.innerHTML = `
                <input type="radio" name="trunk_service" value="${trunk.id}">
                <span>${trunk.name}</span> - <small>${trunk.description}</small>
            `;
            trunkContainer.appendChild(label);
        });
    }
}

function applyFieldHints() {
    for (const [fieldId, hint] of Object.entries(fieldHints)) {
        const input = document.getElementById(fieldId);
        if (input) {
            const wrapper = input.parentElement;
            let hintEl = wrapper.querySelector('.field-hint');
            if (!hintEl) {
                hintEl = document.createElement('small');
                hintEl.className = 'field-hint form-hint';
                wrapper.appendChild(hintEl);
            }
            hintEl.textContent = hint;
        }
    }
}

// ============ PLAN CHANGE ============
function onPlanChange() {
    const plan = getSelectedPlan();
    const p = PLANS[plan];
    if (!p) return;

    // Update extensions slider (step 5)
    const extSlider = document.getElementById('extensionsCount');
    if (extSlider) {
        extSlider.max = p.extensions;
        if (parseInt(extSlider.value) > p.extensions) extSlider.value = p.extensions;
        const maxExtLabel = document.getElementById('maxExtLabel');
        if (maxExtLabel) maxExtLabel.textContent = p.extensions;
        updateRangeDisplay(extSlider, 'extensionsValue');
    }

    // Update ring groups slider (step 5)
    const rgSlider = document.getElementById('ringGroupsCount');
    if (rgSlider) {
        rgSlider.max = p.ring_groups;
        if (parseInt(rgSlider.value) > p.ring_groups) rgSlider.value = p.ring_groups;
        const maxRgLabel = document.getElementById('maxRgLabel');
        if (maxRgLabel) maxRgLabel.textContent = p.ring_groups;
        updateRangeDisplay(rgSlider, 'ringGroupsValue');
    }

    // Update IVR slider (step 6)
    const ivrSlider = document.getElementById('ivrsCount');
    if (ivrSlider) {
        ivrSlider.max = p.ivrs;
        if (parseInt(ivrSlider.value) > p.ivrs) ivrSlider.value = p.ivrs;
        const maxIvrLabel = document.getElementById('maxIvrLabel');
        if (maxIvrLabel) maxIvrLabel.textContent = p.ivrs;
    }

    // Update plan name display
    const planNameStep = document.getElementById('planNameStep4');
    if (planNameStep) planNameStep.textContent = p.name;

    // Show plan summary
    const box = document.getElementById('selectedPlanBox');
    const display = document.getElementById('planDisplay');
    if (box && display) {
        display.innerHTML = `<strong>${p.name}</strong> - ${p.price}<br><small style="color:var(--gray-500)">${p.extensions} Ext, ${p.gateways} GW, ${p.ivrs} IVRs, ${p.ring_groups} RG</small>`;
        box.style.display = 'block';
    }
}

function getSelectedPlan() {
    return document.querySelector('input[name="plan"]:checked')?.value || 'business';
}

// ============ RANGE DISPLAY ============
function updateRangeDisplay(input, labelId) {
    document.getElementById(labelId).textContent = input.value;
}

// ============ IVR CONFIGURATION ============
function updateIvrCount(count) {
    count = parseInt(count);
    document.getElementById('ivrsValue').textContent = count;
    const container = document.getElementById('ivrConfigs');
    const existing = container.querySelectorAll('.ivr-config-card').length;

    if (count > existing) {
        for (let i = existing; i < count; i++) {
            container.appendChild(createIvrCard(i));
        }
    } else if (count < existing) {
        const cards = container.querySelectorAll('.ivr-config-card');
        for (let i = existing - 1; i >= count; i--) {
            cards[i].remove();
        }
    }
}

function createIvrCard(index) {
    const card = document.createElement('div');
    card.className = 'ivr-config-card';
    card.id = `ivrCard${index}`;
    
    // Initialize IVR tracking
    if (!ivrMenus[index]) {
        ivrMenus[index] = { name: `IVR Menu ${index + 1}`, options: [] };
    }
    
    card.innerHTML = `
        <div class="ivr-header">
            <h4><i class="fas fa-diagram-project"></i> IVR Menu ${index + 1}</h4>
        </div>
        <div class="form-group">
            <label data-i18n-html="reg.label.ivrName">${t('reg.label.ivrName')}</label>
            <input type="text" class="ivr-name" placeholder="${t('reg.placeholder.ivrName')}" value="IVR Menu ${index + 1}" 
                onchange="saveIvrName(${index}, this.value)">
        </div>
        <div class="form-group">
            <label data-i18n-html="reg.label.greetingRecording">${t('reg.label.greetingRecording')}</label>
            <div class="ivr-recording-upload" onclick="this.querySelector('input').click()">
                <input type="file" accept="audio/wav,audio/mp3,audio/mpeg,audio/ogg,.wav,.mp3,.ogg"
                    onchange="handleIvrFileSelect(this, ${index})">
                <i class="fas fa-cloud-arrow-up"></i>
                <p class="upload-label" data-i18n="reg.hint.greetingRecording">${t('reg.hint.greetingRecording')}</p>
            </div>
        </div>
        <div class="form-group">
            <label data-i18n-html="reg.label.menuOptions">${t('reg.label.menuOptions')}</label>
            <div class="ivr-options-list" id="ivrOptions${index}">
                <div class="ivr-option-row">
                    <span class="digit-label">1</span>
                    <select class="ivr-opt-action" onchange="onIvrActionChange(${index}, this)">
                        <option value="transfer" data-i18n="reg.option.transferExtension">${t('reg.option.transferExtension')}</option>
                        <option value="ring_group" data-i18n="reg.option.transferRingGroup">${t('reg.option.transferRingGroup')}</option>
                        <option value="voicemail" data-i18n="reg.option.sendVoicemail">${t('reg.option.sendVoicemail')}</option>
                        <option value="ivr" data-i18n="reg.option.goToIvr">${t('reg.option.goToIvr')}</option>
                    </select>
                    <div class="ivr-opt-param-wrapper" data-type="transfer">
                        <input type="text" class="ivr-opt-param" placeholder="${t('reg.placeholder.ivrOptParam')}" data-i18n="reg.placeholder.ivrOptParam">
                    </div>
                    <button type="button" class="remove-btn" onclick="this.closest('.ivr-option-row').remove()"><i class="fas fa-times"></i></button>
                </div>
                <div class="ivr-option-row">
                    <span class="digit-label">2</span>
                    <select class="ivr-opt-action" onchange="onIvrActionChange(${index}, this)">
                        <option value="transfer" data-i18n="reg.option.transferExtension">${t('reg.option.transferExtension')}</option>
                        <option value="ring_group" data-i18n="reg.option.transferRingGroup">${t('reg.option.transferRingGroup')}</option>
                        <option value="voicemail" data-i18n="reg.option.sendVoicemail">${t('reg.option.sendVoicemail')}</option>
                        <option value="ivr" data-i18n="reg.option.goToIvr">${t('reg.option.goToIvr')}</option>
                    </select>
                    <div class="ivr-opt-param-wrapper" data-type="transfer">
                        <input type="text" class="ivr-opt-param" placeholder="${t('reg.placeholder.ivrOptParam')}" data-i18n="reg.placeholder.ivrOptParam">
                    </div>
                    <button type="button" class="remove-btn" onclick="this.closest('.ivr-option-row').remove()"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <button type="button" class="add-ivr-option" onclick="addIvrOption(${index})" data-i18n-html="reg.btn.addIvrOption">
                ${t('reg.btn.addIvrOption')}
            </button>
        </div>
    `;
    return card;
}

function saveIvrName(index, name) {
    ivrMenus[index].name = name;
}

function onIvrActionChange(ivrIndex, selectElement) {
    const action = selectElement.value;
    const wrapper = selectElement.parentElement.querySelector('.ivr-opt-param-wrapper');
    
    if (action === 'ivr') {
        // Build dropdown with existing IVRs
        const existingIvrs = Object.keys(ivrMenus).map(idx => ({
            id: idx,
            name: ivrMenus[idx].name
        }));
        
        let html = `
            <select class="ivr-opt-param" onchange="handleIvrSelection(${ivrIndex}, this)">
                <option value="" data-i18n="reg.option.selectIvr">${t('reg.option.selectIvr')}</option>
        `;
        
        existingIvrs.forEach(ivr => {
            html += `<option value="ivr_${ivr.id}">${ivr.name}</option>`;
        });
        
        html += `
                <option value="ivr_new" class="new-ivr-opt" style="background-color: var(--primary-color); color: white;">
                    ${t('reg.option.createNewIvr')}
                </option>
            </select>
        `;
        
        wrapper.innerHTML = html;
        wrapper.dataset.type = 'ivr';
    } else {
        // Use text input for other actions
        wrapper.innerHTML = `<input type="text" class="ivr-opt-param" placeholder="${t('reg.placeholder.ivrOptParam')}" data-i18n="reg.placeholder.ivrOptParam">`;
        wrapper.dataset.type = action;
    }
}

function handleIvrFileSelect(input, index) {
    const file = input.files[0];
    const uploadDiv = input.closest('.ivr-recording-upload');
    if (file) {
        uploadDiv.querySelector('.upload-label').innerHTML = `<span class="file-name"><i class="fas fa-check-circle"></i> ${file.name}</span>`;
    }
}

function addIvrOption(ivrIndex) {
    const list = document.getElementById('ivrOptions' + ivrIndex);
    const rows = list.querySelectorAll('.ivr-option-row');
    const nextDigit = rows.length + 1;
    if (nextDigit > 9) return;

    const row = document.createElement('div');
    row.className = 'ivr-option-row';
    row.innerHTML = `
        <span class="digit-label">${nextDigit}</span>
        <select class="ivr-opt-action" onchange="onIvrActionChange(${ivrIndex}, this)">
            <option value="transfer" data-i18n="reg.option.transferExtension">${t('reg.option.transferExtension')}</option>
            <option value="ring_group" data-i18n="reg.option.transferRingGroup">${t('reg.option.transferRingGroup')}</option>
            <option value="voicemail" data-i18n="reg.option.sendVoicemail">${t('reg.option.sendVoicemail')}</option>
            <option value="ivr" data-i18n="reg.option.goToIvr">${t('reg.option.goToIvr')}</option>
        </select>
        <div class="ivr-opt-param-wrapper" data-type="transfer">
            <input type="text" class="ivr-opt-param" placeholder="${t('reg.placeholder.ivrOptParam')}" data-i18n="reg.placeholder.ivrOptParam">
        </div>
        <button type="button" class="remove-btn" onclick="this.closest('.ivr-option-row').remove()"><i class="fas fa-times"></i></button>
    `;
    list.appendChild(row);
}

function handleIvrSelection(ivrIndex, selectElement) {
    const value = selectElement.value;
    
    if (value === 'ivr_new') {
        currentIvrModalTarget = { parentIvrIndex: ivrIndex, rowElement: selectElement.closest('.ivr-option-row') };
        openIvrCreateModal();
    } else if (value.startsWith('ivr_')) {
        const targetIvrId = value.split('_')[1];
        selectElement.value = `ivr_${targetIvrId}`;
    }
}

function openIvrCreateModal() {
    // Get max IVR index to offer next new IVR
    const maxIndex = Math.max(...Object.keys(ivrMenus).map(Number));
    const newIvrIndex = maxIndex + 1;
    
    let modalContent = `
        <div class="modal-header">
            <h3>${t('reg.label.createIvr')}</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('ivrCreateModal').style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>${t('reg.label.ivrName')}</label>
                <input type="text" id="newIvrName" class="form-control" placeholder="${t('reg.placeholder.ivrName')}" value="IVR Menu ${newIvrIndex + 1}">
            </div>
            <div class="form-group">
                <label>${t('reg.label.greetingRecording')}</label>
                <div class="ivr-recording-upload" onclick="this.querySelector('input').click()">
                    <input type="file" id="newIvrGreeting" accept="audio/wav,audio/mp3,audio/mpeg,audio/ogg,.wav,.mp3,.ogg" onchange="updateIvrGreetingLabel(this)">
                    <i class="fas fa-cloud-arrow-up"></i>
                    <p class="upload-label" id="newIvrGreetingLabel">${t('reg.hint.greetingRecording')}</p>
                </div>
            </div>
            <div class="form-group">
                <label>${t('reg.label.menuOptions')}</label>
                <div class="ivr-options-list" id="newIvrOptions">
                    <div class="ivr-option-row">
                        <span class="digit-label">1</span>
                        <select class="ivr-opt-action" onchange="onNewIvrActionChange(this)">
                            <option value="transfer">${t('reg.option.transferExtension')}</option>
                            <option value="ring_group">${t('reg.option.transferRingGroup')}</option>
                            <option value="voicemail">${t('reg.option.sendVoicemail')}</option>
                            <option value="ivr">${t('reg.option.goToIvr')}</option>
                        </select>
                        <div class="ivr-opt-param-wrapper" data-type="transfer">
                            <input type="text" class="ivr-opt-param" placeholder="${t('reg.placeholder.ivrOptParam')}">
                        </div>
                        <button type="button" class="remove-btn" onclick="this.closest('.ivr-option-row').remove()"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <button type="button" class="btn btn-small btn-ghost" onclick="addNewIvrOption()">
                    ${t('reg.btn.addIvrOption')}
                </button>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('ivrCreateModal').style.display='none'">
                ${t('reg.btn.cancel')}
            </button>
            <button type="button" class="btn btn-primary" onclick="saveNewIvr(${newIvrIndex})">
                ${t('reg.btn.createIvr')}
            </button>
        </div>
    `;
    
    const modal = document.getElementById('ivrCreateModal');
    if (!modal) {
        const newModal = document.createElement('div');
        newModal.id = 'ivrCreateModal';
        newModal.className = 'modal-overlay ivr-create-modal';
        newModal.onclick = (e) => {
            if (e.target === newModal) newModal.style.display = 'none';
        };
        document.body.appendChild(newModal);
    }
    
    const modalElement = document.getElementById('ivrCreateModal');
    modalElement.innerHTML = `<div class="modal-content">${modalContent}</div>`;
    modalElement.style.display = 'flex';
}

function onNewIvrActionChange(selectElement) {
    const action = selectElement.value;
    const wrapper = selectElement.parentElement.querySelector('.ivr-opt-param-wrapper');
    
    if (action === 'ivr') {
        const existingIvrs = Object.keys(ivrMenus).map(idx => ({
            id: idx,
            name: ivrMenus[idx].name
        }));
        
        let html = `<select class="ivr-opt-param" onchange="handleNewIvrSelection(this)">
            <option value="">${t('reg.option.selectIvr')}</option>`;
        
        existingIvrs.forEach(ivr => {
            html += `<option value="ivr_${ivr.id}">${ivr.name}</option>`;
        });
        
        html += `<option value="ivr_new">${t('reg.option.createNewIvr')}</option></select>`;
        
        wrapper.innerHTML = html;
        wrapper.dataset.type = 'ivr';
    } else {
        wrapper.innerHTML = `<input type="text" class="ivr-opt-param" placeholder="${t('reg.placeholder.ivrOptParam')}">`;
        wrapper.dataset.type = action;
    }
}

function addNewIvrOption() {
    const list = document.getElementById('newIvrOptions');
    const rows = list.querySelectorAll('.ivr-option-row');
    const nextDigit = rows.length + 1;
    if (nextDigit > 9) return;
    
    const row = document.createElement('div');
    row.className = 'ivr-option-row';
    row.innerHTML = `
        <span class="digit-label">${nextDigit}</span>
        <select class="ivr-opt-action" onchange="onNewIvrActionChange(this)">
            <option value="transfer">${t('reg.option.transferExtension')}</option>
            <option value="ring_group">${t('reg.option.transferRingGroup')}</option>
            <option value="voicemail">${t('reg.option.sendVoicemail')}</option>
            <option value="ivr">${t('reg.option.goToIvr')}</option>
        </select>
        <div class="ivr-opt-param-wrapper" data-type="transfer">
            <input type="text" class="ivr-opt-param" placeholder="${t('reg.placeholder.ivrOptParam')}">
        </div>
        <button type="button" class="remove-btn" onclick="this.closest('.ivr-option-row').remove()"><i class="fas fa-times"></i></button>
    `;
    list.appendChild(row);
}

function updateIvrGreetingLabel(input) {
    const file = input.files[0];
    const label = document.getElementById('newIvrGreetingLabel');
    if (file && label) {
        label.innerHTML = `<span class="file-name"><i class="fas fa-check-circle"></i> ${file.name}</span>`;
    }
}

function handleNewIvrSelection(selectElement) {
    const value = selectElement.value;
    if (value === 'ivr_new') {
        // Recursively open another modal - save current modal state first
        closeIvrCreateModal();
        openIvrCreateModal();
    }
}

function saveNewIvr(ivrIndex) {
    const name = document.getElementById('newIvrName')?.value || `IVR Menu ${ivrIndex + 1}`;
    ivrMenus[ivrIndex] = { name: name, options: [] };
    
    // Update the parent IVR's option row with the new IVR
    if (currentIvrModalTarget) {
        const select = currentIvrModalTarget.rowElement.querySelector('.ivr-opt-param');
        if (select && select.tagName === 'SELECT') {
            select.value = `ivr_${ivrIndex}`;
        }
    }
    
    closeIvrCreateModal();
}

function closeIvrCreateModal() {
    const modal = document.getElementById('ivrCreateModal');
    if (modal) modal.style.display = 'none';
    currentIvrModalTarget = null;
}

// ============ GATEWAY TOGGLE ============
function toggleGatewayForm() {
    const checked = document.getElementById('configureGateway').checked;
    document.getElementById('gatewayForm').style.display = checked ? 'block' : 'none';
}

// ============ STEP NAVIGATION ============
function nextStep(step) {
    console.log('nextStep called with step:', step, 'currentStep is:', currentStep);
    
    if (!validateStep(currentStep)) {
        console.log('Validation failed for step', currentStep);
        return;
    }
    
    console.log('Validation passed, proceeding to step', step);

    const indicators = document.querySelectorAll('.form-step-indicator');
    const lines = document.querySelectorAll('.step-line');
    
    console.log('Found', indicators.length, 'indicators and', lines.length, 'lines');

    indicators[currentStep - 1].classList.remove('active');
    indicators[currentStep - 1].classList.add('completed');
    indicators[currentStep - 1].querySelector('.step-dot').innerHTML = '<i class="fas fa-check" style="font-size:0.75rem"></i>';

    if (currentStep - 1 < lines.length) lines[currentStep - 1].classList.add('completed');

    document.getElementById('step' + currentStep).classList.remove('active');
    document.getElementById('step' + step).classList.add('active');
    indicators[step - 1].classList.add('active');

    currentStep = step;
    console.log('Moved to step', step);

    if (step === 9) buildReviewTable();
    updateConfigSummary();
}

function prevStep(step) {
    const indicators = document.querySelectorAll('.form-step-indicator');
    const lines = document.querySelectorAll('.step-line');

    indicators[currentStep - 1].classList.remove('active');
    document.getElementById('step' + currentStep).classList.remove('active');

    indicators[step - 1].classList.remove('completed');
    indicators[step - 1].classList.add('active');
    indicators[step - 1].querySelector('.step-dot').textContent = step;

    if (step - 1 < lines.length) lines[step - 1].classList.remove('completed');

    document.getElementById('step' + step).classList.add('active');
    currentStep = step;
}

// ============ VALIDATION ============
function validateStep(step) {
    try {
        console.log('Validating step', step);
        const stepEl = document.getElementById('step' + step);
        if (!stepEl) {
            console.warn('Step element not found: step' + step);
            return true; // Allow progression if step element doesn't exist
        }

        const inputs = stepEl.querySelectorAll('input[required], select[required]');
        console.log('Found', inputs.length, 'required inputs in step', step);
        let valid = true;

        inputs.forEach((input, idx) => {
            // Skip hidden inputs
            if (input.offsetParent === null) {
                console.log('  Input', idx, 'is hidden, skipping');
                return;
            }
            
            console.log('  Input', idx, ':', input.id, '=', input.value, 'type:', input.type);
            
            if (!input.value.trim()) {
                console.log('    -> EMPTY, marking invalid');
                input.style.borderColor = '#EF4444';
                input.addEventListener('input', function handler() {
                    this.style.borderColor = '';
                    this.removeEventListener('input', handler);
                });
                valid = false;
            } else {
                console.log('    -> Has value, OK');
            }
        });

        if (step === 2) {
            const domain = document.getElementById('domainName')?.value.trim();
            if (domain && !isValidDomain(domain)) {
                console.log('Domain validation failed:', domain);
                const domainCheck = document.getElementById('domainCheck');
                if (domainCheck) domainCheck.innerHTML = '<span style="color:#EF4444"><i class="fas fa-times-circle"></i> Invalid domain format</span>';
                valid = false;
            }
            const pass = document.getElementById('adminPassword')?.value;
            const confirm = document.getElementById('confirmPassword')?.value;
            if (pass !== confirm) {
                console.log('Password mismatch');
                const confirmEl = document.getElementById('confirmPassword');
                if (confirmEl) confirmEl.style.borderColor = '#EF4444';
                alert('Passwords do not match');
                valid = false;
            }
            if (pass && pass.length < 8) {
                console.log('Password too short');
                const passEl = document.getElementById('adminPassword');
                if (passEl) passEl.style.borderColor = '#EF4444';
                alert('Password must be at least 8 characters');
                valid = false;
            }
        }

        console.log('Step', step, 'validation result:', valid);
        
        if (!valid) {
            const form = document.querySelector('.register-form');
            if (form) {
                form.style.animation = 'shake 0.5s ease';
                setTimeout(() => form.style.animation = '', 500);
            }
        }
        return valid;
    } catch (err) {
        console.error('Validation error:', err);
        return true; // Allow progression on error
    }
}

function isValidDomain(domain) {
    return /^([a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/.test(domain);
}

// ============ DOMAIN CHECK ============
function checkDomain() {
    const domain = document.getElementById('domainName').value.trim();
    const checkEl = document.getElementById('domainCheck');
    if (!domain) { checkEl.innerHTML = ''; return; }

    // Check for voipat.com subdomain requirement
    if (!domain.endsWith('.voipat.com')) {
        checkEl.innerHTML = '<span style="color:#F59E0B"><i class="fas fa-info-circle"></i> Your domain will be registered as <strong>' + domain + '.voipat.com</strong></span>';
    }

    if (!isValidDomain(domain)) { 
        checkEl.innerHTML += ' <span style="color:#EF4444"><i class="fas fa-times-circle"></i> Invalid domain format</span>';
        return; 
    }

    checkEl.innerHTML = '<span class="checking"><i class="fas fa-spinner fa-spin"></i> Checking...</span>';
    fetch(CONFIG.apiUrl + '?action=check_domain&domain=' + encodeURIComponent(domain))
        .then(r => r.json())
        .then(data => {
            let msg = data.available
                ? '<span class="available"><i class="fas fa-check-circle"></i> Available!</span>'
                : '<span class="taken"><i class="fas fa-times-circle"></i> Already in use</span>';
            if (!domain.endsWith('.voipat.com')) {
                msg = '<span style="color:#F59E0B"><i class="fas fa-info-circle"></i> Will be registered as <strong>' + domain + '.voipat.com</strong></span> ' + msg;
            }
            checkEl.innerHTML = msg;
        })
        .catch(() => {
            checkEl.innerHTML = '<span style="color:var(--gray-400)"><i class="fas fa-info-circle"></i> Will be verified during registration</span>';
        });
}

// ============ PASSWORD STRENGTH ============
function updatePasswordStrength() {
    const password = document.getElementById('adminPassword').value;
    const el = document.getElementById('passwordStrength');
    if (!password) { el.innerHTML = ''; return; }
    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    const colors = ['#EF4444', '#F59E0B', '#F59E0B', '#10B981', '#059669'];
    el.innerHTML = `<div class="strength-bar" style="width:${(score/5)*100}%;background:${colors[score-1]||colors[0]}"></div>`;
}

function togglePassword(btn) {
    const input = btn.previousElementSibling;
    const icon = btn.querySelector('i');
    input.type = input.type === 'password' ? 'text' : 'password';
    icon.className = input.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}

// ============ CONFIG SUMMARY ============
function updateConfigSummary() {
    const plan = PLANS[getSelectedPlan()];
    const box = document.getElementById('configSummary');
    const content = document.getElementById('configSummaryContent');
    if (currentStep < 4) { box.style.display = 'none'; return; }

    box.style.display = 'block';
    const ext = document.getElementById('extensionsCount').value;
    const rg = document.getElementById('ringGroupsCount').value;
    const ivr = document.getElementById('ivrsCount').value;
    const hasGw = document.getElementById('configureGateway')?.checked;

    content.innerHTML = `
        <div class="config-summary-item"><span>Plan</span><strong>${plan.name}</strong></div>
        <div class="config-summary-item"><span>Extensions</span><strong>${ext}</strong></div>
        <div class="config-summary-item"><span>Ring Groups</span><strong>${rg}</strong></div>
        <div class="config-summary-item"><span>IVR Menus</span><strong>${ivr}</strong></div>
        <div class="config-summary-item"><span>Gateway</span><strong>${hasGw ? 'Yes' : 'Skip'}</strong></div>
    `;
}

// ============ REVIEW TABLE ============
function buildReviewTable() {
    const plan = PLANS[getSelectedPlan()];
    const domain = document.getElementById('domainName').value;
    const ext = document.getElementById('extensionsCount').value;
    const extStart = document.getElementById('extensionStart').value;
    const rg = document.getElementById('ringGroupsCount').value;
    const ivr = document.getElementById('ivrsCount').value;
    const hasGw = document.getElementById('configureGateway')?.checked;
    const installType = document.querySelector('input[name="installation_type"]:checked')?.value || 'company_pbx';
    const devices = Array.from(document.querySelectorAll('.device-checkbox:checked')).map(cb => cb.value);
    const trunk = document.querySelector('input[name="trunk_service"]:checked')?.value || '-';

    let html = `
        <div class="review-section">
            <h4>Account</h4>
            <div class="review-row"><span class="review-label">Name</span><span class="review-value">${document.getElementById('fullName').value}</span></div>
            <div class="review-row"><span class="review-label">Email</span><span class="review-value">${document.getElementById('email').value}</span></div>
            <div class="review-row"><span class="review-label">Company</span><span class="review-value">${document.getElementById('company').value || '-'}</span></div>
        </div>
        <div class="review-section">
            <h4>Domain & Plan</h4>
            <div class="review-row"><span class="review-label">Domain</span><span class="review-value">${domain}</span></div>
            <div class="review-row"><span class="review-label">Installation Type</span><span class="review-value">${installationTypes[installType]?.name || installType}</span></div>
            <div class="review-row"><span class="review-label">Admin User</span><span class="review-value">${document.getElementById('adminUsername').value}</span></div>
            <div class="review-row"><span class="review-label">Plan</span><span class="review-value">${plan.name} (${plan.price})</span></div>
        </div>
        <div class="review-section">
            <h4>Configuration</h4>
            <div class="review-row"><span class="review-label">Extensions</span><span class="review-value">${ext} (starting at ${extStart})</span></div>
            <div class="review-row"><span class="review-label">Ring Groups</span><span class="review-value">${rg}</span></div>
            <div class="review-row"><span class="review-label">IVR Menus</span><span class="review-value">${ivr}</span></div>
        </div>`;

    // Device details
    if (devices.length > 0) {
        html += '<div class="review-section"><h4>Devices</h4>';
        devices.forEach(deviceId => {
            const device = deviceTypes.devices?.find(d => d.id === deviceId);
            if (device) {
                html += `<div class="review-row"><span class="review-label">${device.name}</span><span class="review-value">${device.description}</span></div>`;
            }
        });
        html += `<div class="review-row"><span class="review-label">Trunk Service</span><span class="review-value">${deviceTypes.trunks?.find(t => t.id === trunk)?.name || trunk}</span></div>`;
        html += '</div>';
    }

    // IVR details
    const ivrCards = document.querySelectorAll('.ivr-config-card');
    if (ivrCards.length > 0) {
        html += '<div class="review-section"><h4>IVR Details</h4>';
        ivrCards.forEach((card, i) => {
            const name = card.querySelector('.ivr-name').value || `IVR ${i+1}`;
            const file = card.querySelector('input[type="file"]').files[0];
            const options = card.querySelectorAll('.ivr-option-row');
            html += `<div class="review-row"><span class="review-label">${name}</span><span class="review-value">${file ? file.name : 'No recording'}, ${options.length} options</span></div>`;
        });
        html += '</div>';
    }

    // Gateway
    if (hasGw) {
        html += `
        <div class="review-section">
            <h4>SIP Gateway</h4>
            <div class="review-row"><span class="review-label">Name</span><span class="review-value">${document.getElementById('gwName').value || '-'}</span></div>
            <div class="review-row"><span class="review-label">Proxy</span><span class="review-value">${document.getElementById('gwProxy').value || '-'}</span></div>
            <div class="review-row"><span class="review-label">Username</span><span class="review-value">${document.getElementById('gwUsername').value || '-'}</span></div>
        </div>`;
    }

    document.getElementById('reviewTable').innerHTML = html;
}

// ============ COLLECT FORM DATA ============
function collectFormData() {
    const data = {
        full_name: document.getElementById('fullName').value.trim(),
        email: document.getElementById('email').value.trim(),
        phone: document.getElementById('phone').value.trim(),
        company: document.getElementById('company').value.trim(),
        domain_name: document.getElementById('domainName').value.trim(),
        admin_username: document.getElementById('adminUsername').value.trim(),
        admin_password: document.getElementById('adminPassword').value,
        plan: getSelectedPlan(),
        installation_type: document.querySelector('input[name="installation_type"]:checked')?.value || 'company_pbx',
        extensions_count: document.getElementById('extensionsCount').value,
        extension_start: document.getElementById('extensionStart').value,
        ring_groups_count: document.getElementById('ringGroupsCount').value,
        ivrs_count: document.getElementById('ivrsCount').value,
    };

    // IVR configs
    const ivrCards = document.querySelectorAll('.ivr-config-card');
    data.ivr_configs = [];
    data.ivr_chart_config = {};
    ivrCards.forEach((card, i) => {
        const ivr = {
            name: card.querySelector('.ivr-name').value || `IVR Menu ${i+1}`,
            options: []
        };
        card.querySelectorAll('.ivr-option-row').forEach(row => {
            ivr.options.push({
                digit: row.querySelector('.digit-label').textContent,
                action: row.querySelector('.ivr-opt-action').value,
                param: row.querySelector('.ivr-opt-param').value
            });
        });
        data.ivr_configs.push(ivr);
    });

    // Device configuration
    data.device_config = Array.from(document.querySelectorAll('.device-checkbox:checked')).map(cb => cb.value);
    
    // Trunk service
    data.trunk_data = { service: document.querySelector('input[name="trunk_service"]:checked')?.value || '' };

    // Gateway / Outbound config
    if (document.getElementById('configureGateway')?.checked) {
        data.outbound_config = {
            gateway_name: document.getElementById('gwName').value.trim(),
            proxy: document.getElementById('gwProxy').value.trim(),
            username: document.getElementById('gwUsername').value.trim(),
            password: document.getElementById('gwPassword').value.trim(),
            register: document.getElementById('gwRegister').value,
            transport: document.getElementById('gwTransport').value,
            caller_id: document.getElementById('gwCallerId').value.trim()
        };
        data.gateway = data.outbound_config; // Keep for backward compatibility
    }

    return data;
}

// ============ REGISTRATION ============
function handleRegistration(e) {
    e.preventDefault();
    if (!document.getElementById('agreeTerms').checked) {
        alert('Please agree to the Terms of Service and Privacy Policy');
        return;
    }
    const formData = collectFormData();
    showProcessing(formData);
}

function showProcessing(formData) {
    document.querySelectorAll('.form-step-content').forEach(el => el.classList.remove('active'));
    document.getElementById('formSteps').style.display = 'none';
    document.querySelector('.register-form h2').textContent = 'Creating Your PBX';

    const processing = document.getElementById('stepProcessing');
    processing.style.display = 'block';
    processing.classList.add('active');
    const log = document.getElementById('progressLog');

    addLog(log, 'Validating registration details...', 'info');

    setTimeout(() => {
        addLog(log, 'Connecting to provisioning server...', 'info');

        // Build form data for POST
        const body = new FormData();
        body.append('action', 'register');
        body.append('full_name', formData.full_name);
        body.append('email', formData.email);
        body.append('phone', formData.phone);
        body.append('company', formData.company);
        body.append('domain_name', formData.domain_name);
        body.append('admin_username', formData.admin_username);
        body.append('admin_password', formData.admin_password);
        body.append('plan', formData.plan);
        body.append('installation_type', formData.installation_type);
        body.append('extensions_count', formData.extensions_count);
        body.append('extension_start', formData.extension_start);
        body.append('ring_groups_count', formData.ring_groups_count);
        body.append('ivrs_count', formData.ivrs_count);
        body.append('ivr_configs', JSON.stringify(formData.ivr_configs));
        body.append('ivr_chart_config', JSON.stringify(formData.ivr_chart_config));
        body.append('device_config', JSON.stringify(formData.device_config));
        body.append('trunk_data', JSON.stringify(formData.trunk_data));

        if (formData.outbound_config) {
            body.append('outbound_config', JSON.stringify(formData.outbound_config));
        }
        if (formData.gateway) {
            body.append('gateway', JSON.stringify(formData.gateway));
        }

        // Add IVR recording files
        const ivrCards = document.querySelectorAll('.ivr-config-card');
        ivrCards.forEach((card, i) => {
            const fileInput = card.querySelector('input[type="file"]');
            if (fileInput.files[0]) {
                body.append('ivr_recording_' + i, fileInput.files[0]);
            }
        });

        fetch(CONFIG.apiUrl, { method: 'POST', body: body })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    addLog(log, 'Domain created successfully!', 'success');
                    addLog(log, 'Extensions provisioned: ' + (data.extensions_count || formData.extensions_count), 'success');
                    if (formData.ivrs_count > 0) addLog(log, 'IVR menus configured: ' + formData.ivrs_count, 'success');
                    if (formData.gateway) addLog(log, 'SIP gateway configured.', 'success');
                    addLog(log, 'Admin user created.', 'success');
                    addLog(log, 'Setup complete!', 'success');
                    setTimeout(() => showSuccess(formData, data), 1500);
                } else {
                    addLog(log, 'Error: ' + (data.message || 'Registration failed'), 'error');
                    if (data.details) addLog(log, 'Details: ' + data.details, 'error');
                    setTimeout(() => showError(data.message || 'Registration failed.'), 1500);
                }
            })
            .catch(err => {
                addLog(log, 'Connection error: ' + err.message, 'error');
                setTimeout(() => showError('Could not connect to the server.'), 1500);
            });
    }, 800);
}

function addLog(container, message, type) {
    const entry = document.createElement('div');
    entry.className = 'log-entry ' + (type || '');
    entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
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

    const url = 'https://' + formData.domain_name;
    document.getElementById('resultUrl').textContent = url;
    document.getElementById('resultUsername').textContent = formData.admin_username;
    document.getElementById('resultDomain').textContent = formData.domain_name;
    document.getElementById('resultIp').textContent = response.server_ip || CONFIG.serverIp;
    document.getElementById('tempUrl').href = CONFIG.tempUrlPattern;
    document.getElementById('tempUrl').textContent = CONFIG.tempUrlPattern;
    document.getElementById('loginBtn').href = url;
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
    currentStep = 1;
    document.getElementById('formSteps').style.display = 'flex';
    document.querySelector('.register-form h2').textContent = 'Create Your Account';

    document.querySelectorAll('.form-step-indicator').forEach((ind, i) => {
        ind.classList.remove('active', 'completed');
        ind.querySelector('.step-dot').textContent = i + 1;
        if (i === 0) ind.classList.add('active');
    });
    document.querySelectorAll('.step-line').forEach(l => l.classList.remove('completed'));
    document.getElementById('step1').classList.add('active');
    document.getElementById('progressLog').innerHTML = '';
}

// Shake animation
const s = document.createElement('style');
s.textContent = '@keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-8px)}75%{transform:translateX(8px)}}';
document.head.appendChild(s);
