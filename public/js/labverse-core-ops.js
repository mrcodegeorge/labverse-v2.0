/**
 * ============================================================================
 * LABVERSE — Phase 4: Core Operations (Patients, Test Orders, Payments, Samples)
 * ============================================================================
 */
window.LabverseCoreOps = (function () {
    const UI = window.LabverseUI;

    // Internal module state
    const state = {
        patientsPage: 1,
        patientsSearch: '',
        patientsGender: '',
        selectedPatientForOrder: null,
        catalogueCategories: [],
        catalogueTests: [],
        orderCart: [], // array of test objects
        orderCategoryFilter: '',
        orderSearchQuery: '',
        orderDiscount: 0,
        orderDiscountReason: '',
        ordersPage: 1,
        ordersStatusFilter: '',
        ordersPaymentFilter: '',
        ordersPriorityFilter: '',
        ordersSearch: '',
        samplesPage: 1,
        samplesStatusFilter: '',
        samplesSearch: '',
        paymentsPage: 1,
        paymentsMethodFilter: '',
        paymentsSearch: '',
    };

    // =========================================================================
    // 6. PATIENT MANAGEMENT MODULE
    // =========================================================================
    async function renderPatients(page = 1) {
        state.patientsPage = page;
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        const canManage = window.LabverseApp.hasPermission('manage_patients');
        const canOrder = window.LabverseApp.hasPermission('place_order');

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Patients',
            title: 'Patient Registry & Clinical History',
            description: 'Search registered patients, verify demographics, inspect diagnostic history, or register a new patient.',
            actionsHtml: canManage ? `
                <button type="button" class="btn btn-primary" onclick="LabverseCoreOps.openPatientModal()">
                    <i data-lucide="user-plus" style="width:16px;height:16px;"></i> Register New Patient
                </button>
            ` : '',
        }) + `
            <div class="lv-filter-bar">
                <form id="lv-patient-filter-form" class="row g-2 align-items-end" onsubmit="event.preventDefault(); LabverseCoreOps.applyPatientFilters();">
                    <div class="col-12 col-md-5">
                        <label for="patient-search-input" class="form-label">Search Patient</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i data-lucide="search" style="width:15px;height:15px;"></i></span>
                            <input type="search" id="patient-search-input" name="search" class="form-control" placeholder="Search by Patient ID (LV-2026-...), name, phone, or NHIS..." value="${UI.esc(state.patientsSearch)}">
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="patient-gender-filter" class="form-label">Gender</label>
                        <select id="patient-gender-filter" name="gender" class="form-select form-select-sm">
                            <option value="">All Genders</option>
                            <option value="Male" ${state.patientsGender === 'Male' ? 'selected' : ''}>Male</option>
                            <option value="Female" ${state.patientsGender === 'Female' ? 'selected' : ''}>Female</option>
                            <option value="Other" ${state.patientsGender === 'Other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                            <i data-lucide="filter" style="width:14px;height:14px;"></i> Search & Filter
                        </button>
                        <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseCoreOps.resetPatientFilters()">Reset</button>
                    </div>
                </form>
            </div>
            <div id="lv-patients-table-wrap" class="lv-card">
                ${UI.loadingSpinner('Loading patient directory...')}
            </div>
        `;
        UI.refreshIcons();

        try {
            const params = new URLSearchParams({
                page: state.patientsPage,
                search: state.patientsSearch,
                gender: state.patientsGender,
            });
            const data = await UI.api(`/patients?${params.toString()}`);
            const rows = data.data || [];
            const tableWrap = document.getElementById('lv-patients-table-wrap');
            if (!tableWrap) return;

            if (rows.length === 0) {
                tableWrap.innerHTML = UI.emptyState({
                    icon: 'users',
                    title: 'No matching patient records',
                    message: 'No patients matched your search criteria. You can clear filters or register a new patient.',
                    actionHtml: canManage ? `<button type="button" class="btn btn-primary btn-sm" onclick="LabverseCoreOps.openPatientModal()"><i data-lucide="user-plus" style="width:14px;height:14px;"></i> Register New Patient</button>` : '',
                });
                UI.refreshIcons();
                return;
            }

            tableWrap.innerHTML = `
                <div class="lv-table-container">
                    <table class="lv-table">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Patient Name</th>
                                <th>Gender / Age</th>
                                <th>Phone Number</th>
                                <th>NHIS / Blood Group</th>
                                <th>Registration Date</th>
                                <th>Branch</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map(p => `
                                <tr>
                                    <td><span class="font-mono fw-semibold text-primary">${UI.esc(p.patient_code)}</span></td>
                                    <td>
                                        <div class="fw-semibold text-dark">${UI.esc(p.full_name)}</div>
                                        <div class="small text-secondary">${UI.esc(p.email || p.address || 'No address on file')}</div>
                                    </td>
                                    <td>
                                        <span class="fw-medium">${UI.esc(p.gender)}</span>
                                        <span class="text-secondary small d-block">${p.age !== null ? `${p.age} yrs` : '—'} (DOB: ${UI.formatDate(p.date_of_birth)})</span>
                                    </td>
                                    <td><span class="font-mono">${UI.esc(p.phone)}</span></td>
                                    <td>
                                        <span class="small d-block">${p.nhis_number ? UI.esc(p.nhis_number) : '<span class="text-muted">Private / Cash</span>'}</span>
                                        ${p.blood_group ? `<span class="lv-badge lv-badge-info mt-1">Blood: ${UI.esc(p.blood_group)}${p.genotype ? ' / ' + UI.esc(p.genotype) : ''}</span>` : ''}
                                    </td>
                                    <td>${UI.formatDate(p.created_at)}</td>
                                    <td><span class="lv-badge lv-badge-neutral">${UI.esc(p.branch?.name || 'Main Lab')}</span></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseCoreOps.viewPatientProfile(${p.id})" title="View Profile & History">
                                                <i data-lucide="eye" style="width:14px;height:14px;"></i> Profile
                                            </button>
                                            ${canManage ? `
                                                <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseCoreOps.openPatientModal(${p.id})" title="Edit Patient">
                                                    <i data-lucide="edit-3" style="width:14px;height:14px;"></i> Edit
                                                </button>
                                            ` : ''}
                                            ${canOrder ? `
                                                <button type="button" class="btn btn-secondary-teal btn-sm" onclick="LabverseCoreOps.startOrderForPatient(${p.id})">
                                                    <i data-lucide="flask-conical" style="width:14px;height:14px;"></i> Order Test
                                                </button>
                                            ` : ''}
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ${UI.pagination(data, 'LabverseCoreOps.renderPatients')}
            `;
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function applyPatientFilters() {
        state.patientsSearch = document.getElementById('patient-search-input')?.value || '';
        state.patientsGender = document.getElementById('patient-gender-filter')?.value || '';
        renderPatients(1);
    }

    function resetPatientFilters() {
        state.patientsSearch = '';
        state.patientsGender = '';
        renderPatients(1);
    }

    async function openPatientModal(patientId = null) {
        let patient = null;
        if (patientId) {
            try {
                const res = await UI.api(`/patients/${patientId}`);
                patient = res.patient;
            } catch (err) {
                UI.toast(err.message, 'error');
                return;
            }
        }

        const isEdit = !!patient;
        const gender = patient?.gender || 'Female';

        const html = `
            <form id="lv-patient-form" method="POST" action="/api/patients" onsubmit="event.preventDefault(); LabverseCoreOps.submitPatientForm(${isEdit ? patient.id : 'null'});">
                <div class="p-3 border-bottom bg-light d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="mb-0 fw-bold">${isEdit ? `Edit Patient — ${UI.esc(patient.patient_code)}` : 'Register New Laboratory Patient'}</h5>
                        <div class="small text-secondary">Complete demographic, contact, and clinical baseline details.</div>
                    </div>
                    <button type="button" class="btn-close" aria-label="Close dialog" onclick="LabverseApp.closeDialog()"></button>
                </div>
                <div class="p-4" style="max-height:74vh;overflow-y:auto;">
                    <div id="lv-duplicate-warning-banner" class="alert alert-warning d-none mb-3 py-2 small" role="alert" aria-live="polite"></div>

                    <fieldset class="mb-4">
                        <legend class="fs-6 fw-bold text-primary border-bottom pb-2 mb-3">1. Personal Information</legend>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="pat-first-name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" id="pat-first-name" name="first_name" class="form-control" autocomplete="given-name" required value="${UI.esc(patient?.first_name || '')}" onblur="LabverseCoreOps.checkDuplicatePatient()">
                            </div>
                            <div class="col-md-4">
                                <label for="pat-last-name" class="form-label">Last Name / Surname <span class="text-danger">*</span></label>
                                <input type="text" id="pat-last-name" name="last_name" class="form-control" autocomplete="family-name" required value="${UI.esc(patient?.last_name || '')}" onblur="LabverseCoreOps.checkDuplicatePatient()">
                            </div>
                            <div class="col-md-4">
                                <label for="pat-other-names" class="form-label">Other / Middle Names</label>
                                <input type="text" id="pat-other-names" name="other_names" class="form-control" autocomplete="additional-name" value="${UI.esc(patient?.other_names || '')}">
                            </div>
                            <div class="col-md-4">
                                <label for="pat-dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" id="pat-dob" name="date_of_birth" class="form-control" autocomplete="bday" required max="${new Date().toISOString().split('T')[0]}" value="${UI.esc(patient?.date_of_birth || '')}" onchange="LabverseCoreOps.checkDuplicatePatient()">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label d-block">Biological Sex / Gender <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3 pt-1" role="radiogroup" aria-label="Patient Gender">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="gender" id="gender-female" value="Female" ${gender === 'Female' ? 'checked' : ''} required>
                                        <label class="form-check-label" for="gender-female">Female</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="gender" id="gender-male" value="Male" ${gender === 'Male' ? 'checked' : ''}>
                                        <label class="form-check-label" for="gender-male">Male</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="gender" id="gender-other" value="Other" ${gender === 'Other' ? 'checked' : ''}>
                                        <label class="form-check-label" for="gender-other">Other</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend class="fs-6 fw-bold text-primary border-bottom pb-2 mb-3">2. Contact Information</legend>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="pat-phone" class="form-label">Primary Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" id="pat-phone" name="phone" class="form-control" autocomplete="tel" required placeholder="+233 244 000 000" value="${UI.esc(patient?.phone || '')}" onblur="LabverseCoreOps.checkDuplicatePatient()">
                            </div>
                            <div class="col-md-4">
                                <label for="pat-email" class="form-label">Email Address</label>
                                <input type="email" id="pat-email" name="email" class="form-control" autocomplete="email" placeholder="patient@example.com" value="${UI.esc(patient?.email || '')}">
                            </div>
                            <div class="col-md-4">
                                <label for="pat-city" class="form-label">City / Town</label>
                                <input type="text" id="pat-city" name="city" class="form-control" autocomplete="address-level2" placeholder="Accra" value="${UI.esc(patient?.city || 'Accra')}">
                            </div>
                            <div class="col-12">
                                <label for="pat-address" class="form-label">Residential Address / Landmark</label>
                                <input type="text" id="pat-address" name="address" class="form-control" autocomplete="street-address" placeholder="House No., Street, Suburb" value="${UI.esc(patient?.address || '')}">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="fs-6 fw-bold text-primary border-bottom pb-2 mb-3">3. Clinical Demographics & Emergency Contact</legend>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="pat-nhis" class="form-label">NHIS / Insurance Number</label>
                                <input type="text" id="pat-nhis" name="nhis_number" class="form-control" placeholder="NHIS-XXXXXXXX" value="${UI.esc(patient?.nhis_number || '')}">
                            </div>
                            <div class="col-md-4">
                                <label for="pat-blood" class="form-label">Blood Group</label>
                                <select id="pat-blood" name="blood_group" class="form-select">
                                    <option value="">Unknown / Not Tested</option>
                                    ${['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'].map(bg => `<option value="${bg}" ${patient?.blood_group === bg ? 'selected' : ''}>${bg}</option>`).join('')}
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="pat-genotype" class="form-label">HB Genotype</label>
                                <select id="pat-genotype" name="genotype" class="form-select">
                                    <option value="">Unknown</option>
                                    ${['AA', 'AS', 'SS', 'SC', 'AC'].map(gt => `<option value="${gt}" ${patient?.genotype === gt ? 'selected' : ''}>${gt}</option>`).join('')}
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="pat-em-name" class="form-label">Emergency Contact Name</label>
                                <input type="text" id="pat-em-name" name="emergency_contact_name" class="form-control" value="${UI.esc(patient?.emergency_contact_name || '')}">
                            </div>
                            <div class="col-md-4">
                                <label for="pat-em-phone" class="form-label">Emergency Contact Phone</label>
                                <input type="tel" id="pat-em-phone" name="emergency_contact_phone" class="form-control" value="${UI.esc(patient?.emergency_contact_phone || '')}">
                            </div>
                            <div class="col-md-4">
                                <label for="pat-em-rel" class="form-label">Relationship</label>
                                <input type="text" id="pat-em-rel" name="emergency_contact_relation" class="form-control" placeholder="Spouse, Parent, Sibling..." value="${UI.esc(patient?.emergency_contact_relation || '')}">
                            </div>
                        </div>
                    </fieldset>
                </div>
                <div class="p-3 border-top bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDialog()">Cancel</button>
                    <div class="d-flex gap-2">
                        <button type="submit" id="btn-save-patient" class="btn btn-primary">
                            <i data-lucide="Check" style="width:16px;height:16px;"></i> ${isEdit ? 'Save Patient Changes' : 'Save Patient'}
                        </button>
                        ${!isEdit && window.LabverseApp.hasPermission('place_order') ? `
                            <button type="button" id="btn-save-and-order" class="btn btn-secondary-teal" onclick="LabverseCoreOps.submitPatientForm(null, true)">
                                <i data-lucide="flask-conical" style="width:16px;height:16px;"></i> Save & Proceed to Test Order
                            </button>
                        ` : ''}
                    </div>
                </div>
            </form>
        `;

        window.LabverseApp.openDialog(html, true);
    }

    async function checkDuplicatePatient() {
        const firstName = document.getElementById('pat-first-name')?.value?.trim() || '';
        const lastName = document.getElementById('pat-last-name')?.value?.trim() || '';
        const phone = document.getElementById('pat-phone')?.value?.trim() || '';
        const dob = document.getElementById('pat-dob')?.value || '';
        const banner = document.getElementById('lv-duplicate-warning-banner');
        if (!banner || (!phone && (!firstName || !lastName))) return;

        try {
            const params = new URLSearchParams({ first_name: firstName, last_name: lastName, phone, date_of_birth: dob });
            const res = await UI.api(`/patients/check-duplicate?${params.toString()}`);
            if (res.matches && res.matches.length > 0) {
                const m = res.matches[0];
                banner.classList.remove('d-none');
                banner.innerHTML = `
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <strong>Potential Duplicate Patient Detected:</strong>
                            ${UI.esc(m.full_name)} (<span class="font-mono">${UI.esc(m.patient_code)}</span>, Phone: ${UI.esc(m.phone)}, DOB: ${UI.formatDate(m.date_of_birth)})
                        </div>
                        <button type="button" class="btn btn-warning btn-sm" onclick="LabverseApp.closeDialog(); LabverseCoreOps.viewPatientProfile(${m.id})">
                            Open Existing Record
                        </button>
                    </div>
                `;
            } else {
                banner.classList.add('d-none');
            }
        } catch (_) {}
    }

    async function submitPatientForm(patientId = null, proceedToOrder = false) {
        const form = document.getElementById('lv-patient-form');
        if (!form) return;

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        const saveBtn = document.getElementById('btn-save-patient');
        if (saveBtn) saveBtn.disabled = true;

        try {
            const res = await UI.api(patientId ? `/patients/${patientId}` : '/patients', {
                method: patientId ? 'PUT' : 'POST',
                body: payload,
            });
            UI.toast(res.message || 'Patient saved successfully.', 'success');
            window.LabverseApp.closeDialog();

            if (proceedToOrder && res.patient) {
                state.selectedPatientForOrder = res.patient;
                window.LabverseApp.navigate('orders', { mode: 'new', patientId: res.patient.id });
            } else {
                renderPatients(state.patientsPage);
            }
        } catch (err) {
            UI.toast(err.message, 'error');
            if (saveBtn) saveBtn.disabled = false;
        }
    }

    async function viewPatientProfile(patientId) {
        window.LabverseApp.openDrawer(UI.loadingSpinner('Loading patient clinical dossier...'));
        try {
            const res = await UI.api(`/patients/${patientId}`);
            const p = res.patient;
            const orders = p.orders || [];
            const reports = p.reports || [];
            const payments = p.payments || [];

            const html = `
                <div class="lv-drawer-header">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="font-mono fw-bold text-primary fs-6">${UI.esc(p.patient_code)}</span>
                            <span class="lv-badge lv-badge-neutral">${UI.esc(p.branch?.name || 'Main Lab')}</span>
                        </div>
                        <h5 class="mb-0 fw-bold mt-1">${UI.esc(p.full_name)}</h5>
                    </div>
                    <div class="d-flex gap-2">
                        ${window.LabverseApp.hasPermission('place_order') ? `
                            <button type="button" class="btn btn-secondary-teal btn-sm" onclick="LabverseApp.closeDrawer(); LabverseCoreOps.startOrderForPatient(${p.id})">
                                <i data-lucide="flask-conical" style="width:14px;height:14px;"></i> Place Test Order
                            </button>
                        ` : ''}
                        <button type="button" class="btn-close" aria-label="Close patient drawer" onclick="LabverseApp.closeDrawer()"></button>
                    </div>
                </div>
                <div class="lv-drawer-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 rounded border bg-light h-100">
                                <div class="small text-secondary text-uppercase fw-semibold mb-2">Demographics & Contact</div>
                                <div class="small mb-1"><strong>Gender / Age:</strong> ${UI.esc(p.gender)} • ${p.age} yrs (DOB: ${UI.formatDate(p.date_of_birth)})</div>
                                <div class="small mb-1"><strong>Phone:</strong> <span class="font-mono">${UI.esc(p.phone)}</span></div>
                                <div class="small mb-1"><strong>Email:</strong> ${UI.esc(p.email || '—')}</div>
                                <div class="small"><strong>Address:</strong> ${UI.esc(p.address || '—')} ${p.city ? ', ' + UI.esc(p.city) : ''}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded border bg-light h-100">
                                <div class="small text-secondary text-uppercase fw-semibold mb-2">Clinical Baseline & Emergency</div>
                                <div class="small mb-1"><strong>Blood Group / Genotype:</strong> ${UI.esc(p.blood_group || 'N/A')} / ${UI.esc(p.genotype || 'N/A')}</div>
                                <div class="small mb-1"><strong>NHIS Number:</strong> <span class="font-mono">${UI.esc(p.nhis_number || 'Private / Cash')}</span></div>
                                <div class="small mb-1"><strong>Emergency Contact:</strong> ${UI.esc(p.emergency_contact_name || '—')} (${UI.esc(p.emergency_contact_relation || '—')})</div>
                                <div class="small"><strong>Emergency Phone:</strong> <span class="font-mono">${UI.esc(p.emergency_contact_phone || '—')}</span></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2 d-flex align-items-center gap-2">
                        <i data-lucide="clipboard-list" style="width:16px;height:16px;color:var(--lv-primary);"></i>
                        Previous Laboratory Orders (${orders.length})
                    </h6>
                    <div class="lv-card mb-4">
                        ${orders.length === 0 ? `<div class="p-3 text-secondary small">No laboratory orders recorded for this patient yet.</div>` : `
                            <div class="lv-table-container">
                                <table class="lv-table">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Date</th>
                                            <th>Ordered Tests</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${orders.map(o => `
                                            <tr>
                                                <td class="font-mono fw-semibold">${UI.esc(o.order_number)}</td>
                                                <td>${UI.formatDateTime(o.created_at)}</td>
                                                <td>${(o.items || []).map(i => `<span class="lv-badge lv-badge-neutral me-1">${UI.esc(i.test?.code || i.test?.name)}</span>`).join('')}</td>
                                                <td>${UI.statusBadge(o.status)}</td>
                                                <td>${UI.statusBadge(o.payment_status)} <span class="small font-mono d-block">${UI.formatCurrency(o.total_amount)}</span></td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseCoreOps.viewOrderDetails(${o.id})">Inspect</button>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `}
                    </div>

                    <h6 class="fw-bold mb-2 d-flex align-items-center gap-2">
                        <i data-lucide="file-check-2" style="width:16px;height:16px;color:var(--lv-secondary);"></i>
                        Released Medical Reports (${reports.length})
                    </h6>
                    <div class="lv-card mb-3">
                        ${reports.length === 0 ? `<div class="p-3 text-secondary small">No released medical reports yet.</div>` : `
                            <div class="lv-table-container">
                                <table class="lv-table">
                                    <thead>
                                        <tr>
                                            <th>Report #</th>
                                            <th>Order #</th>
                                            <th>Released At</th>
                                            <th>Approved By</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${reports.map(r => `
                                            <tr>
                                                <td class="font-mono fw-semibold text-primary">${UI.esc(r.report_number)}</td>
                                                <td class="font-mono">${UI.esc(r.order?.order_number || '—')}</td>
                                                <td>${UI.formatDateTime(r.released_at)}</td>
                                                <td>${UI.esc(r.approved_by_user?.name || 'Supervisor')}</td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="LabverseLabOps.openReportPreview(${r.id})">
                                                        <i data-lucide="printer" style="width:13px;height:13px;"></i> View / Print Report
                                                    </button>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `}
                    </div>
                </div>
            `;
            window.LabverseApp.openDrawer(html);
        } catch (err) {
            UI.toast(err.message, 'error');
            window.LabverseApp.closeDrawer();
        }
    }

    async function startOrderForPatient(patientId) {
        try {
            const res = await UI.api(`/patients/${patientId}`);
            state.selectedPatientForOrder = res.patient;
            window.LabverseApp.navigate('orders', { mode: 'new' });
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 7. TEST ORDER MANAGEMENT MODULE
    // =========================================================================
    async function renderOrders(options = {}) {
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        if (options.mode === 'new') {
            return renderNewOrderComposer();
        }

        if (options.page) state.ordersPage = options.page;
        const canPlace = window.LabverseApp.hasPermission('place_order');

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Laboratory Orders',
            title: 'Test Order Requisitions',
            description: 'Manage diagnostic test requisitions, track specimen collection progress, and monitor order turnaround.',
            actionsHtml: canPlace ? `
                <button type="button" class="btn btn-primary" onclick="LabverseCoreOps.renderNewOrderComposer()">
                    <i data-lucide="plus-circle" style="width:16px;height:16px;"></i> New Test Order
                </button>
            ` : '',
        }) + `
            <div class="lv-filter-bar">
                <form class="row g-2 align-items-end" onsubmit="event.preventDefault(); LabverseCoreOps.applyOrderFilters();">
                    <div class="col-12 col-md-4">
                        <label for="order-search-input" class="form-label">Search Order or Patient</label>
                        <input type="search" id="order-search-input" class="form-control form-control-sm" placeholder="Order # (ORD-...), Patient name or ID..." value="${UI.esc(state.ordersSearch)}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="order-status-filter" class="form-label">Order Status</label>
                        <select id="order-status-filter" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="ordered" ${state.ordersStatusFilter === 'ordered' ? 'selected' : ''}>Ordered</option>
                            <option value="sample_collected" ${state.ordersStatusFilter === 'sample_collected' ? 'selected' : ''}>Sample Collected</option>
                            <option value="processing" ${state.ordersStatusFilter === 'processing' ? 'selected' : ''}>Processing</option>
                            <option value="awaiting_approval" ${state.ordersStatusFilter === 'awaiting_approval' ? 'selected' : ''}>Awaiting Approval</option>
                            <option value="completed" ${state.ordersStatusFilter === 'completed' ? 'selected' : ''}>Completed</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="order-pay-filter" class="form-label">Payment Status</label>
                        <select id="order-pay-filter" class="form-select form-select-sm">
                            <option value="">All Payments</option>
                            <option value="paid" ${state.ordersPaymentFilter === 'paid' ? 'selected' : ''}>Paid</option>
                            <option value="partial" ${state.ordersPaymentFilter === 'partial' ? 'selected' : ''}>Part Payment</option>
                            <option value="unpaid" ${state.ordersPaymentFilter === 'unpaid' ? 'selected' : ''}>Unpaid</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="order-priority-filter" class="form-label">Priority</label>
                        <select id="order-priority-filter" class="form-select form-select-sm">
                            <option value="">All Priorities</option>
                            <option value="routine" ${state.ordersPriorityFilter === 'routine' ? 'selected' : ''}>Routine</option>
                            <option value="urgent" ${state.ordersPriorityFilter === 'urgent' ? 'selected' : ''}>Urgent</option>
                            <option value="stat" ${state.ordersPriorityFilter === 'stat' ? 'selected' : ''}>STAT / Emergency</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                        <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseCoreOps.resetOrderFilters()">Reset</button>
                    </div>
                </form>
            </div>
            <div id="lv-orders-table-wrap" class="lv-card">
                ${UI.loadingSpinner('Loading laboratory orders...')}
            </div>
        `;
        UI.refreshIcons();

        try {
            const params = new URLSearchParams({
                page: state.ordersPage,
                search: state.ordersSearch,
                status: state.ordersStatusFilter,
                payment_status: state.ordersPaymentFilter,
                priority: state.ordersPriorityFilter,
            });
            const data = await UI.api(`/orders?${params.toString()}`);
            const rows = data.data || [];
            const wrap = document.getElementById('lv-orders-table-wrap');
            if (!wrap) return;

            if (rows.length === 0) {
                wrap.innerHTML = UI.emptyState({
                    icon: 'clipboard-list',
                    title: 'No laboratory orders found',
                    message: 'Create a new test order requisition or adjust the active filters.',
                });
                UI.refreshIcons();
                return;
            }

            wrap.innerHTML = `
                <div class="lv-table-container">
                    <table class="lv-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Patient</th>
                                <th>Priority</th>
                                <th>Ordered Tests</th>
                                <th>Samples</th>
                                <th>Billing / Payment</th>
                                <th>Workflow Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map(o => `
                                <tr>
                                    <td>
                                        <span class="font-mono fw-semibold text-primary">${UI.esc(o.order_number)}</span>
                                        <span class="small text-secondary d-block">${UI.formatDateTime(o.created_at)}</span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">${UI.esc(o.patient?.full_name || '—')}</div>
                                        <div class="small text-secondary font-mono">${UI.esc(o.patient?.patient_code || '')} • ${UI.esc(o.patient?.gender || '')}</div>
                                    </td>
                                    <td>${UI.statusBadge(o.priority)}</td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1" style="max-width:260px;">
                                            ${(o.items || []).map(it => `<span class="lv-badge lv-badge-neutral" title="${UI.esc(it.test?.name)}">${UI.esc(it.test?.code)}</span>`).join('')}
                                        </div>
                                    </td>
                                    <td>
                                        ${(o.samples || []).map(s => `
                                            <div class="small font-mono">${UI.esc(s.barcode)} ${UI.statusBadge(s.status)}</div>
                                        `).join('')}
                                    </td>
                                    <td>
                                        <div class="fw-semibold font-mono">${UI.formatCurrency(o.total_amount)}</div>
                                        <div class="d-flex align-items-center gap-1 mt-1">
                                            ${UI.statusBadge(o.payment_status)}
                                            ${o.balance_due > 0 ? `<span class="small text-danger font-mono">(Due: ${UI.formatCurrency(o.balance_due)})</span>` : ''}
                                        </div>
                                    </td>
                                    <td>${UI.statusBadge(o.status)}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseCoreOps.viewOrderDetails(${o.id})">
                                                <i data-lucide="eye" style="width:14px;height:14px;"></i> Details
                                            </button>
                                            ${o.report ? `
                                                <button type="button" class="btn btn-primary btn-sm" onclick="LabverseLabOps.openReportPreview(${o.report.id})">
                                                    <i data-lucide="file-text" style="width:14px;height:14px;"></i> Report
                                                </button>
                                            ` : ''}
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ${UI.pagination(data, 'LabverseCoreOps.renderOrdersPage')}
            `;
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function renderOrdersPage(p) {
        renderOrders({ page: p });
    }

    function applyOrderFilters() {
        state.ordersSearch = document.getElementById('order-search-input')?.value || '';
        state.ordersStatusFilter = document.getElementById('order-status-filter')?.value || '';
        state.ordersPaymentFilter = document.getElementById('order-pay-filter')?.value || '';
        state.ordersPriorityFilter = document.getElementById('order-priority-filter')?.value || '';
        renderOrders({ page: 1 });
    }

    function resetOrderFilters() {
        state.ordersSearch = '';
        state.ordersStatusFilter = '';
        state.ordersPaymentFilter = '';
        state.ordersPriorityFilter = '';
        renderOrders({ page: 1 });
    }

    // Interactive Test Order Composer
    async function renderNewOrderComposer() {
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Orders / New Test Requisition',
            title: 'Create Laboratory Test Order',
            description: 'Select a patient, browse the diagnostic test catalogue by clinical department, calculate charges, and submit the order.',
            actionsHtml: `
                <button type="button" class="btn btn-light-surface" onclick="LabverseCoreOps.renderOrders()">
                    <i data-lucide="arrow-left" style="width:15px;height:15px;"></i> Back to Orders List
                </button>
            `,
        }) + `<div id="lv-order-composer-body">${UI.loadingSpinner('Loading diagnostic test catalogue...')}</div>`;
        UI.refreshIcons();

        try {
            const [catRes, testRes] = await Promise.all([
                UI.api('/catalogue/categories'),
                UI.api('/catalogue/tests?active_only=1'),
            ]);
            state.catalogueCategories = catRes.categories || [];
            state.catalogueTests = testRes.tests || [];
            renderComposerLayout();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function renderComposerLayout() {
        const wrap = document.getElementById('lv-order-composer-body');
        if (!wrap) return;

        const p = state.selectedPatientForOrder;

        wrap.innerHTML = `
            <div class="row g-4">
                <!-- Left Column: Patient Selection + Categorized Test Catalogue -->
                <div class="col-12 col-lg-7">
                    <div class="lv-card mb-3">
                        <div class="lv-card-header">
                            <h2 class="lv-card-title"><i data-lucide="user-check" style="width:17px;height:17px;color:var(--lv-primary);"></i> Step 1: Select Patient</h2>
                            <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseCoreOps.openPatientModal()">
                                <i data-lucide="user-plus" style="width:14px;height:14px;"></i> New Patient
                            </button>
                        </div>
                        <div class="lv-card-body">
                            <div class="input-group mb-2">
                                <span class="input-group-text bg-white"><i data-lucide="search" style="width:15px;height:15px;"></i></span>
                                <input type="search" id="order-patient-lookup" class="form-control" placeholder="Type patient name, Patient ID (LV-2026-...), or phone number..." oninput="LabverseCoreOps.searchPatientForOrder(this.value)">
                            </div>
                            <div id="order-patient-search-results" class="mb-2"></div>
                            <div id="order-selected-patient-card">
                                ${p ? `
                                    <div class="p-3 rounded border d-flex align-items-center justify-content-between" style="background:var(--lv-primary-soft);border-color:#B6D0EB !important;">
                                        <div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="font-mono fw-bold text-primary">${UI.esc(p.patient_code)}</span>
                                                <span class="fw-bold text-dark">${UI.esc(p.full_name)}</span>
                                            </div>
                                            <div class="small text-secondary mt-1">
                                                ${UI.esc(p.gender)} • ${p.age ?? '—'} yrs • Phone: <span class="font-mono">${UI.esc(p.phone)}</span> • NHIS: ${UI.esc(p.nhis_number || 'Cash/Private')}
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseCoreOps.clearSelectedOrderPatient()">Change</button>
                                    </div>
                                ` : `<div class="text-secondary small">No patient selected yet. Search above to select a patient.</div>`}
                            </div>
                        </div>
                    </div>

                    <div class="lv-card">
                        <div class="lv-card-header flex-wrap">
                            <h2 class="lv-card-title"><i data-lucide="microscope" style="width:17px;height:17px;color:var(--lv-secondary);"></i> Step 2: Select Diagnostic Tests</h2>
                            <div class="w-100 mt-2">
                                <input type="search" id="order-catalogue-search" class="form-control form-control-sm" placeholder="Filter tests by code (e.g. FBC, RFT, LFT, MP), test name, or sample type..." value="${UI.esc(state.orderSearchQuery)}" oninput="LabverseCoreOps.filterComposerCatalogue(this.value)">
                            </div>
                            <div class="d-flex flex-wrap gap-1 mt-2" id="order-category-pills">
                                <button type="button" class="btn btn-sm ${!state.orderCategoryFilter ? 'btn-primary' : 'btn-light-surface'}" onclick="LabverseCoreOps.setOrderCategory('')">All Categories</button>
                                ${state.catalogueCategories.map(cat => `
                                    <button type="button" class="btn btn-sm ${String(state.orderCategoryFilter) === String(cat.id) ? 'btn-primary' : 'btn-light-surface'}" onclick="LabverseCoreOps.setOrderCategory('${cat.id}')">
                                        ${UI.esc(cat.name)}
                                    </button>
                                `).join('')}
                            </div>
                        </div>
                        <div class="lv-card-body p-0" id="order-catalogue-list" style="max-height:460px;overflow-y:auto;">
                            ${renderCatalogueRowsHtml()}
                        </div>
                    </div>
                </div>

                <!-- Right Column: Requisition Summary, Pricing, Discounts & Payment -->
                <div class="col-12 col-lg-5">
                    <form id="lv-submit-order-form" class="lv-card" onsubmit="event.preventDefault(); LabverseCoreOps.submitNewOrder();">
                        <div class="lv-card-header">
                            <h2 class="lv-card-title"><i data-lucide="receipt" style="width:17px;height:17px;color:var(--lv-primary);"></i> Step 3: Order Summary & Billing</h2>
                            <span class="lv-badge lv-badge-info" id="order-cart-count-badge">${state.orderCart.length} Test(s)</span>
                        </div>
                        <div class="lv-card-body">
                            <div id="order-cart-items-wrap" class="mb-3">
                                ${renderOrderCartHtml()}
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label for="ord-priority" class="form-label">Clinical Priority <span class="text-danger">*</span></label>
                                    <select id="ord-priority" name="priority" class="form-select form-select-sm" required>
                                        <option value="routine">Routine</option>
                                        <option value="urgent">Urgent</option>
                                        <option value="stat">STAT / Emergency</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="ord-doctor" class="form-label">Referring Clinician / Doctor</label>
                                    <input type="text" id="ord-doctor" name="referring_doctor" class="form-control form-control-sm" placeholder="Dr. Name / Hospital">
                                </div>
                                <div class="col-12">
                                    <label for="ord-notes" class="form-label">Clinical Notes / Provisional Diagnosis</label>
                                    <input type="text" id="ord-notes" name="clinical_notes" class="form-control form-control-sm" placeholder="Relevant clinical indication or symptoms...">
                                </div>
                            </div>

                            <div class="p-3 rounded border bg-light mb-3">
                                <div class="row g-2 align-items-center mb-2">
                                    <div class="col-6">
                                        <label for="ord-discount" class="form-label mb-0">Authorized Discount (GH₵)</label>
                                    </div>
                                    <div class="col-6">
                                        <input type="number" step="0.01" min="0" id="ord-discount" class="form-control form-control-sm text-end font-mono" value="${state.orderDiscount}" oninput="LabverseCoreOps.updateOrderDiscount(this.value)">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <input type="text" id="ord-discount-reason" class="form-control form-control-sm" placeholder="Discount reason (e.g. NHIS Co-pay / Staff / Corporate)" value="${UI.esc(state.orderDiscountReason)}" oninput="LabverseCoreOps.updateDiscountReason(this.value)">
                                </div>
                                <div id="order-totals-breakdown">
                                    ${renderOrderTotalsHtml()}
                                </div>
                            </div>

                            <fieldset class="border rounded p-3 mb-3">
                                <legend class="small fw-bold text-uppercase text-secondary px-1" style="float:none;width:auto;">Initial Payment Collection (Optional)</legend>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label for="ord-pay-amount" class="form-label">Amount Paid Now (GH₵)</label>
                                        <input type="number" step="0.01" min="0" id="ord-pay-amount" name="initial_payment_amount" class="form-control form-control-sm font-mono" value="0.00">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ord-pay-method" class="form-label">Payment Method</label>
                                        <select id="ord-pay-method" name="payment_method" class="form-select form-select-sm">
                                            <option value="cash">Cash (GHS)</option>
                                            <option value="mtn_momo">MTN Mobile Money</option>
                                            <option value="telecel_cash">Telecel Cash</option>
                                            <option value="at_money">AT Money</option>
                                            <option value="card">POS / Card</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="nhis">NHIS / Corporate</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label for="ord-pay-ref" class="form-label">Transaction Reference / MoMo ID</label>
                                        <input type="text" id="ord-pay-ref" name="transaction_reference" class="form-control form-control-sm font-mono" placeholder="e.g. MOMO-GH-948102...">
                                    </div>
                                </div>
                            </fieldset>

                            <button type="submit" id="btn-submit-lab-order" class="btn btn-primary w-100 py-2">
                                <i data-lucide="check-circle-2" style="width:17px;height:17px;"></i> Submit Laboratory Order & Generate Sample Barcodes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        UI.refreshIcons();
    }

    function renderCatalogueRowsHtml() {
        const q = (state.orderSearchQuery || '').toLowerCase();
        const catId = state.orderCategoryFilter;

        const filtered = state.catalogueTests.filter(t => {
            if (catId && String(t.category_id) !== String(catId)) return false;
            if (q) {
                return (
                    t.code.toLowerCase().includes(q) ||
                    t.name.toLowerCase().includes(q) ||
                    (t.sample_type || '').toLowerCase().includes(q)
                );
            }
            return true;
        });

        if (filtered.length === 0) {
            return `<div class="p-4 text-center text-secondary small">No tests match your filter criteria.</div>`;
        }

        return `
            <table class="lv-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Test Name & Specimen</th>
                        <th>TAT</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Select</th>
                    </tr>
                </thead>
                <tbody>
                    ${filtered.map(t => {
                        const inCart = state.orderCart.some(c => c.id === t.id);
                        return `
                            <tr>
                                <td class="font-mono fw-semibold text-primary">${UI.esc(t.code)}</td>
                                <td>
                                    <div class="fw-semibold">${UI.esc(t.name)}</div>
                                    <div class="small text-secondary">${UI.esc(t.category?.name || '')} • Specimen: <strong>${UI.esc(t.sample_type)}</strong></div>
                                </td>
                                <td class="small">${t.turnaround_hours}h</td>
                                <td class="text-end font-mono fw-semibold">${UI.formatCurrency(t.price)}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm ${inCart ? 'btn-danger' : 'btn-outline-primary'}" onclick="LabverseCoreOps.toggleTestInCart(${t.id})">
                                        ${inCart ? 'Remove' : '+ Add'}
                                    </button>
                                </td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        `;
    }

    function renderOrderCartHtml() {
        if (state.orderCart.length === 0) {
            return `<div class="p-3 rounded border text-center text-secondary small bg-light">No diagnostic tests selected yet. Click <strong>+ Add</strong> from the catalogue on the left.</div>`;
        }

        return `
            <div class="border rounded overflow-hidden">
                ${state.orderCart.map(t => `
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-white">
                        <div>
                            <span class="font-mono fw-bold text-primary me-1">${UI.esc(t.code)}</span>
                            <span class="fw-medium small">${UI.esc(t.name)}</span>
                            <span class="d-block text-secondary" style="font-size:0.74rem;">Specimen: ${UI.esc(t.sample_type)}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="font-mono fw-semibold small">${UI.formatCurrency(t.price)}</span>
                            <button type="button" class="btn btn-sm text-danger p-1" aria-label="Remove test" onclick="LabverseCoreOps.toggleTestInCart(${t.id})">
                                <i data-lucide="x" style="width:14px;height:14px;"></i>
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function renderOrderTotalsHtml() {
        const subtotal = state.orderCart.reduce((acc, t) => acc + Number(t.price || 0), 0);
        const discount = Math.min(Number(state.orderDiscount || 0), subtotal);
        const total = Math.max(subtotal - discount, 0);

        return `
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-secondary">Tests Subtotal:</span>
                <span class="font-mono fw-semibold">${UI.formatCurrency(subtotal)}</span>
            </div>
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-secondary">Discount Applied:</span>
                <span class="font-mono text-danger">- ${UI.formatCurrency(discount)}</span>
            </div>
            <div class="d-flex justify-content-between border-top pt-2 mt-1">
                <span class="fw-bold">Total Payable:</span>
                <span class="font-mono fw-bold fs-6 text-primary">${UI.formatCurrency(total)}</span>
            </div>
        `;
    }

    async function searchPatientForOrder(query) {
        const box = document.getElementById('order-patient-search-results');
        if (!box) return;
        if (!query || query.trim().length < 2) {
            box.innerHTML = '';
            return;
        }

        try {
            const res = await UI.api(`/patients?search=${encodeURIComponent(query.trim())}&per_page=5`);
            const list = res.data || [];
            if (list.length === 0) {
                box.innerHTML = `<div class="p-2 border rounded bg-light small text-secondary">No patients found matching "${UI.esc(query)}".</div>`;
                return;
            }
            box.innerHTML = `
                <div class="list-group shadow-sm">
                    ${list.map(p => `
                        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2" onclick='LabverseCoreOps.selectPatientForOrder(${JSON.stringify(p).replace(/'/g, "&#39;")})'>
                            <div>
                                <span class="font-mono fw-bold text-primary me-2">${UI.esc(p.patient_code)}</span>
                                <span class="fw-semibold">${UI.esc(p.full_name)}</span>
                                <span class="small text-secondary ms-2">(${UI.esc(p.gender)}, ${p.age ?? '—'} yrs)</span>
                            </div>
                            <span class="font-mono small text-secondary">${UI.esc(p.phone)}</span>
                        </button>
                    `).join('')}
                </div>
            `;
        } catch (_) {}
    }

    function selectPatientForOrder(patientObj) {
        state.selectedPatientForOrder = patientObj;
        const box = document.getElementById('order-patient-search-results');
        if (box) box.innerHTML = '';
        renderComposerLayout();
    }

    function clearSelectedOrderPatient() {
        state.selectedPatientForOrder = null;
        renderComposerLayout();
    }

    function setOrderCategory(catId) {
        state.orderCategoryFilter = catId;
        renderComposerLayout();
    }

    function filterComposerCatalogue(q) {
        state.orderSearchQuery = q;
        const listEl = document.getElementById('order-catalogue-list');
        if (listEl) listEl.innerHTML = renderCatalogueRowsHtml();
    }

    function toggleTestInCart(testId) {
        const idx = state.orderCart.findIndex(c => c.id === testId);
        if (idx >= 0) {
            state.orderCart.splice(idx, 1);
        } else {
            const found = state.catalogueTests.find(t => t.id === testId);
            if (found) state.orderCart.push(found);
        }

        const listEl = document.getElementById('order-catalogue-list');
        const cartEl = document.getElementById('order-cart-items-wrap');
        const totalsEl = document.getElementById('order-totals-breakdown');
        const badgeEl = document.getElementById('order-cart-count-badge');

        if (listEl) listEl.innerHTML = renderCatalogueRowsHtml();
        if (cartEl) cartEl.innerHTML = renderOrderCartHtml();
        if (totalsEl) totalsEl.innerHTML = renderOrderTotalsHtml();
        if (badgeEl) badgeEl.textContent = `${state.orderCart.length} Test(s)`;

        const subtotal = state.orderCart.reduce((acc, t) => acc + Number(t.price || 0), 0);
        const total = Math.max(subtotal - Number(state.orderDiscount || 0), 0);
        const payInput = document.getElementById('ord-pay-amount');
        if (payInput) payInput.value = total.toFixed(2);

        UI.refreshIcons();
    }

    function updateOrderDiscount(val) {
        state.orderDiscount = Math.max(Number(val || 0), 0);
        const totalsEl = document.getElementById('order-totals-breakdown');
        if (totalsEl) totalsEl.innerHTML = renderOrderTotalsHtml();
    }

    function updateDiscountReason(val) {
        state.orderDiscountReason = val;
    }

    async function submitNewOrder() {
        if (!state.selectedPatientForOrder) {
            UI.toast('Please select a patient for this laboratory order first.', 'warning');
            return;
        }
        if (state.orderCart.length === 0) {
            UI.toast('Please select at least one diagnostic test from the catalogue.', 'warning');
            return;
        }

        const btn = document.getElementById('btn-submit-lab-order');
        if (btn) btn.disabled = true;

        const payload = {
            patient_id: state.selectedPatientForOrder.id,
            priority: document.getElementById('ord-priority')?.value || 'routine',
            referring_doctor: document.getElementById('ord-doctor')?.value || null,
            clinical_notes: document.getElementById('ord-notes')?.value || null,
            test_ids: state.orderCart.map(t => t.id),
            discount_amount: Number(state.orderDiscount || 0),
            discount_reason: state.orderDiscountReason || null,
            initial_payment_amount: Number(document.getElementById('ord-pay-amount')?.value || 0),
            payment_method: document.getElementById('ord-pay-method')?.value || 'cash',
            transaction_reference: document.getElementById('ord-pay-ref')?.value || null,
        };

        try {
            const res = await UI.api('/orders', {
                method: 'POST',
                body: payload,
            });
            UI.toast(res.message || 'Order created successfully.', 'success');
            state.orderCart = [];
            state.orderDiscount = 0;
            state.orderDiscountReason = '';
            state.selectedPatientForOrder = null;

            renderOrders({ page: 1 });
            if (res.order) {
                viewOrderDetails(res.order.id);
            }
        } catch (err) {
            UI.toast(err.message, 'error');
            if (btn) btn.disabled = false;
        }
    }

    async function viewOrderDetails(orderId) {
        window.LabverseApp.openDrawer(UI.loadingSpinner('Loading order requisition & specimen details...'));
        try {
            const res = await UI.api(`/orders/${orderId}`);
            const o = res.order;

            const html = `
                <div class="lv-drawer-header">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="font-mono fw-bold text-primary fs-6">${UI.esc(o.order_number)}</span>
                            ${UI.statusBadge(o.priority)}
                            ${UI.statusBadge(o.status)}
                        </div>
                        <h5 class="mb-0 fw-bold mt-1">${UI.esc(o.patient?.full_name)} (<span class="font-mono">${UI.esc(o.patient?.patient_code)}</span>)</h5>
                    </div>
                    <button type="button" class="btn-close" aria-label="Close order drawer" onclick="LabverseApp.closeDrawer()"></button>
                </div>
                <div class="lv-drawer-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light h-100 small">
                                <div class="fw-bold text-uppercase text-secondary mb-2">Clinical Requisition Info</div>
                                <div class="mb-1"><strong>Patient:</strong> ${UI.esc(o.patient?.full_name)} (${UI.esc(o.patient?.gender)}, ${o.patient?.age ?? '—'} yrs)</div>
                                <div class="mb-1"><strong>Referring Clinician:</strong> ${UI.esc(o.referring_doctor || 'Walk-in / Self-Referred')}</div>
                                <div class="mb-1"><strong>Clinical Notes:</strong> ${UI.esc(o.clinical_notes || 'None recorded')}</div>
                                <div><strong>Branch:</strong> ${UI.esc(o.branch?.name || 'Main Lab')}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light h-100 small">
                                <div class="fw-bold text-uppercase text-secondary mb-2">Financial & Billing Summary</div>
                                <div class="d-flex justify-content-between mb-1"><span>Subtotal:</span><span class="font-mono">${UI.formatCurrency(o.subtotal)}</span></div>
                                <div class="d-flex justify-content-between mb-1"><span>Discount:</span><span class="font-mono text-danger">- ${UI.formatCurrency(o.discount_amount)}</span></div>
                                <div class="d-flex justify-content-between mb-1 fw-bold"><span>Total Amount:</span><span class="font-mono">${UI.formatCurrency(o.total_amount)}</span></div>
                                <div class="d-flex justify-content-between mb-1 text-success"><span>Amount Paid:</span><span class="font-mono">${UI.formatCurrency(o.amount_paid)}</span></div>
                                <div class="d-flex justify-content-between fw-bold ${o.balance_due > 0 ? 'text-danger' : 'text-success'}">
                                    <span>Balance Due:</span><span class="font-mono">${UI.formatCurrency(o.balance_due)}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2">Generated Specimen Containers & Barcodes</h6>
                    <div class="row g-2 mb-4">
                        ${(o.samples || []).map(s => `
                            <div class="col-md-6">
                                <div class="p-3 border rounded bg-white d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-semibold">${UI.esc(s.sample_type)}</div>
                                        <div class="small text-secondary">${UI.esc(s.container_type || '')}</div>
                                        <div class="mt-1">${UI.statusBadge(s.status)}</div>
                                    </div>
                                    <div class="lv-barcode-box">
                                        <div class="fw-bold font-mono">${UI.esc(s.barcode)}</div>
                                        <div class="small text-secondary" style="font-size:0.7rem;letter-spacing:0;">${UI.esc(s.sample_code)}</div>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>

                    <h6 class="fw-bold mb-2">Ordered Diagnostic Tests (${(o.items || []).length})</h6>
                    <div class="lv-card mb-3">
                        <div class="lv-table-container">
                            <table class="lv-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Test Name</th>
                                        <th>Sample Barcode</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${(o.items || []).map(item => `
                                        <tr>
                                            <td class="font-mono fw-semibold text-primary">${UI.esc(item.test?.code)}</td>
                                            <td>${UI.esc(item.test?.name)}</td>
                                            <td class="font-mono small">${UI.esc(item.sample?.barcode || '—')}</td>
                                            <td class="font-mono">${UI.formatCurrency(item.price)}</td>
                                            <td>${UI.statusBadge(item.status)}</td>
                                            <td class="text-end">
                                                ${window.LabverseApp.hasPermission('enter_results') && item.status !== 'pending_sample' ? `
                                                    <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseLabOps.openResultEntryModal(${item.id})">
                                                        Results
                                                    </button>
                                                ` : ''}
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="lv-drawer-footer">
                    <button type="button" class="btn btn-light-surface" onclick="window.print()">
                        <i data-lucide="printer" style="width:15px;height:15px;"></i> Print Requisition Slip
                    </button>
                    <div class="d-flex gap-2">
                        ${o.balance_due > 0 && window.LabverseApp.hasPermission('manage_payments') ? `
                            <button type="button" class="btn btn-secondary-teal" onclick="LabverseCoreOps.openRecordPaymentModal(${o.id}, '${UI.esc(o.order_number)}', ${o.balance_due})">
                                <i data-lucide="credit-card" style="width:15px;height:15px;"></i> Record Balance Payment (${UI.formatCurrency(o.balance_due)})
                            </button>
                        ` : ''}
                        ${o.report ? `
                            <button type="button" class="btn btn-primary" onclick="LabverseLabOps.openReportPreview(${o.report.id})">
                                <i data-lucide="file-check-2" style="width:15px;height:15px;"></i> Open Official Report
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
            window.LabverseApp.openDrawer(html);
        } catch (err) {
            UI.toast(err.message, 'error');
            window.LabverseApp.closeDrawer();
        }
    }

    // =========================================================================
    // 8. SAMPLE MANAGEMENT MODULE
    // =========================================================================
    async function renderSamples(page = 1) {
        state.samplesPage = page;
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Sample Collection & Tracking',
            title: 'Specimen Lifecycle & Barcode Management',
            description: 'Scan or enter specimen barcodes, record phlebotomy collection, log laboratory reception, and accept or reject specimens.',
        }) + `
            <div class="row g-3 mb-3" id="lv-sample-kpi-row"></div>
            <div class="lv-filter-bar">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <form onsubmit="event.preventDefault(); LabverseCoreOps.scanSampleBarcode();">
                            <label for="sample-barcode-scanner" class="form-label fw-semibold text-primary">Barcode Scanner / Quick Lookup</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i data-lucide="scan-line" style="width:15px;height:15px;"></i></span>
                                <input type="text" id="sample-barcode-scanner" class="form-control font-mono" placeholder="Scan or type barcode (e.g. LV26110700007)..." autocomplete="off">
                                <button type="submit" class="btn btn-secondary-teal">Scan</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="sample-search-input" class="form-label">Search Samples</label>
                        <input type="search" id="sample-search-input" class="form-control form-control-sm" placeholder="Sample code, Order #, or Patient name..." value="${UI.esc(state.samplesSearch)}" oninput="LabverseCoreOps.updateSampleSearch(this.value)">
                    </div>
                    <div class="col-8 col-md-3">
                        <label for="sample-status-select" class="form-label">Lifecycle Status</label>
                        <select id="sample-status-select" class="form-select form-select-sm" onchange="LabverseCoreOps.setSampleStatusFilter(this.value)">
                            <option value="">All Sample Statuses</option>
                            <option value="awaiting_collection" ${state.samplesStatusFilter === 'awaiting_collection' ? 'selected' : ''}>Awaiting Collection</option>
                            <option value="collected" ${state.samplesStatusFilter === 'collected' ? 'selected' : ''}>Collected</option>
                            <option value="received" ${state.samplesStatusFilter === 'received' ? 'selected' : ''}>Received at Lab</option>
                            <option value="accepted" ${state.samplesStatusFilter === 'accepted' ? 'selected' : ''}>Accepted</option>
                            <option value="rejected" ${state.samplesStatusFilter === 'rejected' ? 'selected' : ''}>Rejected</option>
                            <option value="processing" ${state.samplesStatusFilter === 'processing' ? 'selected' : ''}>Processing</option>
                            <option value="completed" ${state.samplesStatusFilter === 'completed' ? 'selected' : ''}>Completed</option>
                        </select>
                    </div>
                    <div class="col-4 col-md-1">
                        <button type="button" class="btn btn-primary btn-sm w-100" onclick="LabverseCoreOps.renderSamples(1)">Filter</button>
                    </div>
                </div>
            </div>
            <div id="lv-samples-table-wrap" class="lv-card">
                ${UI.loadingSpinner('Loading specimen registry...')}
            </div>
        `;
        UI.refreshIcons();

        try {
            const params = new URLSearchParams({
                page: state.samplesPage,
                status: state.samplesStatusFilter,
                search: state.samplesSearch,
            });
            const data = await UI.api(`/samples?${params.toString()}`);
            const counts = data.status_counts || {};
            const rows = data.samples?.data || [];

            const kpiRow = document.getElementById('lv-sample-kpi-row');
            if (kpiRow) {
                kpiRow.innerHTML = `
                    <div class="col-6 col-md-2">${UI.statCard({ label: 'Awaiting Collection', value: counts.awaiting_collection ?? 0, icon: 'syringe', tone: 'warning', onClickAttr: `onclick="LabverseCoreOps.setSampleStatusFilter('awaiting_collection')"` })}</div>
                    <div class="col-6 col-md-2">${UI.statCard({ label: 'Collected', value: counts.collected ?? 0, icon: 'test-tube', tone: 'info', onClickAttr: `onclick="LabverseCoreOps.setSampleStatusFilter('collected')"` })}</div>
                    <div class="col-6 col-md-2">${UI.statCard({ label: 'Received', value: counts.received ?? 0, icon: 'inbox', tone: 'secondary', onClickAttr: `onclick="LabverseCoreOps.setSampleStatusFilter('received')"` })}</div>
                    <div class="col-6 col-md-2">${UI.statCard({ label: 'Accepted', value: counts.accepted ?? 0, icon: 'check-circle', tone: 'success', onClickAttr: `onclick="LabverseCoreOps.setSampleStatusFilter('accepted')"` })}</div>
                    <div class="col-6 col-md-2">${UI.statCard({ label: 'Processing', value: counts.processing ?? 0, icon: 'flask-conical', tone: 'primary', onClickAttr: `onclick="LabverseCoreOps.setSampleStatusFilter('processing')"` })}</div>
                    <div class="col-6 col-md-2">${UI.statCard({ label: 'Rejected', value: counts.rejected ?? 0, icon: 'alert-octagon', tone: 'danger', onClickAttr: `onclick="LabverseCoreOps.setSampleStatusFilter('rejected')"` })}</div>
                `;
            }

            const wrap = document.getElementById('lv-samples-table-wrap');
            if (!wrap) return;

            if (rows.length === 0) {
                wrap.innerHTML = UI.emptyState({
                    icon: 'test-tube',
                    title: 'No specimens match the current filter',
                    message: 'Clear the lifecycle filter or scan a barcode above.',
                });
                UI.refreshIcons();
                return;
            }

            wrap.innerHTML = `
                <div class="lv-table-container">
                    <table class="lv-table">
                        <thead>
                            <tr>
                                <th>Sample / Barcode</th>
                                <th>Patient & Order</th>
                                <th>Specimen & Container</th>
                                <th>Collection Details</th>
                                <th>Storage Location</th>
                                <th>Status</th>
                                <th class="text-end">Lifecycle Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map(s => `
                                <tr>
                                    <td>
                                        <span class="font-mono fw-bold text-primary d-block">${UI.esc(s.barcode)}</span>
                                        <span class="small text-secondary font-mono">${UI.esc(s.sample_code)}</span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">${UI.esc(s.patient?.full_name || '—')}</div>
                                        <div class="small text-secondary font-mono">${UI.esc(s.order?.order_number || '')} ${UI.statusBadge(s.order?.priority)}</div>
                                    </td>
                                    <td>
                                        <div class="fw-medium">${UI.esc(s.sample_type)}</div>
                                        <div class="small text-secondary">${UI.esc(s.container_type || 'Standard Container')}</div>
                                    </td>
                                    <td class="small">
                                        ${s.collected_at ? `
                                            <div>By: ${UI.esc(s.collected_by_user?.name || 'Staff')}</div>
                                            <div class="text-secondary">${UI.formatDateTime(s.collected_at)}</div>
                                        ` : '<span class="text-muted">Not collected yet</span>'}
                                    </td>
                                    <td class="small">
                                        ${s.storage_location ? `<span class="lv-badge lv-badge-neutral">${UI.esc(s.storage_location)}</span>` : '<span class="text-muted">Unassigned</span>'}
                                        ${s.rejection_reason ? `<div class="text-danger small mt-1">Rejected: ${UI.esc(s.rejection_reason)}</div>` : ''}
                                    </td>
                                    <td>${UI.statusBadge(s.status)}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                            ${renderSampleActionButtons(s)}
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ${UI.pagination(data.samples, 'LabverseCoreOps.renderSamples')}
            `;
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function renderSampleActionButtons(s) {
        const btns = [];
        if (s.status === 'awaiting_collection') {
            btns.push(`<button type="button" class="btn btn-primary btn-sm" onclick="LabverseCoreOps.quickSampleAction(${s.id}, 'collect')">Collect Sample</button>`);
        } else if (s.status === 'collected') {
            btns.push(`<button type="button" class="btn btn-secondary-teal btn-sm" onclick="LabverseCoreOps.quickSampleAction(${s.id}, 'receive')">Receive at Lab</button>`);
            btns.push(`<button type="button" class="btn btn-success btn-sm" onclick="LabverseCoreOps.quickSampleAction(${s.id}, 'accept')">Accept</button>`);
        } else if (s.status === 'received') {
            btns.push(`<button type="button" class="btn btn-success btn-sm" onclick="LabverseCoreOps.quickSampleAction(${s.id}, 'accept')">Accept Specimen</button>`);
            btns.push(`<button type="button" class="btn btn-outline-danger btn-sm" onclick="LabverseCoreOps.openRejectSampleModal(${s.id}, '${UI.esc(s.sample_code)}')">Reject</button>`);
        } else if (s.status === 'rejected') {
            btns.push(`<button type="button" class="btn btn-warning btn-sm" onclick="LabverseCoreOps.quickSampleAction(${s.id}, 'recollect')">Log Recollection</button>`);
        }
        btns.push(`<button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseCoreOps.openSampleInspector(${s.id}, '${UI.esc(s.barcode)}')">Manage</button>`);
        return btns.join('');
    }

    function updateSampleSearch(val) {
        state.samplesSearch = val;
    }

    function setSampleStatusFilter(status) {
        state.samplesStatusFilter = status;
        renderSamples(1);
    }

    async function scanSampleBarcode() {
        const input = document.getElementById('sample-barcode-scanner');
        const code = input?.value?.trim();
        if (!code) {
            UI.toast('Please scan or enter a barcode.', 'warning');
            return;
        }
        openSampleInspector(null, code);
    }

    async function quickSampleAction(sampleId, action) {
        try {
            const res = await UI.api(`/samples/${sampleId}/status`, {
                method: 'POST',
                body: { action },
            });
            UI.toast(res.message, 'success');
            renderSamples(state.samplesPage);
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function openRejectSampleModal(sampleId, sampleCode) {
        const html = `
            <form method="POST" onsubmit="event.preventDefault(); LabverseCoreOps.submitRejectSample(${sampleId});">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-danger">Reject Specimen — ${UI.esc(sampleCode)}</h5>
                    <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDialog()"></button>
                </div>
                <div class="p-4">
                    <div class="mb-3">
                        <label for="rej-reason" class="form-label">Clinical Rejection Reason <span class="text-danger">*</span></label>
                        <select id="rej-reason" class="form-select" required>
                            <option value="">Select reason...</option>
                            <option value="Grossly Haemolysed Specimen">Grossly Haemolysed Specimen</option>
                            <option value="Clotted EDTA Whole Blood Sample">Clotted EDTA Whole Blood Sample</option>
                            <option value="Insufficient Specimen Volume (QNS)">Insufficient Specimen Volume (QNS)</option>
                            <option value="Wrong Anticoagulant / Wrong Container">Wrong Anticoagulant / Wrong Container</option>
                            <option value="Unlabelled or Mislabelled Specimen Tube">Unlabelled or Mislabelled Specimen Tube</option>
                            <option value="Leaking or Broken Container in Transit">Leaking or Broken Container in Transit</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="rej-notes" class="form-label">Corrective Action / Recollection Instructions</label>
                        <textarea id="rej-notes" class="form-control" rows="3" placeholder="Request immediate recollection from phlebotomy..."></textarea>
                    </div>
                </div>
                <div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDialog()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Sample Rejection</button>
                </div>
            </form>
        `;
        window.LabverseApp.openDialog(html);
    }

    async function submitRejectSample(sampleId) {
        const reason = document.getElementById('rej-reason')?.value;
        const notes = document.getElementById('rej-notes')?.value;
        try {
            const res = await UI.api(`/samples/${sampleId}/status`, {
                method: 'POST',
                body: { action: 'reject', rejection_reason: reason, rejection_notes: notes },
            });
            UI.toast(res.message, 'warning');
            window.LabverseApp.closeDialog();
            renderSamples(state.samplesPage);
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    async function openSampleInspector(sampleId, barcode) {
        window.LabverseApp.openDrawer(UI.loadingSpinner('Looking up specimen barcode...'));
        try {
            const res = await UI.api(`/samples/lookup?barcode=${encodeURIComponent(barcode)}`);
            const s = res.sample;

            const html = `
                <div class="lv-drawer-header">
                    <div>
                        <span class="font-mono fw-bold text-primary fs-6">${UI.esc(s.sample_code)}</span>
                        <h5 class="mb-0 fw-bold mt-1">${UI.esc(s.sample_type)} — ${UI.statusBadge(s.status)}</h5>
                    </div>
                    <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDrawer()"></button>
                </div>
                <div class="lv-drawer-body">
                    <div class="lv-barcode-box mb-4 py-3">
                        <div class="fs-4 fw-bold font-mono">${UI.esc(s.barcode)}</div>
                        <div class="small text-secondary">${UI.esc(s.patient?.full_name)} (${UI.esc(s.patient?.patient_code)}) • Order: ${UI.esc(s.order?.order_number)}</div>
                    </div>

                    <div class="row g-3 mb-4 small">
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="fw-bold text-uppercase text-secondary mb-2">Collection & Reception</div>
                                <div class="mb-1"><strong>Container:</strong> ${UI.esc(s.container_type || 'Standard')}</div>
                                <div class="mb-1"><strong>Collected By:</strong> ${UI.esc(s.collected_by_user?.name || 'Pending')} (${UI.formatDateTime(s.collected_at)})</div>
                                <div class="mb-1"><strong>Received By:</strong> ${UI.esc(s.received_by_user?.name || 'Pending')} (${UI.formatDateTime(s.received_at)})</div>
                                <div><strong>Accepted By:</strong> ${UI.esc(s.accepted_by_user?.name || 'Pending')} (${UI.formatDateTime(s.accepted_at)})</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <form class="p-3 border rounded bg-light h-100" onsubmit="event.preventDefault(); LabverseCoreOps.saveSampleLocation(${s.id});">
                                <div class="fw-bold text-uppercase text-secondary mb-2">Storage & Condition</div>
                                <div class="mb-2">
                                    <label for="smp-loc" class="form-label mb-1">Bench / Fridge Storage Location</label>
                                    <input type="text" id="smp-loc" class="form-control form-control-sm" placeholder="e.g. Cold Rack B2 - Pos 04" value="${UI.esc(s.storage_location || '')}">
                                </div>
                                <button type="submit" class="btn btn-light-surface btn-sm">Update Storage Location</button>
                            </form>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2">Associated Diagnostic Assays</h6>
                    <div class="lv-card mb-3">
                        <table class="lv-table">
                            <thead>
                                <tr><th>Code</th><th>Test Name</th><th>Assay Status</th></tr>
                            </thead>
                            <tbody>
                                ${(s.order_items || []).map(oi => `
                                    <tr>
                                        <td class="font-mono fw-semibold">${UI.esc(oi.test?.code)}</td>
                                        <td>${UI.esc(oi.test?.name)}</td>
                                        <td>${UI.statusBadge(oi.status)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="lv-drawer-footer">
                    <button type="button" class="btn btn-light-surface" onclick="window.print()">
                        <i data-lucide="printer" style="width:15px;height:15px;"></i> Print Barcode Label
                    </button>
                    <div class="d-flex gap-2">
                        ${renderSampleActionButtons(s)}
                    </div>
                </div>
            `;
            window.LabverseApp.openDrawer(html);
        } catch (err) {
            UI.toast(err.message, 'error');
            window.LabverseApp.closeDrawer();
        }
    }

    async function saveSampleLocation(sampleId) {
        const loc = document.getElementById('smp-loc')?.value;
        try {
            const res = await UI.api(`/samples/${sampleId}/status`, {
                method: 'POST',
                body: { action: 'update_location', storage_location: loc },
            });
            UI.toast('Sample storage location updated.', 'success');
            window.LabverseApp.closeDrawer();
            renderSamples(state.samplesPage);
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 13. PAYMENT & FINANCIAL MANAGEMENT MODULE
    // =========================================================================
    async function renderPayments(page = 1) {
        state.paymentsPage = page;
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Payments & Billing',
            title: 'Financial Workspace, Invoices & Receipts',
            description: 'Record Cash, MTN Mobile Money, Telecel Cash, AT Money, Card, and NHIS payments; manage outstanding balances and print official receipts.',
        }) + `<div id="lv-payments-content">${UI.loadingSpinner('Loading financial workspace...')}</div>`;
        UI.refreshIcons();

        try {
            const params = new URLSearchParams({
                page: state.paymentsPage,
                payment_method: state.paymentsMethodFilter,
                search: state.paymentsSearch,
            });
            const data = await UI.api(`/payments?${params.toString()}`);
            const sum = data.summary || {};
            const payments = data.payments?.data || [];
            const outstanding = data.outstanding_orders || [];
            const canManage = window.LabverseApp.hasPermission('manage_payments');

            const wrap = document.getElementById('lv-payments-content');
            if (!wrap) return;

            wrap.innerHTML = `
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">${UI.statCard({ label: 'Collected Today', value: UI.formatCurrency(sum.collected_today), icon: 'wallet', tone: 'success' })}</div>
                    <div class="col-6 col-lg-3">${UI.statCard({ label: 'Total Revenue Collected', value: UI.formatCurrency(sum.collected_total), icon: 'landmark', tone: 'primary' })}</div>
                    <div class="col-6 col-lg-3">${UI.statCard({ label: 'Total Invoiced', value: UI.formatCurrency(sum.total_invoiced), icon: 'file-spreadsheet', tone: 'info' })}</div>
                    <div class="col-6 col-lg-3">${UI.statCard({ label: 'Outstanding Balances', value: UI.formatCurrency(sum.total_outstanding), meta: `${sum.outstanding_orders_count || 0} unpaid/partial orders`, icon: 'alert-circle', tone: 'danger' })}</div>
                </div>

                ${outstanding.length > 0 ? `
                    <div class="lv-card mb-4 border-warning">
                        <div class="lv-card-header bg-warning-subtle">
                            <h2 class="lv-card-title text-dark"><i data-lucide="clock" style="width:17px;height:17px;"></i> Orders with Outstanding Balance (${outstanding.length})</h2>
                        </div>
                        <div class="lv-table-container">
                            <table class="lv-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Patient</th>
                                        <th>Total Invoice</th>
                                        <th>Paid So Far</th>
                                        <th>Balance Due</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${outstanding.map(o => `
                                        <tr>
                                            <td class="font-mono fw-semibold text-primary">${UI.esc(o.order_number)}</td>
                                            <td>${UI.esc(o.patient?.full_name)} <span class="small text-secondary font-mono">(${UI.esc(o.patient?.patient_code)})</span></td>
                                            <td class="font-mono">${UI.formatCurrency(o.total_amount)}</td>
                                            <td class="font-mono text-success">${UI.formatCurrency(o.amount_paid)}</td>
                                            <td class="font-mono fw-bold text-danger">${UI.formatCurrency(o.balance_due)}</td>
                                            <td>${UI.statusBadge(o.payment_status)}</td>
                                            <td class="text-end">
                                                ${canManage ? `
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="LabverseCoreOps.openRecordPaymentModal(${o.id}, '${UI.esc(o.order_number)}', ${o.balance_due})">
                                                        <i data-lucide="plus" style="width:14px;height:14px;"></i> Collect Payment
                                                    </button>
                                                ` : ''}
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ` : ''}

                <div class="lv-card">
                    <div class="lv-card-header flex-wrap">
                        <h2 class="lv-card-title">Payment Transaction Ledger & Official Receipts</h2>
                        <div class="d-flex gap-2">
                            <input type="search" id="pay-search-input" class="form-control form-control-sm" placeholder="Receipt #, Order #, or Patient..." value="${UI.esc(state.paymentsSearch)}">
                            <select id="pay-method-filter" class="form-select form-select-sm" onchange="LabverseCoreOps.filterPayments()">
                                <option value="">All Payment Methods</option>
                                <option value="cash" ${state.paymentsMethodFilter === 'cash' ? 'selected' : ''}>Cash (GHS)</option>
                                <option value="mtn_momo" ${state.paymentsMethodFilter === 'mtn_momo' ? 'selected' : ''}>MTN Mobile Money</option>
                                <option value="telecel_cash" ${state.paymentsMethodFilter === 'telecel_cash' ? 'selected' : ''}>Telecel Cash</option>
                                <option value="at_money" ${state.paymentsMethodFilter === 'at_money' ? 'selected' : ''}>AT Money</option>
                                <option value="card" ${state.paymentsMethodFilter === 'card' ? 'selected' : ''}>POS / Card</option>
                                <option value="bank_transfer" ${state.paymentsMethodFilter === 'bank_transfer' ? 'selected' : ''}>Bank Transfer</option>
                                <option value="nhis" ${state.paymentsMethodFilter === 'nhis' ? 'selected' : ''}>NHIS</option>
                            </select>
                            <button type="button" class="btn btn-primary btn-sm" onclick="LabverseCoreOps.filterPayments()">Search</button>
                        </div>
                    </div>
                    <div class="lv-table-container">
                        <table class="lv-table">
                            <thead>
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Date & Time</th>
                                    <th>Patient & Order</th>
                                    <th>Payment Method</th>
                                    <th>Transaction Ref</th>
                                    <th>Amount Paid</th>
                                    <th>Cashier</th>
                                    <th class="text-end">Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${payments.map(p => `
                                    <tr>
                                        <td class="font-mono fw-semibold text-primary">${UI.esc(p.receipt_number)}</td>
                                        <td>${UI.formatDateTime(p.paid_at)}</td>
                                        <td>
                                            <div class="fw-semibold">${UI.esc(p.patient?.full_name || '—')}</div>
                                            <div class="small text-secondary font-mono">Order: ${UI.esc(p.order?.order_number || '—')}</div>
                                        </td>
                                        <td><span class="lv-badge lv-badge-info">${UI.esc(UI.paymentMethodLabel(p.payment_method))}</span></td>
                                        <td class="font-mono small">${UI.esc(p.transaction_reference || '—')}</td>
                                        <td class="font-mono fw-bold text-success">${UI.formatCurrency(p.amount)}</td>
                                        <td class="small">${UI.esc(p.received_by_user?.name || 'Cashier')}</td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseCoreOps.viewReceipt(${p.id})">
                                                <i data-lucide="printer" style="width:14px;height:14px;"></i> Receipt
                                            </button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    ${UI.pagination(data.payments, 'LabverseCoreOps.renderPayments')}
                </div>
            `;
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function filterPayments() {
        state.paymentsSearch = document.getElementById('pay-search-input')?.value || '';
        state.paymentsMethodFilter = document.getElementById('pay-method-filter')?.value || '';
        renderPayments(1);
    }

    function openRecordPaymentModal(orderId, orderNumber, balanceDue) {
        const html = `
            <form method="POST" onsubmit="event.preventDefault(); LabverseCoreOps.submitPayment(${orderId});">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold">Record Payment — ${UI.esc(orderNumber)}</h5>
                        <div class="small text-secondary">Outstanding Balance: <strong class="text-danger font-mono">${UI.formatCurrency(balanceDue)}</strong></div>
                    </div>
                    <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDialog()"></button>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="rec-pay-amount" class="form-label">Amount Being Paid (GH₵) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" max="${balanceDue}" id="rec-pay-amount" class="form-control font-mono fw-bold" required value="${Number(balanceDue).toFixed(2)}">
                        </div>
                        <div class="col-md-6">
                            <label for="rec-pay-method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select id="rec-pay-method" class="form-select" required>
                                <option value="cash">Cash (GHS)</option>
                                <option value="mtn_momo">MTN Mobile Money</option>
                                <option value="telecel_cash">Telecel Cash</option>
                                <option value="at_money">AT Money</option>
                                <option value="card">POS / Visa / Mastercard</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="nhis">NHIS / Corporate</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="rec-pay-ref" class="form-label">Transaction Reference / Mobile Money ID</label>
                            <input type="text" id="rec-pay-ref" class="form-control font-mono" placeholder="e.g. MOMO-GH-8472910...">
                        </div>
                        <div class="col-12">
                            <label for="rec-pay-notes" class="form-label">Cashier Remarks</label>
                            <input type="text" id="rec-pay-notes" class="form-control" placeholder="Balance settlement...">
                        </div>
                    </div>
                </div>
                <div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDialog()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm Payment & Issue Receipt</button>
                </div>
            </form>
        `;
        window.LabverseApp.openDialog(html);
    }

    async function submitPayment(orderId) {
        const amount = Number(document.getElementById('rec-pay-amount')?.value || 0);
        const method = document.getElementById('rec-pay-method')?.value || 'cash';
        const ref = document.getElementById('rec-pay-ref')?.value || null;
        const notes = document.getElementById('rec-pay-notes')?.value || null;

        try {
            const res = await UI.api('/payments', {
                method: 'POST',
                body: {
                    lab_order_id: orderId,
                    amount,
                    payment_method: method,
                    transaction_reference: ref,
                    notes,
                },
            });
            UI.toast(res.message, 'success');
            window.LabverseApp.closeDialog();
            renderPayments(state.paymentsPage);
            if (res.payment) {
                viewReceipt(res.payment.id);
            }
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    async function viewReceipt(paymentId) {
        window.LabverseApp.openDrawer(UI.loadingSpinner('Generating official payment receipt...'));
        try {
            const res = await UI.api(`/payments/${paymentId}`);
            const p = res.payment;

            const html = `
                <div class="lv-drawer-header lv-no-print">
                    <h5 class="mb-0 fw-bold">Official Payment Receipt — ${UI.esc(p.receipt_number)}</h5>
                    <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDrawer()"></button>
                </div>
                <div class="lv-drawer-body">
                    <div class="lv-report-sheet">
                        <div class="lv-report-header d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="fw-bold mb-1" style="color:var(--lv-primary);">LABVERSE DIAGNOSTIC LABORATORIES</h4>
                                <div class="small text-secondary">${UI.esc(p.branch?.name || 'Main Reference Lab')} • ${UI.esc(p.branch?.address || 'Accra, Ghana')}</div>
                                <div class="small text-secondary">Tel: ${UI.esc(p.branch?.phone || '+233 302 784 900')}</div>
                            </div>
                            <div class="text-end">
                                <span class="lv-badge lv-badge-success">OFFICIAL RECEIPT</span>
                                <div class="font-mono fw-bold fs-6 mt-1">${UI.esc(p.receipt_number)}</div>
                                <div class="small text-secondary">Invoice: ${UI.esc(p.invoice_number)}</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4 small">
                            <div class="col-6">
                                <div><strong>Received From:</strong> ${UI.esc(p.patient?.full_name)}</div>
                                <div><strong>Patient ID:</strong> <span class="font-mono">${UI.esc(p.patient?.patient_code)}</span></div>
                                <div><strong>Order Number:</strong> <span class="font-mono">${UI.esc(p.order?.order_number)}</span></div>
                            </div>
                            <div class="col-6 text-end">
                                <div><strong>Payment Date:</strong> ${UI.formatDateTime(p.paid_at)}</div>
                                <div><strong>Payment Method:</strong> ${UI.esc(UI.paymentMethodLabel(p.payment_method))}</div>
                                <div><strong>Transaction Ref:</strong> <span class="font-mono">${UI.esc(p.transaction_reference || 'N/A')}</span></div>
                            </div>
                        </div>

                        <table class="lv-table border mb-4">
                            <thead>
                                <tr>
                                    <th>Test Code</th>
                                    <th>Diagnostic Investigation</th>
                                    <th class="text-end">Fee</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(p.order?.items || []).map(it => `
                                    <tr>
                                        <td class="font-mono">${UI.esc(it.test?.code)}</td>
                                        <td>${UI.esc(it.test?.name)}</td>
                                        <td class="text-end font-mono">${UI.formatCurrency(it.price)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>

                        <div class="row justify-content-end">
                            <div class="col-md-6">
                                <div class="p-3 rounded border bg-light small">
                                    <div class="d-flex justify-content-between mb-1"><span>Order Total:</span><span class="font-mono">${UI.formatCurrency(p.order?.total_amount)}</span></div>
                                    <div class="d-flex justify-content-between mb-1 fw-bold text-success fs-6"><span>Amount Paid (This Receipt):</span><span class="font-mono">${UI.formatCurrency(p.amount)}</span></div>
                                    <div class="d-flex justify-content-between border-top pt-1"><span>Remaining Balance:</span><span class="font-mono fw-bold">${UI.formatCurrency(p.order?.balance_due)}</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-between small text-secondary">
                            <div>Issued By Cashier: <strong>${UI.esc(p.received_by_user?.name || 'Authorized Cashier')}</strong></div>
                            <div>Thank you for choosing Labverse Medical Diagnostics.</div>
                        </div>
                    </div>
                </div>
                <div class="lv-drawer-footer lv-no-print">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDrawer()">Close</button>
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i data-lucide="printer" style="width:15px;height:15px;"></i> Print Receipt
                    </button>
                </div>
            `;
            window.LabverseApp.openDrawer(html);
        } catch (err) {
            UI.toast(err.message, 'error');
            window.LabverseApp.closeDrawer();
        }
    }

    return {
        renderPatients,
        applyPatientFilters,
        resetPatientFilters,
        openPatientModal,
        checkDuplicatePatient,
        submitPatientForm,
        viewPatientProfile,
        startOrderForPatient,
        renderOrders,
        renderOrdersPage,
        applyOrderFilters,
        resetOrderFilters,
        renderNewOrderComposer,
        searchPatientForOrder,
        selectPatientForOrder,
        clearSelectedOrderPatient,
        setOrderCategory,
        filterComposerCatalogue,
        toggleTestInCart,
        updateOrderDiscount,
        updateDiscountReason,
        submitNewOrder,
        viewOrderDetails,
        renderSamples,
        updateSampleSearch,
        setSampleStatusFilter,
        scanSampleBarcode,
        quickSampleAction,
        openRejectSampleModal,
        submitRejectSample,
        openSampleInspector,
        saveSampleLocation,
        renderPayments,
        filterPayments,
        openRecordPaymentModal,
        submitPayment,
        viewReceipt,
    };
})();

