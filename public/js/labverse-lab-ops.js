/**
 * ============================================================================
 * LABVERSE — Phase 5: Laboratory Operations
 * (9. Laboratory Workspace, 10. Result Entry, 11. Result Approval, 12. Report Generation)
 * ============================================================================
 */
window.LabverseLabOps = (function () {
    const UI = window.LabverseUI;

    const state = {
        labPage: 1,
        labStatusFilter: 'queue',
        labCategoryFilter: '',
        labPriorityFilter: '',
        labSearch: '',
        resultsPage: 1,
        resultsStatusFilter: '',
        resultsSearch: '',
        approvalsPage: 1,
        approvalsSearch: '',
        reportsPage: 1,
        reportsSearch: '',
        categories: [],
        activeEntryItem: null,
    };

    async function ensureCategoriesLoaded() {
        if (state.categories.length === 0) {
            try {
                const res = await UI.api('/catalogue/categories');
                state.categories = res.categories || [];
            } catch (_) {}
        }
    }

    // =========================================================================
    // 9. LABORATORY WORKSPACE MODULE
    // =========================================================================
    async function renderLaboratoryWorkspace(page = 1) {
        state.labPage = page;
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        await ensureCategoriesLoaded();

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Laboratory Workspace',
            title: 'Diagnostic Laboratory Bench Queue',
            description: 'Monitor assigned assays, prioritize STAT and urgent specimens, inspect patient clinical notes, and open tests for result entry.',
        }) + `
            <div class="row g-3 mb-3" id="lv-lab-summary-cards"></div>
            <div class="lv-filter-bar">
                <form class="row g-2 align-items-end" onsubmit="event.preventDefault(); LabverseLabOps.applyLabFilters();">
                    <div class="col-12 col-md-4">
                        <label for="lab-search-input" class="form-label">Search Queue</label>
                        <input type="search" id="lab-search-input" class="form-control form-control-sm" placeholder="Order #, Sample Barcode, Patient, or Test..." value="${UI.esc(state.labSearch)}">
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="lab-cat-filter" class="form-label">Test Department / Category</label>
                        <select id="lab-cat-filter" class="form-select form-select-sm">
                            <option value="">All Departments</option>
                            ${state.categories.map(c => `<option value="${c.id}" ${String(state.labCategoryFilter) === String(c.id) ? 'selected' : ''}>${UI.esc(c.name)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="lab-status-filter" class="form-label">Bench Status</label>
                        <select id="lab-status-filter" class="form-select form-select-sm">
                            <option value="queue" ${state.labStatusFilter === 'queue' ? 'selected' : ''}>Active Work Queue</option>
                            <option value="" ${state.labStatusFilter === '' ? 'selected' : ''}>All Statuses</option>
                            <option value="pending_sample" ${state.labStatusFilter === 'pending_sample' ? 'selected' : ''}>Awaiting Sample</option>
                            <option value="awaiting_processing" ${state.labStatusFilter === 'awaiting_processing' ? 'selected' : ''}>Ready for Assay</option>
                            <option value="draft_saved" ${state.labStatusFilter === 'draft_saved' ? 'selected' : ''}>Draft Saved</option>
                            <option value="correction_requested" ${state.labStatusFilter === 'correction_requested' ? 'selected' : ''}>Correction Requested</option>
                            <option value="submitted" ${state.labStatusFilter === 'submitted' ? 'selected' : ''}>Submitted for Review</option>
                            <option value="approved" ${state.labStatusFilter === 'approved' ? 'selected' : ''}>Approved & Completed</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="lab-priority-filter" class="form-label">Priority</label>
                        <select id="lab-priority-filter" class="form-select form-select-sm">
                            <option value="">All Priorities</option>
                            <option value="stat" ${state.labPriorityFilter === 'stat' ? 'selected' : ''}>STAT / Emergency</option>
                            <option value="urgent" ${state.labPriorityFilter === 'urgent' ? 'selected' : ''}>Urgent</option>
                            <option value="routine" ${state.labPriorityFilter === 'routine' ? 'selected' : ''}>Routine</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                    </div>
                </form>
            </div>
            <div id="lv-lab-queue-wrap" class="lv-card">
                ${UI.loadingSpinner('Loading laboratory work queue...')}
            </div>
        `;
        UI.refreshIcons();

        try {
            const params = new URLSearchParams({
                page: state.labPage,
                status: state.labStatusFilter,
                category_id: state.labCategoryFilter,
                priority: state.labPriorityFilter,
                search: state.labSearch,
            });
            const data = await UI.api(`/lab/items?${params.toString()}`);
            const sum = data.summary || {};
            const rows = data.items?.data || [];

            const sumEl = document.getElementById('lv-lab-summary-cards');
            if (sumEl) {
                sumEl.innerHTML = `
                    <div class="col-6 col-md-2">${UI.statCard({ label: 'Ready for Assay', value: sum.awaiting_processing ?? 0, icon: 'flask-conical', tone: 'info', onClickAttr: `onclick="LabverseLabOps.setLabQueueFilter('awaiting_processing')"` })}</div>
                    <div class="col-6 col-md-2">${UI.statCard({ label: 'In Progress / Drafts', value: sum.in_progress ?? 0, icon: 'edit-3', tone: 'secondary', onClickAttr: `onclick="LabverseLabOps.setLabQueueFilter('draft_saved')"` })}</div>
                    <div class="col-6 col-md-2">${UI.statCard({ label: 'Urgent / STAT', value: sum.urgent_or_stat ?? 0, icon: 'zap', tone: 'danger', onClickAttr: `onclick="LabverseLabOps.setLabPriorityFilter('stat')"` })}</div>
                    <div class="col-6 col-md-2">${UI.statCard({ label: 'Corrections Needed', value: sum.correction_requested ?? 0, icon: 'rotate-ccw', tone: 'warning', onClickAttr: `onclick="LabverseLabOps.setLabQueueFilter('correction_requested')"` })}</div>
                    <div class="col-6 col-md-2">${UI.statCard({ label: 'Awaiting Approval', value: sum.submitted ?? 0, icon: 'clock', tone: 'warning', onClickAttr: `onclick="LabverseLabOps.setLabQueueFilter('submitted')"` })}</div>
                    <div class="col-6 col-md-2">${UI.statCard({ label: 'Completed Assays', value: sum.approved ?? 0, icon: 'check-circle-2', tone: 'success', onClickAttr: `onclick="LabverseLabOps.setLabQueueFilter('approved')"` })}</div>
                `;
            }

            const wrap = document.getElementById('lv-lab-queue-wrap');
            if (!wrap) return;

            if (rows.length === 0) {
                wrap.innerHTML = UI.emptyState({
                    icon: 'microscope',
                    title: 'No tests in this laboratory queue view',
                    message: 'Switch bench status to "All Statuses" or accept incoming specimens in Sample Collection.',
                });
                UI.refreshIcons();
                return;
            }

            const canEnter = window.LabverseApp.hasPermission('enter_results');

            wrap.innerHTML = `
                <div class="lv-table-container">
                    <table class="lv-table">
                        <thead>
                            <tr>
                                <th>Order & Priority</th>
                                <th>Patient Details</th>
                                <th>Diagnostic Assay</th>
                                <th>Specimen Barcode</th>
                                <th>Turnaround / Status</th>
                                <th class="text-end">Bench Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map(item => `
                                <tr>
                                    <td>
                                        <span class="font-mono fw-semibold text-primary d-block">${UI.esc(item.order?.order_number)}</span>
                                        <div class="mt-1">${UI.statusBadge(item.order?.priority)}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">${UI.esc(item.order?.patient?.full_name)}</div>
                                        <div class="small text-secondary">${UI.esc(item.order?.patient?.patient_code)} • ${UI.esc(item.order?.patient?.gender)}, ${item.order?.patient?.age ?? '—'} yrs</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">${UI.esc(item.test?.name)}</div>
                                        <div class="small text-secondary"><span class="font-mono">${UI.esc(item.test?.code)}</span> • ${UI.esc(item.test?.category?.name || '')}</div>
                                        ${item.reviewer_comments && item.status === 'correction_requested' ? `
                                            <div class="small text-danger mt-1 fw-medium"><i data-lucide="alert-circle" style="width:13px;height:13px;"></i> Supervisor Note: ${UI.esc(item.reviewer_comments)}</div>
                                        ` : ''}
                                    </td>
                                    <td>
                                        <div class="font-mono fw-semibold">${UI.esc(item.sample?.barcode || '—')}</div>
                                        <div class="mt-1">${UI.statusBadge(item.sample?.status)}</div>
                                    </td>
                                    <td>
                                        <div>${UI.statusBadge(item.status)}</div>
                                        <div class="small text-secondary mt-1">Target TAT: ${item.test?.turnaround_hours || 4}h</div>
                                    </td>
                                    <td class="text-end">
                                        ${item.status === 'pending_sample' ? `
                                            <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseApp.navigate('samples')">
                                                Accept Sample First
                                            </button>
                                        ` : canEnter && item.status !== 'approved' ? `
                                            <button type="button" class="btn btn-primary btn-sm" onclick="LabverseLabOps.openResultEntryModal(${item.id})">
                                                <i data-lucide="file-edit" style="width:14px;height:14px;"></i> ${item.status === 'submitted' ? 'Inspect / Edit Results' : 'Enter Results'}
                                            </button>
                                        ` : `
                                            <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseLabOps.openResultEntryModal(${item.id})">
                                                <i data-lucide="eye" style="width:14px;height:14px;"></i> View Results
                                            </button>
                                        `}
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ${UI.pagination(data.items, 'LabverseLabOps.renderLaboratoryWorkspace')}
            `;
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function applyLabFilters() {
        state.labSearch = document.getElementById('lab-search-input')?.value || '';
        state.labCategoryFilter = document.getElementById('lab-cat-filter')?.value || '';
        state.labStatusFilter = document.getElementById('lab-status-filter')?.value ?? '';
        state.labPriorityFilter = document.getElementById('lab-priority-filter')?.value || '';
        renderLaboratoryWorkspace(1);
    }

    function setLabQueueFilter(status) {
        state.labStatusFilter = status;
        renderLaboratoryWorkspace(1);
    }

    function setLabPriorityFilter(priority) {
        state.labPriorityFilter = priority;
        state.labStatusFilter = '';
        renderLaboratoryWorkspace(1);
    }

    // =========================================================================
    // 10. ADAPTIVE RESULT ENTRY MODULE
    // =========================================================================
    async function renderResultsModule(page = 1) {
        state.resultsPage = page;
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Result Entry',
            title: 'Diagnostic Result Entry & Clinical Validation',
            description: 'Enter numeric, qualitative, or text-based parameters against configured reference intervals, save drafts, or submit results for supervisor approval.',
        }) + `
            <div class="lv-filter-bar">
                <form class="row g-2 align-items-end" onsubmit="event.preventDefault(); LabverseLabOps.filterResultsModule();">
                    <div class="col-12 col-md-6">
                        <label for="res-mod-search" class="form-label">Search Order, Barcode, Patient or Assay</label>
                        <input type="search" id="res-mod-search" class="form-control form-control-sm" placeholder="Search by Order #, Patient name, Barcode, or Test code..." value="${UI.esc(state.resultsSearch)}">
                    </div>
                    <div class="col-8 col-md-4">
                        <label for="res-mod-status" class="form-label">Result Status</label>
                        <select id="res-mod-status" class="form-select form-select-sm">
                            <option value="" ${state.resultsStatusFilter === '' ? 'selected' : ''}>All Result Statuses</option>
                            <option value="awaiting_processing" ${state.resultsStatusFilter === 'awaiting_processing' ? 'selected' : ''}>Awaiting Entry</option>
                            <option value="draft_saved" ${state.resultsStatusFilter === 'draft_saved' ? 'selected' : ''}>Draft Saved</option>
                            <option value="correction_requested" ${state.resultsStatusFilter === 'correction_requested' ? 'selected' : ''}>Correction Requested</option>
                            <option value="submitted" ${state.resultsStatusFilter === 'submitted' ? 'selected' : ''}>Submitted for Review</option>
                            <option value="approved" ${state.resultsStatusFilter === 'approved' ? 'selected' : ''}>Approved & Verified</option>
                        </select>
                    </div>
                    <div class="col-4 col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                    </div>
                </form>
            </div>
            <div id="lv-results-list-wrap" class="lv-card">
                ${UI.loadingSpinner('Loading diagnostic tests for result entry...')}
            </div>
        `;
        UI.refreshIcons();

        try {
            const params = new URLSearchParams({
                page: state.resultsPage,
                status: state.resultsStatusFilter,
                search: state.resultsSearch,
            });
            const data = await UI.api(`/lab/items?${params.toString()}`);
            const rows = data.items?.data || [];
            const wrap = document.getElementById('lv-results-list-wrap');
            if (!wrap) return;

            if (rows.length === 0) {
                wrap.innerHTML = UI.emptyState({
                    icon: 'file-spreadsheet',
                    title: 'No test records found',
                    message: 'Clear filters to view all laboratory tests.',
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
                                <th>Test Panel</th>
                                <th>Specimen</th>
                                <th>Flags</th>
                                <th>Entered By</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map(item => `
                                <tr>
                                    <td class="font-mono fw-semibold text-primary">${UI.esc(item.order?.order_number)}</td>
                                    <td>
                                        <div class="fw-semibold">${UI.esc(item.order?.patient?.full_name)}</div>
                                        <div class="small text-secondary">${UI.esc(item.order?.patient?.gender)}, ${item.order?.patient?.age ?? '—'} yrs</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">${UI.esc(item.test?.name)}</div>
                                        <div class="small text-secondary font-mono">${UI.esc(item.test?.code)}</div>
                                    </td>
                                    <td class="font-mono small">${UI.esc(item.sample?.barcode || '—')} ${UI.statusBadge(item.sample?.status)}</td>
                                    <td>
                                        ${item.has_critical_values ? `<span class="lv-badge lv-badge-critical">CRITICAL VALUE</span>` : item.has_abnormal_values ? `<span class="lv-badge lv-badge-warning">Abnormal Flag</span>` : `<span class="lv-badge lv-badge-neutral">Standard</span>`}
                                    </td>
                                    <td class="small">${UI.esc(item.entered_by_user?.name || '—')}</td>
                                    <td>${UI.statusBadge(item.status)}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn ${item.status === 'approved' ? 'btn-light-surface' : 'btn-primary'} btn-sm" onclick="LabverseLabOps.openResultEntryModal(${item.id})">
                                            <i data-lucide="${item.status === 'approved' ? 'eye' : 'edit-3'}" style="width:14px;height:14px;"></i>
                                            ${item.status === 'approved' ? 'Inspect' : 'Open Result Sheet'}
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ${UI.pagination(data.items, 'LabverseLabOps.renderResultsModule')}
            `;
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function filterResultsModule() {
        state.resultsSearch = document.getElementById('res-mod-search')?.value || '';
        state.resultsStatusFilter = document.getElementById('res-mod-status')?.value || '';
        renderResultsModule(1);
    }

    // Open Adaptive Result Entry Workbench in Drawer
    async function openResultEntryModal(orderItemId) {
        window.LabverseApp.openDrawer(UI.loadingSpinner('Loading test parameters & reference intervals...'), true);
        try {
            const data = await UI.api(`/lab/items/${orderItemId}`);
            const item = data.item;
            state.activeEntryItem = item;

            const patient = item.order?.patient;
            const gender = (patient?.gender || 'Male').toLowerCase();
            const isLocked = item.status === 'approved' || !window.LabverseApp.hasPermission('enter_results');
            const parameters = item.test?.parameters || [];
            const existingMap = {};
            (item.results || []).forEach(r => {
                existingMap[r.test_parameter_id] = r;
            });

            const html = `
                <div class="lv-drawer-header">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="font-mono fw-bold text-primary">${UI.esc(item.order?.order_number)}</span>
                            <span class="font-mono lv-badge lv-badge-neutral">Barcode: ${UI.esc(item.sample?.barcode || 'N/A')}</span>
                            ${UI.statusBadge(item.status)}
                        </div>
                        <h5 class="mb-0 fw-bold mt-1">${UI.esc(item.test?.name)} (${UI.esc(item.test?.code)})</h5>
                    </div>
                    <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDrawer()"></button>
                </div>

                <form id="lv-result-entry-form" class="lv-drawer-body" onsubmit="event.preventDefault(); LabverseLabOps.submitResultsForm(${item.id}, 'submit');">
                    <!-- Patient & Specimen Contextual Strip -->
                    <div class="p-3 rounded border bg-light mb-3 small">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <strong>Patient:</strong> ${UI.esc(patient?.full_name)} (<span class="font-mono">${UI.esc(patient?.patient_code)}</span>)<br>
                                <strong>Sex / Age:</strong> ${UI.esc(patient?.gender)} • ${patient?.age ?? '—'} yrs
                            </div>
                            <div class="col-md-4">
                                <strong>Specimen:</strong> ${UI.esc(item.sample?.sample_type || item.test?.sample_type)}<br>
                                <strong>Methodology:</strong> ${UI.esc(item.test?.methodology || 'Standard Assay')}
                            </div>
                            <div class="col-md-4">
                                <strong>Clinical Indication:</strong> ${UI.esc(item.order?.clinical_notes || item.order?.diagnosis || 'Routine diagnostic request')}<br>
                                <strong>Referring Clinician:</strong> ${UI.esc(item.order?.referring_doctor || 'Walk-in')}
                            </div>
                        </div>
                    </div>

                    ${item.reviewer_comments ? `
                        <div class="alert ${item.status === 'correction_requested' ? 'alert-danger' : 'alert-info'} py-2 small mb-3">
                            <strong>Supervisor Review Comment:</strong> ${UI.esc(item.reviewer_comments)}
                        </div>
                    ` : ''}

                    <div class="lv-card mb-3">
                        <div class="lv-card-header">
                            <h6 class="lv-card-title">Analytical Parameters & Reference Intervals (${UI.esc(patient?.gender)} Reference Applied)</h6>
                            <span class="small text-secondary">Results are never auto-approved upon saving.</span>
                        </div>
                        <div class="lv-table-container">
                            <table class="lv-table">
                                <thead>
                                    <tr>
                                        <th style="width:26%;">Parameter</th>
                                        <th style="width:26%;">Result Value</th>
                                        <th style="width:10%;">Unit</th>
                                        <th style="width:18%;">Reference Interval</th>
                                        <th style="width:12%;">Clinical Flag</th>
                                        <th style="width:8%;">Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${parameters.map(p => {
                                        const ex = existingMap[p.id] || {};
                                        const val = ex.result_value ?? '';
                                        const refMin = gender === 'female' ? (p.ref_min_female ?? p.ref_min_male) : (p.ref_min_male ?? p.ref_min_female);
                                        const refMax = gender === 'female' ? (p.ref_max_female ?? p.ref_max_male) : (p.ref_max_male ?? p.ref_max_female);
                                        const refText = p.reference_text || (refMin !== null && refMax !== null ? `${refMin} - ${refMax}` : 'N/A');

                                        let inputControl = '';
                                        if (p.input_type === 'select' && Array.isArray(p.options_json)) {
                                            inputControl = `
                                                <select class="form-select form-select-sm lv-param-input"
                                                    data-param-id="${p.id}"
                                                    data-input-type="select"
                                                    aria-label="${UI.esc(p.name)}"
                                                    ${isLocked ? 'disabled' : ''}
                                                    onchange="LabverseLabOps.liveEvaluateRow(this)">
                                                    <option value="">-- Select Result --</option>
                                                    ${p.options_json.map(opt => `<option value="${UI.esc(opt)}" ${val === opt ? 'selected' : ''}>${UI.esc(opt)}</option>`).join('')}
                                                </select>
                                            `;
                                        } else if (p.input_type === 'numeric') {
                                            inputControl = `
                                                <input type="number" step="any"
                                                    class="form-control form-control-sm font-mono fw-semibold lv-param-input"
                                                    data-param-id="${p.id}"
                                                    data-input-type="numeric"
                                                    data-min="${refMin ?? ''}"
                                                    data-max="${refMax ?? ''}"
                                                    data-crit-low="${p.critical_low ?? ''}"
                                                    data-crit-high="${p.critical_high ?? ''}"
                                                    aria-label="${UI.esc(p.name)}"
                                                    value="${UI.esc(val)}"
                                                    placeholder="Enter value..."
                                                    ${isLocked ? 'disabled' : ''}
                                                    oninput="LabverseLabOps.liveEvaluateRow(this)">
                                            `;
                                        } else {
                                            inputControl = `
                                                <input type="text"
                                                    class="form-control form-control-sm lv-param-input"
                                                    data-param-id="${p.id}"
                                                    data-input-type="text"
                                                    aria-label="${UI.esc(p.name)}"
                                                    value="${UI.esc(val)}"
                                                    placeholder="Enter observation..."
                                                    ${isLocked ? 'disabled' : ''}
                                                    oninput="LabverseLabOps.liveEvaluateRow(this)">
                                            `;
                                        }

                                        return `
                                            <tr id="param-row-${p.id}">
                                                <td>
                                                    <div class="fw-semibold">${UI.esc(p.name)}</div>
                                                    <div class="small text-secondary font-mono">${UI.esc(p.code)}</div>
                                                </td>
                                                <td>${inputControl}</td>
                                                <td class="font-mono small text-secondary">${UI.esc(p.unit || '—')}</td>
                                                <td class="font-mono small">${UI.esc(refText)}</td>
                                                <td id="param-flag-${p.id}">${UI.statusBadge(ex.flag || 'pending')}</td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm lv-param-remark" data-param-id="${p.id}" placeholder="Remark" value="${UI.esc(ex.remarks || '')}" ${isLocked ? 'disabled' : ''}>
                                                </td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="res-tech-notes" class="form-label">Bench Scientist Technical Observations / QC Notes</label>
                        <textarea id="res-tech-notes" class="form-control form-control-sm" rows="2" placeholder="Record analyzer QC status, dilution notes, or morphological comments..." ${isLocked ? 'disabled' : ''}>${UI.esc(item.technician_notes || '')}</textarea>
                    </div>
                </form>

                <div class="lv-drawer-footer">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDrawer()">Close</button>
                    ${!isLocked ? `
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-light-surface" onclick="LabverseLabOps.submitResultsForm(${item.id}, 'draft')">
                                <i data-lucide="save" style="width:15px;height:15px;"></i> Save Draft
                            </button>
                            <button type="button" class="btn btn-outline-primary" onclick="LabverseLabOps.submitResultsForm(${item.id}, 'repeat')">
                                <i data-lucide="repeat" style="width:15px;height:15px;"></i> Log Repeat Run & Submit
                            </button>
                            <button type="button" class="btn btn-primary" onclick="LabverseLabOps.submitResultsForm(${item.id}, 'submit')">
                                <i data-lucide="send" style="width:15px;height:15px;"></i> Submit Results for Review
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;

            window.LabverseApp.openDrawer(html, true);
        } catch (err) {
            UI.toast(err.message, 'error');
            window.LabverseApp.closeDrawer();
        }
    }

    function liveEvaluateRow(inputEl) {
        const paramId = inputEl.getAttribute('data-param-id');
        const type = inputEl.getAttribute('data-input-type');
        const raw = (inputEl.value || '').trim();
        const flagCell = document.getElementById(`param-flag-${paramId}`);
        if (!flagCell) return;

        if (!raw) {
            flagCell.innerHTML = UI.statusBadge('pending');
            return;
        }

        let flag = 'normal';
        if (type === 'numeric' && !isNaN(Number(raw))) {
            const val = Number(raw);
            const min = inputEl.getAttribute('data-min') !== '' ? Number(inputEl.getAttribute('data-min')) : null;
            const max = inputEl.getAttribute('data-max') !== '' ? Number(inputEl.getAttribute('data-max')) : null;
            const critLow = inputEl.getAttribute('data-crit-low') !== '' ? Number(inputEl.getAttribute('data-crit-low')) : null;
            const critHigh = inputEl.getAttribute('data-crit-high') !== '' ? Number(inputEl.getAttribute('data-crit-high')) : null;

            if (critLow !== null && val <= critLow) flag = 'critical_low';
            else if (critHigh !== null && val >= critHigh) flag = 'critical_high';
            else if (min !== null && val < min) flag = 'low';
            else if (max !== null && val > max) flag = 'high';
        } else {
            const lower = raw.toLowerCase();
            if (
                lower.includes('positive') ||
                (lower.includes('reactive') && !lower.includes('non-reactive')) ||
                (lower.includes('seen') && !lower.includes('no ') && !lower.includes('none'))
            ) {
                flag = 'abnormal';
            }
        }

        flagCell.innerHTML = UI.statusBadge(flag);
    }

    async function submitResultsForm(orderItemId, mode = 'submit') {
        const inputs = document.querySelectorAll('.lv-param-input');
        const remarkInputs = document.querySelectorAll('.lv-param-remark');
        const remarksMap = {};
        remarkInputs.forEach(r => {
            remarksMap[r.getAttribute('data-param-id')] = r.value;
        });

        const results = [];
        inputs.forEach(inp => {
            const pid = Number(inp.getAttribute('data-param-id'));
            results.push({
                test_parameter_id: pid,
                result_value: inp.value,
                remarks: remarksMap[pid] || null,
            });
        });

        const techNotes = document.getElementById('res-tech-notes')?.value || null;

        try {
            const res = await UI.api(`/lab/items/${orderItemId}/results`, {
                method: 'POST',
                body: {
                    mode,
                    technician_notes: techNotes,
                    results,
                },
            });
            UI.toast(res.message, 'success');
            window.LabverseApp.closeDrawer();

            const activeRoute = window.LabverseApp.getCurrentRoute();
            if (activeRoute === 'results') renderResultsModule(state.resultsPage);
            else renderLaboratoryWorkspace(state.labPage);
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 11. RESULT APPROVAL MODULE
    // =========================================================================
    async function renderApprovalsModule(page = 1) {
        state.approvalsPage = page;
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Result Approvals',
            title: 'Supervisor Verification & Result Approval Queue',
            description: 'Independent clinical verification of submitted laboratory results. Review abnormal/critical flags, approve for report release, or request bench corrections.',
        }) + `
            <div class="lv-filter-bar">
                <form class="row g-2 align-items-end" onsubmit="event.preventDefault(); LabverseLabOps.filterApprovals();">
                    <div class="col-12 col-md-9">
                        <label for="app-search-input" class="form-label">Search Submitted Results Awaiting Verification</label>
                        <input type="search" id="app-search-input" class="form-control form-control-sm" placeholder="Order #, Patient Name, Barcode, or Test Name..." value="${UI.esc(state.approvalsSearch)}">
                    </div>
                    <div class="col-12 col-md-3">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Search Approval Queue</button>
                    </div>
                </form>
            </div>
            <div id="lv-approvals-table-wrap" class="lv-card">
                ${UI.loadingSpinner('Loading submitted results awaiting verification...')}
            </div>
        `;
        UI.refreshIcons();

        try {
            const params = new URLSearchParams({
                page: state.approvalsPage,
                status: 'submitted',
                search: state.approvalsSearch,
            });
            const data = await UI.api(`/lab/items?${params.toString()}`);
            const rows = data.items?.data || [];
            const wrap = document.getElementById('lv-approvals-table-wrap');
            if (!wrap) return;

            if (rows.length === 0) {
                wrap.innerHTML = UI.emptyState({
                    icon: 'shield-check',
                    title: 'All submitted results have been verified',
                    message: 'There are currently no pending test results awaiting supervisor approval.',
                });
                UI.refreshIcons();
                return;
            }

            wrap.innerHTML = `
                <div class="lv-table-container">
                    <table class="lv-table">
                        <thead>
                            <tr>
                                <th>Order & Priority</th>
                                <th>Patient</th>
                                <th>Diagnostic Test</th>
                                <th>Clinical Flags</th>
                                <th>Submitted By Scientist</th>
                                <th>Submitted At</th>
                                <th class="text-end">Review Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map(item => `
                                <tr class="${item.has_critical_values ? 'table-danger' : ''}">
                                    <td>
                                        <span class="font-mono fw-semibold text-primary d-block">${UI.esc(item.order?.order_number)}</span>
                                        <div class="mt-1">${UI.statusBadge(item.order?.priority)}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">${UI.esc(item.order?.patient?.full_name)}</div>
                                        <div class="small text-secondary">${UI.esc(item.order?.patient?.gender)}, ${item.order?.patient?.age ?? '—'} yrs</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">${UI.esc(item.test?.name)}</div>
                                        <div class="small text-secondary font-mono">${UI.esc(item.test?.code)} • Sample: ${UI.esc(item.sample?.barcode || '—')}</div>
                                    </td>
                                    <td>
                                        ${item.has_critical_values ? `<span class="lv-badge lv-badge-critical">CRITICAL VALUE ALERT</span>` : item.has_abnormal_values ? `<span class="lv-badge lv-badge-warning">Abnormal Findings</span>` : `<span class="lv-badge lv-badge-success">Within Reference</span>`}
                                    </td>
                                    <td>
                                        <div class="fw-medium">${UI.esc(item.entered_by_user?.name || 'Scientist')}</div>
                                        <div class="small text-secondary font-mono">${UI.esc(item.entered_by_user?.staff_id || '')}</div>
                                    </td>
                                    <td>${UI.formatDateTime(item.submitted_at)}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="LabverseLabOps.openApprovalReviewDrawer(${item.id})">
                                            <i data-lucide="shield-check" style="width:14px;height:14px;"></i> Review & Verify
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ${UI.pagination(data.items, 'LabverseLabOps.renderApprovalsModule')}
            `;
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function filterApprovals() {
        state.approvalsSearch = document.getElementById('app-search-input')?.value || '';
        renderApprovalsModule(1);
    }

    async function openApprovalReviewDrawer(orderItemId) {
        window.LabverseApp.openDrawer(UI.loadingSpinner('Loading submitted results for supervisor verification...'), true);
        try {
            const data = await UI.api(`/lab/items/${orderItemId}`);
            const item = data.item;
            const prev = data.previous_results || [];
            const patient = item.order?.patient;

            const html = `
                <div class="lv-drawer-header">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="font-mono fw-bold text-primary">${UI.esc(item.order?.order_number)}</span>
                            ${UI.statusBadge(item.order?.priority)}
                            ${item.has_critical_values ? `<span class="lv-badge lv-badge-critical">CRITICAL VALUE</span>` : ''}
                        </div>
                        <h5 class="mb-0 fw-bold mt-1">Supervisor Verification — ${UI.esc(item.test?.name)}</h5>
                    </div>
                    <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDrawer()"></button>
                </div>

                <div class="lv-drawer-body">
                    <div class="row g-3 mb-3 small">
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="fw-bold text-uppercase text-secondary mb-1">Patient & Clinical Context</div>
                                <div><strong>Patient:</strong> ${UI.esc(patient?.full_name)} (<span class="font-mono">${UI.esc(patient?.patient_code)}</span>)</div>
                                <div><strong>Sex / Age:</strong> ${UI.esc(patient?.gender)} • ${patient?.age ?? '—'} yrs</div>
                                <div><strong>Clinical Notes:</strong> ${UI.esc(item.order?.clinical_notes || 'Routine')}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="fw-bold text-uppercase text-secondary mb-1">Bench Scientist Submission</div>
                                <div><strong>Submitted By:</strong> ${UI.esc(item.entered_by_user?.name || 'Scientist')} (${UI.esc(item.entered_by_user?.staff_id || '')})</div>
                                <div><strong>Submitted Timestamp:</strong> ${UI.formatDateTime(item.submitted_at)}</div>
                                <div><strong>Bench QC Notes:</strong> ${UI.esc(item.technician_notes || 'None provided')}</div>
                            </div>
                        </div>
                    </div>

                    <div class="lv-card mb-4">
                        <div class="lv-card-header">
                            <h6 class="lv-card-title">Submitted Parameter Results</h6>
                        </div>
                        <div class="lv-table-container">
                            <table class="lv-table">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Submitted Result</th>
                                        <th>Unit</th>
                                        <th>Reference Interval</th>
                                        <th>Flag</th>
                                        <th>Technician Remark</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${(item.results || []).map(r => `
                                        <tr class="${r.flag.includes('critical') ? 'table-danger' : ''}">
                                            <td class="fw-semibold">${UI.esc(r.parameter?.name || '')}</td>
                                            <td class="font-mono fw-bold fs-6">${UI.esc(r.result_value)}</td>
                                            <td class="font-mono small text-secondary">${UI.esc(r.unit || '—')}</td>
                                            <td class="font-mono small">${UI.esc(r.reference_range || '—')}</td>
                                            <td>${UI.statusBadge(r.flag)}</td>
                                            <td class="small text-secondary">${UI.esc(r.remarks || '—')}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    ${prev.length > 0 ? `
                        <div class="mb-3">
                            <h6 class="fw-bold small text-uppercase text-secondary">Previous Historical Approved Results for Delta Check</h6>
                            <div class="small text-secondary">Found ${prev.length} prior approved run(s) for this patient.</div>
                        </div>
                    ` : ''}

                    <div class="p-3 border rounded bg-light">
                        <div class="mb-3">
                            <label for="rev-comments" class="form-label fw-semibold">Supervisor Review / Verification Comments (Required if Rejecting or Requesting Correction)</label>
                            <textarea id="rev-comments" class="form-control form-control-sm" rows="2" placeholder="Enter verification remarks or instructions for repeat assay..."></textarea>
                        </div>
                        <div>
                            <label for="rev-pathologist-comment" class="form-label fw-semibold">Clinical Interpretation for Final Patient Report (Optional)</label>
                            <textarea id="rev-pathologist-comment" class="form-control form-control-sm" rows="2" placeholder="Pathologist / Supervisor clinical summary to appear on the released report..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="lv-drawer-footer">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDrawer()">Cancel</button>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-danger" onclick="LabverseLabOps.submitApprovalDecision(${item.id}, 'reject')">
                            <i data-lucide="x-circle" style="width:15px;height:15px;"></i> Reject Run
                        </button>
                        <button type="button" class="btn btn-warning" onclick="LabverseLabOps.submitApprovalDecision(${item.id}, 'request_correction')">
                            <i data-lucide="rotate-ccw" style="width:15px;height:15px;"></i> Request Correction
                        </button>
                        <button type="button" class="btn btn-success" onclick="LabverseLabOps.submitApprovalDecision(${item.id}, 'approve')">
                            <i data-lucide="check-circle-2" style="width:15px;height:15px;"></i> Approve & Authorize Result
                        </button>
                    </div>
                </div>
            `;
            window.LabverseApp.openDrawer(html, true);
        } catch (err) {
            UI.toast(err.message, 'error');
            window.LabverseApp.closeDrawer();
        }
    }

    async function submitApprovalDecision(orderItemId, decision) {
        const reviewerComments = document.getElementById('rev-comments')?.value?.trim() || '';
        const pathologistComment = document.getElementById('rev-pathologist-comment')?.value?.trim() || '';

        if ((decision === 'reject' || decision === 'request_correction') && !reviewerComments) {
            UI.toast('Please provide review comments explaining why this result is being returned.', 'warning');
            return;
        }

        try {
            const res = await UI.api(`/lab/items/${orderItemId}/review`, {
                method: 'POST',
                body: {
                    decision,
                    reviewer_comments: reviewerComments || null,
                    pathologist_comment: pathologistComment || null,
                },
            });
            UI.toast(res.message, 'success');
            window.LabverseApp.closeDrawer();
            renderApprovalsModule(state.approvalsPage);
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 12. MEDICAL LABORATORY REPORT GENERATION MODULE
    // =========================================================================
    async function renderReportsModule(page = 1) {
        state.reportsPage = page;
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Medical Reports',
            title: 'Authorized Diagnostic Laboratory Reports',
            description: 'Preview, print, and download verified medical laboratory reports with ISO 15189 clinical formatting.',
        }) + `
            <div class="lv-filter-bar">
                <form class="row g-2 align-items-end" onsubmit="event.preventDefault(); LabverseLabOps.filterReports();">
                    <div class="col-12 col-md-9">
                        <label for="rep-search-input" class="form-label">Search Released Reports</label>
                        <input type="search" id="rep-search-input" class="form-control form-control-sm" placeholder="Report # (RPT-...), Order #, Verification Code, or Patient Name..." value="${UI.esc(state.reportsSearch)}">
                    </div>
                    <div class="col-12 col-md-3">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Search Reports</button>
                    </div>
                </form>
            </div>
            <div id="lv-reports-table-wrap" class="lv-card">
                ${UI.loadingSpinner('Loading released laboratory reports...')}
            </div>
        `;
        UI.refreshIcons();

        try {
            const params = new URLSearchParams({
                page: state.reportsPage,
                search: state.reportsSearch,
            });
            const data = await UI.api(`/reports?${params.toString()}`);
            const rows = data.data || [];
            const wrap = document.getElementById('lv-reports-table-wrap');
            if (!wrap) return;

            if (rows.length === 0) {
                wrap.innerHTML = UI.emptyState({
                    icon: 'file-check-2',
                    title: 'No released laboratory reports found',
                    message: 'Reports are automatically generated once all tests on an order receive supervisor approval.',
                });
                UI.refreshIcons();
                return;
            }

            wrap.innerHTML = `
                <div class="lv-table-container">
                    <table class="lv-table">
                        <thead>
                            <tr>
                                <th>Report Number</th>
                                <th>Order #</th>
                                <th>Patient</th>
                                <th>Investigations</th>
                                <th>Approved By</th>
                                <th>Released Date</th>
                                <th>Verification Hash</th>
                                <th class="text-end">Report Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map(r => `
                                <tr>
                                    <td class="font-mono fw-bold text-primary">${UI.esc(r.report_number)}</td>
                                    <td class="font-mono">${UI.esc(r.order?.order_number || '—')}</td>
                                    <td>
                                        <div class="fw-semibold">${UI.esc(r.patient?.full_name)}</div>
                                        <div class="small text-secondary font-mono">${UI.esc(r.patient?.patient_code)} • ${UI.esc(r.patient?.gender)}, ${r.patient?.age ?? '—'} yrs</div>
                                    </td>
                                    <td>
                                        ${(r.order?.items || []).map(i => `<span class="lv-badge lv-badge-neutral me-1">${UI.esc(i.test?.code)}</span>`).join('')}
                                    </td>
                                    <td>
                                        <div class="fw-medium">${UI.esc(r.approved_by_user?.name || 'Pathologist')}</div>
                                        <div class="small text-secondary">${UI.esc(r.approved_by_user?.license_number || '')}</div>
                                    </td>
                                    <td>${UI.formatDateTime(r.released_at)}</td>
                                    <td><span class="font-mono small lv-badge lv-badge-teal">${UI.esc(r.verification_code)}</span></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" class="btn btn-light-surface btn-sm" title="Send Report via SMS or Email" onclick="LabverseLabOps.openSendReportNotificationModal(${r.id}, '${UI.esc(r.report_number)}', '${UI.esc(r.patient?.full_name || '')}', '${UI.esc(r.patient?.phone || '')}', '${UI.esc(r.patient?.email || '')}')">
                                                <i data-lucide="send" style="width:14px;height:14px;"></i> SMS / Email
                                            </button>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="LabverseLabOps.openReportPreview(${r.id})">
                                                <i data-lucide="printer" style="width:14px;height:14px;"></i> Preview / Print PDF
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ${UI.pagination(data, 'LabverseLabOps.renderReportsModule')}
            `;
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function filterReports() {
        state.reportsSearch = document.getElementById('rep-search-input')?.value || '';
        renderReportsModule(1);
    }

    async function openReportPreview(reportId) {
        window.LabverseApp.openDrawer(UI.loadingSpinner('Formatting official clinical laboratory report...'), true);
        try {
            const data = await UI.api(`/reports/${reportId}`);
            const r = data.report;
            const fac = data.facility || {};
            const p = r.patient || {};
            const o = r.order || {};
            const b = r.branch || {};
            const items = o.items || [];

            const html = `
                <div class="lv-drawer-header lv-no-print">
                    <div>
                        <span class="font-mono fw-bold text-primary">${UI.esc(r.report_number)}</span>
                        <h5 class="mb-0 fw-bold">Official Medical Laboratory Report</h5>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="LabverseLabOps.openSendReportNotificationModal(${r.id}, '${UI.esc(r.report_number)}', '${UI.esc(p.full_name || '')}', '${UI.esc(p.phone || '')}', '${UI.esc(p.email || '')}')">
                            <i data-lucide="send" style="width:15px;height:15px;"></i> Send via SMS / Email
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                            <i data-lucide="printer" style="width:15px;height:15px;"></i> Print / Save as PDF
                        </button>
                        <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDrawer()"></button>
                    </div>
                </div>

                <div class="lv-drawer-body bg-light">
                    <div class="lv-report-sheet shadow-sm">
                        <!-- Report Header: Facility & Branch Branding -->
                        <div class="lv-report-header d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <div class="lv-brand-mark" style="width:46px;height:46px;font-size:1.2rem;background:var(--lv-primary);">LV</div>
                                <div>
                                    <h4 class="fw-bold mb-0" style="color:var(--lv-primary);letter-spacing:0.02em;">${UI.esc(fac.facility_name || 'LABVERSE DIAGNOSTIC & REFERENCE LABORATORIES')}</h4>
                                    <div class="small fw-semibold text-secondary">${UI.esc(b.name || 'Accra Central Reference Laboratory')} (${UI.esc(b.code || 'LV-ACC-01')})</div>
                                    <div class="small text-secondary">${UI.esc(b.address || '14 Castle Road, West Ridge Medical Enclave, Accra')} • Tel: ${UI.esc(b.phone || fac.facility_phone || '')}</div>
                                    <div class="small text-secondary">${UI.esc(fac.facility_accreditation || 'ISO 15189 Medical Laboratory')}</div>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="lv-badge lv-badge-success mb-1">FINAL VERIFIED REPORT</span>
                                <div class="font-mono fw-bold">${UI.esc(r.report_number)}</div>
                                <div class="small text-secondary font-mono">Verify: ${UI.esc(r.verification_code)}</div>
                            </div>
                        </div>

                        <!-- Patient, Requisition & Specimen Metadata Grid -->
                        <div class="row g-2 mb-4 p-3 rounded border bg-light small">
                            <div class="col-md-4">
                                <div class="mb-1"><strong>Patient Name:</strong> ${UI.esc(p.full_name)}</div>
                                <div class="mb-1"><strong>Patient ID:</strong> <span class="font-mono">${UI.esc(p.patient_code)}</span></div>
                                <div class="mb-1"><strong>Sex / Age:</strong> ${UI.esc(p.gender)} / ${p.age ?? '—'} Years (DOB: ${UI.formatDate(p.date_of_birth)})</div>
                                <div><strong>Phone:</strong> <span class="font-mono">${UI.esc(p.phone)}</span></div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-1"><strong>Order Number:</strong> <span class="font-mono">${UI.esc(o.order_number)}</span></div>
                                <div class="mb-1"><strong>Referring Clinician:</strong> ${UI.esc(o.referring_doctor || 'Walk-in / Self')}</div>
                                <div class="mb-1"><strong>Facility:</strong> ${UI.esc(o.referring_facility || b.name)}</div>
                                <div><strong>Clinical Indication:</strong> ${UI.esc(o.diagnosis || o.clinical_notes || 'Diagnostic Evaluation')}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-1"><strong>Specimens:</strong> ${(o.samples || []).map(s => UI.esc(s.sample_type)).join(', ')}</div>
                                <div class="mb-1"><strong>Sample Barcode(s):</strong> <span class="font-mono">${(o.samples || []).map(s => UI.esc(s.barcode)).join(', ')}</span></div>
                                <div class="mb-1"><strong>Collected At:</strong> ${UI.formatDateTime(o.samples?.[0]?.collected_at || o.created_at)}</div>
                                <div><strong>Report Released:</strong> ${UI.formatDateTime(r.released_at)}</div>
                            </div>
                        </div>

                        <!-- Test Panels & Analytical Results -->
                        ${items.map(item => `
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center px-3 py-2 rounded-top" style="background:#0F294A;color:#FFFFFF;">
                                    <div>
                                        <span class="fw-bold">${UI.esc(item.test?.name)}</span>
                                        <span class="small opacity-75 ms-2">(${UI.esc(item.test?.category?.name || 'Laboratory')})</span>
                                    </div>
                                    <span class="small font-mono opacity-75">Method: ${UI.esc(item.test?.methodology || 'Standard')}</span>
                                </div>
                                <table class="lv-table border">
                                    <thead>
                                        <tr>
                                            <th style="width:34%;">Investigation / Parameter</th>
                                            <th style="width:20%;">Result</th>
                                            <th style="width:14%;">Units</th>
                                            <th style="width:20%;">Reference Interval</th>
                                            <th style="width:12%;">Flag</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${(item.results || []).map(resRow => `
                                            <tr>
                                                <td class="fw-medium">${UI.esc(resRow.parameter?.name || '')}</td>
                                                <td class="font-mono fw-bold ${resRow.flag !== 'normal' ? 'text-danger' : ''}">${UI.esc(resRow.result_value)}</td>
                                                <td class="font-mono small text-secondary">${UI.esc(resRow.unit || '—')}</td>
                                                <td class="font-mono small">${UI.esc(resRow.reference_range || '—')}</td>
                                                <td>${UI.statusBadge(resRow.flag)}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                                ${item.technician_notes || item.reviewer_comments ? `
                                    <div class="px-3 py-2 border border-top-0 bg-light small">
                                        ${item.reviewer_comments ? `<div><strong>Assay Review Note:</strong> ${UI.esc(item.reviewer_comments)}</div>` : ''}
                                    </div>
                                ` : ''}
                            </div>
                        `).join('')}

                        <!-- Pathologist / Clinical Summary -->
                        <div class="p-3 rounded border bg-light mb-4 small">
                            <div class="fw-bold text-uppercase text-secondary mb-1">Laboratory Comments & Pathologist Interpretation</div>
                            <div>${UI.esc(r.pathologist_comment || 'All diagnostic assays performed and verified under internal quality control.')}</div>
                            ${r.clinical_interpretation ? `<div class="mt-1"><strong>Interpretation:</strong> ${UI.esc(r.clinical_interpretation)}</div>` : ''}
                        </div>

                        <!-- Sign-off & Verification Footer -->
                        <div class="row g-3 pt-3 border-top small">
                            <div class="col-6">
                                <div class="text-secondary">Performed By Medical Laboratory Scientist:</div>
                                <div class="fw-bold mt-1">${UI.esc(items[0]?.entered_by_user?.name || 'Authorized Medical Lab Scientist')}</div>
                                <div class="text-secondary font-mono">${UI.esc(items[0]?.entered_by_user?.license_number || 'AHPC Licensed')}</div>
                            </div>
                            <div class="col-6 text-end">
                                <div class="text-secondary">Verified & Authorized For Release By:</div>
                                <div class="fw-bold mt-1">${UI.esc(r.approved_by_user?.name || 'Consultant Pathologist / Lab Supervisor')}</div>
                                <div class="text-secondary font-mono">${UI.esc(r.approved_by_user?.job_title || '')} • ${UI.esc(r.approved_by_user?.license_number || '')}</div>
                            </div>
                        </div>

                        <div class="mt-4 pt-2 border-top text-center text-secondary" style="font-size:0.74rem;">
                            ${UI.esc(fac.report_footer_disclaimer || 'Confidential Medical Laboratory Report — Valid only with digital verification code.')}
                        </div>
                    </div>
                </div>

                <div class="lv-drawer-footer lv-no-print">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDrawer()">Close Preview</button>
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i data-lucide="printer" style="width:15px;height:15px;"></i> Print / Export PDF Report
                    </button>
                </div>
            `;
            window.LabverseApp.openDrawer(html, true);
        } catch (err) {
            UI.toast(err.message, 'error');
            window.LabverseApp.closeDrawer();
        }
    }

    function openSendReportNotificationModal(reportId, reportNumber, patientName, phone, email) {
        const html = `
            <form onsubmit="event.preventDefault(); LabverseLabOps.submitSendReportNotification(${reportId});">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i data-lucide="send" style="width:18px;height:18px;color:var(--lv-primary);"></i> Dispatch Report Notification — ${UI.esc(reportNumber)}</h5>
                    <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDialog()"></button>
                </div>
                <div class="p-4">
                    <div class="p-3 rounded bg-light border small mb-3">
                        <strong>Patient:</strong> ${UI.esc(patientName)}<br>
                        <span class="text-secondary">Dispatch official report readiness alert via SMS Gateway and/or SMTP Email with verification code.</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Delivery Channel(s)</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rep-notify-sms" checked>
                                <label class="form-check-label fw-medium" for="rep-notify-sms">SMS Notification</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rep-notify-email" ${email ? 'checked' : ''}>
                                <label class="form-check-label fw-medium" for="rep-notify-email">SMTP Email Summary</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="rep-notify-phone-val" class="form-label">Recipient Mobile Phone (SMS)</label>
                        <input type="tel" id="rep-notify-phone-val" class="form-control font-mono" value="${UI.esc(phone)}" placeholder="+233 24 000 0000">
                    </div>
                    <div class="mb-2">
                        <label for="rep-notify-email-val" class="form-label">Recipient Email Address (SMTP)</label>
                        <input type="email" id="rep-notify-email-val" class="form-control" value="${UI.esc(email)}" placeholder="patient@example.com">
                    </div>
                </div>
                <div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDialog()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="send" style="width:15px;height:15px;"></i> Dispatch Notification Now
                    </button>
                </div>
            </form>
        `;
        window.LabverseApp.openDialog(html);
        UI.refreshIcons();
    }

    async function submitSendReportNotification(reportId) {
        const channels = [];
        if (document.getElementById('rep-notify-sms')?.checked) channels.push('sms');
        if (document.getElementById('rep-notify-email')?.checked) channels.push('email');

        if (channels.length === 0) {
            UI.toast('Please select at least one channel (SMS or Email).', 'warning');
            return;
        }

        const phone = document.getElementById('rep-notify-phone-val')?.value?.trim() || null;
        const email = document.getElementById('rep-notify-email-val')?.value?.trim() || null;

        try {
            const res = await UI.api(`/reports/${reportId}/notify`, {
                method: 'POST',
                body: { channels, phone, email },
            });
            UI.toast(res.message, 'success');
            window.LabverseApp.closeDialog();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    return {
        renderLaboratoryWorkspace,
        applyLabFilters,
        setLabQueueFilter,
        setLabPriorityFilter,
        renderResultsModule,
        filterResultsModule,
        openResultEntryModal,
        liveEvaluateRow,
        submitResultsForm,
        renderApprovalsModule,
        filterApprovals,
        openApprovalReviewDrawer,
        submitApprovalDecision,
        renderReportsModule,
        filterReports,
        openReportPreview,
        openSendReportNotificationModal,
        submitSendReportNotification,
    };
})();

