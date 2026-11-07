/**
 * ============================================================================
 * LABVERSE — Phase 6: Administration & Governance Modules
 * (14. Inventory, 15. Multi-Branch, 16. Staff & Custom RBAC, Test Catalogue, Settings)
 * ============================================================================
 */
window.LabverseAdminOps = (function () {
    const UI = window.LabverseUI;

    const state = {
        invSearch: '',
        invCategory: '',
        invStockStatus: '',
        staffData: [],
        rolesData: [],
        permissionsByCategory: {},
        branchesData: [],
        catalogueCategories: [],
        catalogueTests: [],
        catFilterId: '',
        catSearch: '',
    };

    // =========================================================================
    // 14. INVENTORY MANAGEMENT MODULE
    // =========================================================================
    async function renderInventory() {
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        const canManage = window.LabverseApp.hasPermission('manage_inventory');

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Inventory & Reagents',
            title: 'Laboratory Inventory, Reagents & Consumables',
            description: 'Monitor stock levels, batch numbers, expiry dates, low-stock alerts, and audit-logged stock movements.',
            actionsHtml: canManage ? `
                <button type="button" class="btn btn-primary" onclick="LabverseAdminOps.openNewInventoryModal()">
                    <i data-lucide="package-plus" style="width:16px;height:16px;"></i> Register Reagent / Consumable
                </button>
            ` : '',
        }) + `<div id="lv-inventory-body">${UI.loadingSpinner('Loading reagent & consumable inventory...')}</div>`;
        UI.refreshIcons();

        try {
            const params = new URLSearchParams({
                search: state.invSearch,
                category: state.invCategory,
                stock_status: state.invStockStatus,
            });
            const data = await UI.api(`/inventory?${params.toString()}`);
            const sum = data.summary || {};
            const items = data.items || [];
            const movements = data.movements || [];

            const wrap = document.getElementById('lv-inventory-body');
            if (!wrap) return;

            wrap.innerHTML = `
                <div class="row g-3 mb-3">
                    <div class="col-6 col-lg-3">${UI.statCard({ label: 'Total Tracked SKUs', value: sum.total_items ?? 0, meta: `${sum.reagents_count ?? 0} reagents • ${sum.consumables_count ?? 0} consumables`, icon: 'boxes', tone: 'primary' })}</div>
                    <div class="col-6 col-lg-3">${UI.statCard({ label: 'Low Stock Alerts', value: sum.low_stock_count ?? 0, icon: 'alert-triangle', tone: 'warning', onClickAttr: `onclick="LabverseAdminOps.setInvStatusFilter('low_stock')"` })}</div>
                    <div class="col-6 col-lg-3">${UI.statCard({ label: 'Expiring Within 45 Days', value: sum.expiring_soon_count ?? 0, icon: 'calendar-clock', tone: 'danger', onClickAttr: `onclick="LabverseAdminOps.setInvStatusFilter('expiring_soon')"` })}</div>
                    <div class="col-6 col-lg-3">${UI.statCard({ label: 'Stock Valuation', value: UI.formatCurrency(sum.total_valuation), icon: 'coins', tone: 'success' })}</div>
                </div>

                <div class="lv-filter-bar">
                    <form class="row g-2 align-items-end" onsubmit="event.preventDefault(); LabverseAdminOps.filterInventory();">
                        <div class="col-12 col-md-4">
                            <label for="inv-search" class="form-label">Search SKU, Reagent Name, Batch or Supplier</label>
                            <input type="search" id="inv-search" class="form-control form-control-sm" placeholder="e.g. Sysmex, Cobas, Vacutainer, LOT-..." value="${UI.esc(state.invSearch)}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="inv-cat" class="form-label">Category</label>
                            <select id="inv-cat" class="form-select form-select-sm">
                                <option value="">All Categories</option>
                                <option value="reagent" ${state.invCategory === 'reagent' ? 'selected' : ''}>Reagents</option>
                                <option value="collection_tube" ${state.invCategory === 'collection_tube' ? 'selected' : ''}>Collection Tubes</option>
                                <option value="kit" ${state.invCategory === 'kit' ? 'selected' : ''}>Rapid Diagnostic Kits</option>
                                <option value="calibrator" ${state.invCategory === 'calibrator' ? 'selected' : ''}>Calibrators & Controls</option>
                                <option value="consumable" ${state.invCategory === 'consumable' ? 'selected' : ''}>General Consumables</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="inv-status" class="form-label">Stock Health</label>
                            <select id="inv-status" class="form-select form-select-sm">
                                <option value="">All Stock Levels</option>
                                <option value="low_stock" ${state.invStockStatus === 'low_stock' ? 'selected' : ''}>Low Stock Only</option>
                                <option value="out_of_stock" ${state.invStockStatus === 'out_of_stock' ? 'selected' : ''}>Out of Stock</option>
                                <option value="expiring_soon" ${state.invStockStatus === 'expiring_soon' ? 'selected' : ''}>Expiring Soon</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                            <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseAdminOps.resetInvFilter()">Reset</button>
                        </div>
                    </form>
                </div>

                <div class="lv-card mb-4">
                    <div class="lv-card-header">
                        <h2 class="lv-card-title">Reagents & Laboratory Consumables Register</h2>
                    </div>
                    <div class="lv-table-container">
                        <table class="lv-table">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Item Name & Category</th>
                                    <th>Batch & Expiry</th>
                                    <th>Storage / Supplier</th>
                                    <th>On Hand / Reorder</th>
                                    <th>Status</th>
                                    <th class="text-end">Stock Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${items.map(it => `
                                    <tr>
                                        <td class="font-mono fw-semibold text-primary">${UI.esc(it.sku)}</td>
                                        <td>
                                            <div class="fw-semibold">${UI.esc(it.name)}</div>
                                            <div class="small text-secondary text-capitalize">${UI.esc((it.category || '').replace('_', ' '))} • ${UI.esc(it.branch?.name || '')}</div>
                                        </td>
                                        <td class="small">
                                            <div class="font-mono">${UI.esc(it.batch_number || '—')}</div>
                                            <div class="${it.is_expiring_soon ? 'text-danger fw-bold' : 'text-secondary'}">Exp: ${UI.formatDate(it.expiry_date)}</div>
                                        </td>
                                        <td class="small">
                                            <div>${UI.esc(it.storage_condition || 'Room Temp')} (${UI.esc(it.location_bin || 'Store')})</div>
                                            <div class="text-secondary">${UI.esc(it.supplier_name || '—')}</div>
                                        </td>
                                        <td>
                                            <span class="font-mono fw-bold fs-6 ${it.quantity_on_hand <= it.reorder_level ? 'text-danger' : 'text-dark'}">${it.quantity_on_hand}</span>
                                            <span class="small text-secondary">${UI.esc(it.unit)} (Min: ${it.reorder_level})</span>
                                        </td>
                                        <td>
                                            ${UI.statusBadge(it.stock_status)}
                                            ${it.is_expiring_soon ? `<span class="lv-badge lv-badge-danger ms-1">Near Expiry</span>` : ''}
                                        </td>
                                        <td class="text-end">
                                            ${canManage ? `
                                                <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseAdminOps.openStockAdjustmentModal(${it.id}, '${UI.esc(it.name)}', ${it.quantity_on_hand}, '${UI.esc(it.unit)}')">
                                                    <i data-lucide="arrow-up-down" style="width:14px;height:14px;"></i> Adjust Stock
                                                </button>
                                            ` : ''}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lv-card">
                    <div class="lv-card-header">
                        <h2 class="lv-card-title">Recent Stock Movements & Audit Ledger</h2>
                    </div>
                    <div class="lv-table-container">
                        <table class="lv-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Item</th>
                                    <th>Movement Type</th>
                                    <th>Qty Change</th>
                                    <th>Balance</th>
                                    <th>Reason & Reference</th>
                                    <th>Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${movements.map(m => `
                                    <tr>
                                        <td class="small">${UI.formatDateTime(m.created_at)}</td>
                                        <td class="fw-medium">${UI.esc(m.item?.name || '—')} <span class="font-mono small text-secondary">(${UI.esc(m.item?.sku || '')})</span></td>
                                        <td><span class="lv-badge ${m.movement_type === 'stock_in' ? 'lv-badge-success' : 'lv-badge-warning'}">${UI.esc(m.movement_type.replace('_', ' '))}</span></td>
                                        <td class="font-mono fw-semibold">${m.quantity} ${UI.esc(m.item?.unit || '')}</td>
                                        <td class="font-mono small">${m.previous_quantity} → <strong>${m.new_quantity}</strong></td>
                                        <td class="small">${UI.esc(m.reason)} <span class="font-mono text-secondary">(${UI.esc(m.reference_number || '')})</span></td>
                                        <td class="small">${UI.esc(m.performed_by_user?.name || 'Staff')}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function filterInventory() {
        state.invSearch = document.getElementById('inv-search')?.value || '';
        state.invCategory = document.getElementById('inv-cat')?.value || '';
        state.invStockStatus = document.getElementById('inv-status')?.value || '';
        renderInventory();
    }

    function setInvStatusFilter(st) {
        state.invStockStatus = st;
        renderInventory();
    }

    function resetInvFilter() {
        state.invSearch = '';
        state.invCategory = '';
        state.invStockStatus = '';
        renderInventory();
    }

    function openNewInventoryModal() {
        const html = `
            <form method="POST" onsubmit="event.preventDefault(); LabverseAdminOps.submitNewInventoryItem(this);">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Register New Reagent or Consumable</h5>
                    <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDialog()"></button>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="inv-new-sku" class="form-label">Item SKU <span class="text-danger">*</span></label>
                            <input type="text" id="inv-new-sku" name="sku" class="form-control font-mono" required placeholder="REG-CHEM-010">
                        </div>
                        <div class="col-md-8">
                            <label for="inv-new-name" class="form-label">Item / Reagent Name <span class="text-danger">*</span></label>
                            <input type="text" id="inv-new-name" name="name" class="form-control" required placeholder="e.g. Cobas Glucose HK Reagent Cassette">
                        </div>
                        <div class="col-md-4">
                            <label for="inv-new-cat" class="form-label">Category <span class="text-danger">*</span></label>
                            <select id="inv-new-cat" name="category" class="form-select" required>
                                <option value="reagent">Reagent</option>
                                <option value="collection_tube">Collection Tube</option>
                                <option value="kit">Diagnostic Kit</option>
                                <option value="calibrator">Calibrator / Control</option>
                                <option value="consumable">Consumable</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="inv-new-unit" class="form-label">Unit of Measure <span class="text-danger">*</span></label>
                            <input type="text" id="inv-new-unit" name="unit" class="form-control" required placeholder="Pack, Box, Cassette, Bottle">
                        </div>
                        <div class="col-md-4">
                            <label for="inv-new-cost" class="form-label">Unit Cost (GH₵) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" id="inv-new-cost" name="unit_cost" class="form-control font-mono" required value="120.00">
                        </div>
                        <div class="col-md-4">
                            <label for="inv-new-qty" class="form-label">Opening Quantity <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" id="inv-new-qty" name="quantity_on_hand" class="form-control font-mono" required value="10">
                        </div>
                        <div class="col-md-4">
                            <label for="inv-new-reorder" class="form-label">Reorder Alert Level <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" id="inv-new-reorder" name="reorder_level" class="form-control font-mono" required value="5">
                        </div>
                        <div class="col-md-4">
                            <label for="inv-new-exp" class="form-label">Expiry Date</label>
                            <input type="date" id="inv-new-exp" name="expiry_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="inv-new-sup" class="form-label">Supplier Name</label>
                            <input type="text" id="inv-new-sup" name="supplier_name" class="form-control" placeholder="Roche / MedLab Ghana">
                        </div>
                        <div class="col-md-6">
                            <label for="inv-new-batch" class="form-label">Batch / Lot Number</label>
                            <input type="text" id="inv-new-batch" name="batch_number" class="form-control font-mono" placeholder="LOT-2026-XX">
                        </div>
                    </div>
                </div>
                <div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDialog()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Inventory Item</button>
                </div>
            </form>
        `;
        window.LabverseApp.openDialog(html);
    }

    async function submitNewInventoryItem(form) {
        const payload = Object.fromEntries(new FormData(form).entries());
        try {
            const res = await UI.api('/inventory', { method: 'POST', body: payload });
            UI.toast(res.message, 'success');
            window.LabverseApp.closeDialog();
            renderInventory();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function openStockAdjustmentModal(itemId, itemName, currentQty, unit) {
        const html = `
            <form method="POST" onsubmit="event.preventDefault(); LabverseAdminOps.submitStockAdjustment(${itemId}, this);">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold">Record Stock Movement</h5>
                        <div class="small text-secondary">${UI.esc(itemName)} (Current: <strong>${currentQty} ${UI.esc(unit)}</strong>)</div>
                    </div>
                    <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDialog()"></button>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="adj-type" class="form-label">Movement Type <span class="text-danger">*</span></label>
                            <select id="adj-type" name="movement_type" class="form-select" required>
                                <option value="stock_in">Stock In (New Delivery / GRN)</option>
                                <option value="consumption">Bench Consumption / Issue</option>
                                <option value="adjustment">Physical Stock Count Adjustment</option>
                                <option value="expired_writeoff">Expired / Damaged Write-off</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="adj-qty" class="form-label">Quantity (${UI.esc(unit)}) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" id="adj-qty" name="quantity" class="form-control font-mono" required value="1">
                        </div>
                        <div class="col-12">
                            <label for="adj-reason" class="form-label">Reason / Justification <span class="text-danger">*</span></label>
                            <input type="text" id="adj-reason" name="reason" class="form-control" required placeholder="e.g. Issued to Haematology bench / Supplier delivery GRN...">
                        </div>
                        <div class="col-12">
                            <label for="adj-ref" class="form-label">Delivery Note / Reference #</label>
                            <input type="text" id="adj-ref" name="reference_number" class="form-control font-mono" placeholder="GRN-2026-...">
                        </div>
                    </div>
                </div>
                <div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDialog()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Stock Movement</button>
                </div>
            </form>
        `;
        window.LabverseApp.openDialog(html);
    }

    async function submitStockAdjustment(itemId, form) {
        const payload = Object.fromEntries(new FormData(form).entries());
        try {
            const res = await UI.api(`/inventory/${itemId}/adjust`, { method: 'POST', body: payload });
            UI.toast(res.message, 'success');
            window.LabverseApp.closeDialog();
            renderInventory();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 15. MULTI-BRANCH MANAGEMENT MODULE
    // =========================================================================
    async function renderBranches() {
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        const canManage = window.LabverseApp.hasPermission('manage_branches');

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Multi-Branch Network',
            title: 'Laboratory Branches & Facility Network',
            description: 'Inspect branch operations, staff counts, patient volumes, and switch your active operational branch context.',
            actionsHtml: canManage ? `
                <button type="button" class="btn btn-primary" onclick="LabverseAdminOps.openBranchModal()">
                    <i data-lucide="building-2" style="width:16px;height:16px;"></i> Add New Branch
                </button>
            ` : '',
        }) + `<div id="lv-branches-grid">${UI.loadingSpinner('Loading multi-branch network...')}</div>`;
        UI.refreshIcons();

        try {
            const res = await UI.api('/branches');
            state.branchesData = res.branches || [];
            const grid = document.getElementById('lv-branches-grid');
            if (!grid) return;

            grid.innerHTML = `
                <div class="row g-3">
                    ${state.branchesData.map(b => `
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="lv-card h-100 d-flex flex-column">
                                <div class="lv-card-header">
                                    <div>
                                        <span class="font-mono fw-bold text-primary">${UI.esc(b.code)}</span>
                                        ${b.is_main ? `<span class="lv-badge lv-badge-teal ms-1">HQ Reference Lab</span>` : ''}
                                        <h5 class="mb-0 fw-bold mt-1">${UI.esc(b.name)}</h5>
                                    </div>
                                    ${b.is_active ? `<span class="lv-badge lv-badge-success">Active</span>` : `<span class="lv-badge lv-badge-neutral">Inactive</span>`}
                                </div>
                                <div class="lv-card-body flex-grow-1 small">
                                    <div class="mb-1"><i data-lucide="map-pin" style="width:14px;height:14px;" class="text-secondary me-1"></i> ${UI.esc(b.address)} (${UI.esc(b.city)}, ${UI.esc(b.region)})</div>
                                    <div class="mb-1"><i data-lucide="phone" style="width:14px;height:14px;" class="text-secondary me-1"></i> <span class="font-mono">${UI.esc(b.phone)}</span></div>
                                    <div class="mb-3"><i data-lucide="user-check" style="width:14px;height:14px;" class="text-secondary me-1"></i> Manager: <strong>${UI.esc(b.manager_name || 'Assigned Manager')}</strong></div>

                                    <div class="row g-2 text-center border-top pt-3">
                                        <div class="col-3">
                                            <div class="fw-bold fs-6">${b.users_count ?? 0}</div>
                                            <div class="text-secondary" style="font-size:0.72rem;">Staff</div>
                                        </div>
                                        <div class="col-3">
                                            <div class="fw-bold fs-6">${b.patients_count ?? 0}</div>
                                            <div class="text-secondary" style="font-size:0.72rem;">Patients</div>
                                        </div>
                                        <div class="col-3">
                                            <div class="fw-bold fs-6">${b.orders_count ?? 0}</div>
                                            <div class="text-secondary" style="font-size:0.72rem;">Orders</div>
                                        </div>
                                        <div class="col-3">
                                            <div class="fw-bold fs-6 text-success">${UI.formatCurrency(b.revenue_total)}</div>
                                            <div class="text-secondary" style="font-size:0.72rem;">Revenue</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-3 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-secondary-teal btn-sm" onclick="LabverseApp.switchBranch('${b.id}')">
                                        Switch Dashboard to Branch
                                    </button>
                                    ${canManage ? `
                                        <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseAdminOps.openBranchModal(${b.id})">Edit</button>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function openBranchModal(branchId = null) {
        const b = branchId ? state.branchesData.find(x => x.id === branchId) : null;
        const isEdit = !!b;

        const html = `
            <form method="POST" onsubmit="event.preventDefault(); LabverseAdminOps.submitBranchForm(${isEdit ? b.id : 'null'}, this);">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">${isEdit ? `Edit Branch — ${UI.esc(b.code)}` : 'Register New Laboratory Branch'}</h5>
                    <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDialog()"></button>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="br-code" class="form-label">Branch Code <span class="text-danger">*</span></label>
                            <input type="text" id="br-code" name="code" class="form-control font-mono" required value="${UI.esc(b?.code || 'LV-TM-04')}">
                        </div>
                        <div class="col-md-8">
                            <label for="br-name" class="form-label">Branch Name <span class="text-danger">*</span></label>
                            <input type="text" id="br-name" name="name" class="form-control" required value="${UI.esc(b?.name || '')}">
                        </div>
                        <div class="col-md-6">
                            <label for="br-region" class="form-label">Region <span class="text-danger">*</span></label>
                            <input type="text" id="br-region" name="region" class="form-control" required value="${UI.esc(b?.region || 'Greater Accra')}">
                        </div>
                        <div class="col-md-6">
                            <label for="br-city" class="form-label">City <span class="text-danger">*</span></label>
                            <input type="text" id="br-city" name="city" class="form-control" required value="${UI.esc(b?.city || '')}">
                        </div>
                        <div class="col-12">
                            <label for="br-address" class="form-label">Physical Address <span class="text-danger">*</span></label>
                            <input type="text" id="br-address" name="address" class="form-control" required value="${UI.esc(b?.address || '')}">
                        </div>
                        <div class="col-md-4">
                            <label for="br-phone" class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" id="br-phone" name="phone" class="form-control" required value="${UI.esc(b?.phone || '')}">
                        </div>
                        <div class="col-md-4">
                            <label for="br-email" class="form-label">Branch Email</label>
                            <input type="email" id="br-email" name="email" class="form-control" value="${UI.esc(b?.email || '')}">
                        </div>
                        <div class="col-md-4">
                            <label for="br-mgr" class="form-label">Branch Manager</label>
                            <input type="text" id="br-mgr" name="manager_name" class="form-control" value="${UI.esc(b?.manager_name || '')}">
                        </div>
                    </div>
                </div>
                <div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDialog()">Cancel</button>
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Save Branch Changes' : 'Create Branch'}</button>
                </div>
            </form>
        `;
        window.LabverseApp.openDialog(html);
    }

    async function submitBranchForm(branchId, form) {
        const payload = Object.fromEntries(new FormData(form).entries());
        try {
            const res = await UI.api(branchId ? `/branches/${branchId}` : '/branches', {
                method: branchId ? 'PUT' : 'POST',
                body: payload,
            });
            UI.toast(res.message, 'success');
            window.LabverseApp.closeDialog();
            renderBranches();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    // =========================================================================
    // 16. STAFF & CUSTOM RBAC MANAGEMENT MODULE
    // =========================================================================
    async function renderStaffAndRbac() {
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Staff & RBAC',
            title: 'Staff Directory & Custom Role-Based Access Control',
            description: 'Manage laboratory personnel accounts, branch assignments, custom roles, and categorized permission matrices.',
            actionsHtml: `
                <button type="button" class="btn btn-primary" onclick="LabverseAdminOps.openStaffModal()">
                    <i data-lucide="user-plus" style="width:16px;height:16px;"></i> Register Staff Member
                </button>
            `,
        }) + `<div id="lv-staff-rbac-body">${UI.loadingSpinner('Loading staff accounts and custom RBAC roles...')}</div>`;
        UI.refreshIcons();

        try {
            const [staffRes, branchRes] = await Promise.all([
                UI.api('/staff'),
                UI.api('/branches'),
            ]);
            state.staffData = staffRes.staff || [];
            state.rolesData = staffRes.roles || [];
            state.permissionsByCategory = staffRes.permissions_by_category || {};
            state.branchesData = branchRes.branches || [];

            const wrap = document.getElementById('lv-staff-rbac-body');
            if (!wrap) return;

            wrap.innerHTML = `
                <div class="row g-4">
                    <!-- Staff Accounts Table -->
                    <div class="col-12 col-xl-7">
                        <div class="lv-card">
                            <div class="lv-card-header">
                                <h2 class="lv-card-title"><i data-lucide="users" style="width:17px;height:17px;color:var(--lv-primary);"></i> Laboratory Staff Directory (${state.staffData.length})</h2>
                            </div>
                            <div class="lv-table-container">
                                <table class="lv-table">
                                    <thead>
                                        <tr>
                                            <th>Staff ID</th>
                                            <th>Name & Title</th>
                                            <th>Branch</th>
                                            <th>Assigned Roles</th>
                                            <th>Status</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${state.staffData.map(u => `
                                            <tr>
                                                <td class="font-mono fw-semibold text-primary">${UI.esc(u.staff_id)}</td>
                                                <td>
                                                    <div class="fw-semibold">${UI.esc(u.name)}</div>
                                                    <div class="small text-secondary">${UI.esc(u.job_title || '')} • ${UI.esc(u.email)}</div>
                                                </td>
                                                <td><span class="lv-badge lv-badge-neutral">${UI.esc(u.branch?.name || 'All Branches')}</span></td>
                                                <td>
                                                    ${(u.roles || []).map(r => `<span class="lv-badge lv-badge-info me-1">${UI.esc(r.name)}</span>`).join('')}
                                                </td>
                                                <td>${u.is_active ? `<span class="lv-badge lv-badge-success">Active</span>` : `<span class="lv-badge lv-badge-danger">Deactivated</span>`}</td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseAdminOps.openStaffModal(${u.id})">Edit</button>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Custom RBAC Roles & Categorized Permissions Matrix -->
                    <div class="col-12 col-xl-5">
                        <div class="lv-card">
                            <div class="lv-card-header">
                                <h2 class="lv-card-title"><i data-lucide="shield" style="width:17px;height:17px;color:var(--lv-secondary);"></i> Custom RBAC Roles & Permissions</h2>
                            </div>
                            <div class="lv-card-body">
                                <div class="mb-3">
                                    <label for="rbac-role-selector" class="form-label fw-semibold">Select Role to Inspect or Configure Permissions</label>
                                    <select id="rbac-role-selector" class="form-select form-select-sm" onchange="LabverseAdminOps.renderRolePermissionEditor(this.value)">
                                        ${state.rolesData.map(r => `<option value="${r.id}">${UI.esc(r.name)} (${r.slug}) — ${r.users_count || 0} staff</option>`).join('')}
                                    </select>
                                </div>
                                <div id="rbac-role-editor-box"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            if (state.rolesData.length > 0) {
                renderRolePermissionEditor(state.rolesData[0].id);
            }
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function renderRolePermissionEditor(roleId) {
        const box = document.getElementById('rbac-role-editor-box');
        if (!box) return;

        const role = state.rolesData.find(r => String(r.id) === String(roleId));
        if (!role) return;

        const activePermIds = new Set((role.permissions || []).map(p => p.id));

        box.innerHTML = `
            <form onsubmit="event.preventDefault(); LabverseAdminOps.saveRolePermissions(${role.id});">
                <div class="p-2 rounded bg-light border small text-secondary mb-3">
                    <strong>Role Description:</strong> ${UI.esc(role.description || '')}
                </div>
                <div style="max-height:380px;overflow-y:auto;" class="pe-1">
                    ${Object.entries(state.permissionsByCategory).map(([category, perms]) => `
                        <div class="mb-3">
                            <div class="small fw-bold text-uppercase text-primary border-bottom pb-1 mb-2">${UI.esc(category)}</div>
                            ${perms.map(perm => `
                                <div class="form-check mb-1">
                                    <input class="form-check-input lv-role-perm-chk" type="checkbox" id="perm-chk-${perm.id}" value="${perm.id}" ${activePermIds.has(perm.id) ? 'checked' : ''}>
                                    <label class="form-check-label small" for="perm-chk-${perm.id}">
                                        <span class="fw-medium">${UI.esc(perm.name)}</span>
                                        <span class="font-mono text-secondary ms-1" style="font-size:0.72rem;">(${UI.esc(perm.slug)})</span>
                                    </label>
                                </div>
                            `).join('')}
                        </div>
                    `).join('')}
                </div>
                <div class="border-top pt-3 mt-2 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-lucide="save" style="width:14px;height:14px;"></i> Save Role Permissions
                    </button>
                </div>
            </form>
        `;
        UI.refreshIcons();
    }

    async function saveRolePermissions(roleId) {
        const role = state.rolesData.find(r => r.id === roleId);
        if (!role) return;

        const checked = Array.from(document.querySelectorAll('.lv-role-perm-chk:checked')).map(el => Number(el.value));

        try {
            const res = await UI.api(`/roles/${roleId}/permissions`, {
                method: 'PUT',
                body: {
                    name: role.name,
                    description: role.description,
                    permission_ids: checked,
                },
            });
            UI.toast(res.message, 'success');
            renderStaffAndRbac();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function openStaffModal(userId = null) {
        const u = userId ? state.staffData.find(x => x.id === userId) : null;
        const isEdit = !!u;
        const userRoleIds = new Set((u?.roles || []).map(r => r.id));

        const html = `
            <form id="lv-staff-form" method="POST" onsubmit="event.preventDefault(); LabverseAdminOps.submitStaffForm(${isEdit ? u.id : 'null'});">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">${isEdit ? `Edit Staff Account — ${UI.esc(u.staff_id)}` : 'Register New Laboratory Staff Account'}</h5>
                    <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDialog()"></button>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="stf-fname" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" id="stf-fname" class="form-control" required value="${UI.esc(u?.first_name || '')}">
                        </div>
                        <div class="col-md-6">
                            <label for="stf-lname" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" id="stf-lname" class="form-control" required value="${UI.esc(u?.last_name || '')}">
                        </div>
                        <div class="col-md-6">
                            <label for="stf-email" class="form-label">Work Email <span class="text-danger">*</span></label>
                            <input type="email" id="stf-email" class="form-control" required value="${UI.esc(u?.email || '')}">
                        </div>
                        <div class="col-md-6">
                            <label for="stf-phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" id="stf-phone" class="form-control" required value="${UI.esc(u?.phone || '')}">
                        </div>
                        <div class="col-md-6">
                            <label for="stf-title" class="form-label">Job Title / Designation <span class="text-danger">*</span></label>
                            <input type="text" id="stf-title" class="form-control" required placeholder="Medical Laboratory Scientist" value="${UI.esc(u?.job_title || '')}">
                        </div>
                        <div class="col-md-6">
                            <label for="stf-lic" class="form-label">Professional License # (AHPC / MDC)</label>
                            <input type="text" id="stf-lic" class="form-control font-mono" placeholder="AHPC-MLS-XXXX" value="${UI.esc(u?.license_number || '')}">
                        </div>
                        <div class="col-md-6">
                            <label for="stf-branch" class="form-label">Assigned Branch <span class="text-danger">*</span></label>
                            <select id="stf-branch" class="form-select" required>
                                ${state.branchesData.map(b => `<option value="${b.id}" ${u?.branch_id === b.id ? 'selected' : ''}>${UI.esc(b.name)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="stf-pass" class="form-label">${isEdit ? 'Reset Password (leave blank to keep)' : 'Temporary Password *'}</label>
                            <input type="password" id="stf-pass" class="form-control" ${isEdit ? '' : 'required'} minlength="8" placeholder="Minimum 8 characters">
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Assign RBAC Role(s) <span class="text-danger">*</span></label>
                            <div class="row g-2 p-2 border rounded bg-light">
                                ${state.rolesData.map(r => `
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input stf-role-chk" type="checkbox" id="stf-role-${r.id}" value="${r.id}" ${userRoleIds.has(r.id) ? 'checked' : ''}>
                                            <label class="form-check-label small fw-medium" for="stf-role-${r.id}">${UI.esc(r.name)}</label>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="stf-active" ${u ? (u.is_active ? 'checked' : '') : 'checked'}>
                                <label class="form-check-label fw-medium" for="stf-active">Staff Account Active (Allowed to sign in)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDialog()">Cancel</button>
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Save Staff Account' : 'Create Staff Account'}</button>
                </div>
            </form>
        `;
        window.LabverseApp.openDialog(html);
    }

    async function submitStaffForm(userId) {
        const roleIds = Array.from(document.querySelectorAll('.stf-role-chk:checked')).map(el => Number(el.value));
        if (roleIds.length === 0) {
            UI.toast('Please assign at least one role.', 'warning');
            return;
        }

        const payload = {
            first_name: document.getElementById('stf-fname')?.value,
            last_name: document.getElementById('stf-lname')?.value,
            email: document.getElementById('stf-email')?.value,
            phone: document.getElementById('stf-phone')?.value,
            job_title: document.getElementById('stf-title')?.value,
            license_number: document.getElementById('stf-lic')?.value || null,
            branch_id: Number(document.getElementById('stf-branch')?.value),
            role_ids: roleIds,
            is_active: document.getElementById('stf-active')?.checked ?? true,
        };

        const pass = document.getElementById('stf-pass')?.value;
        if (pass) payload.password = pass;

        try {
            const res = await UI.api(userId ? `/staff/${userId}` : '/staff', {
                method: userId ? 'PUT' : 'POST',
                body: payload,
            });
            UI.toast(res.message, 'success');
            window.LabverseApp.closeDialog();
            renderStaffAndRbac();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    // =========================================================================
    // TEST CATALOGUE CONFIGURATION MODULE
    // =========================================================================
    async function renderTestCatalogue() {
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        const canManage = window.LabverseApp.hasPermission('manage_catalogue');

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Test Catalogue',
            title: 'Diagnostic Test Catalogue & Reference Intervals',
            description: 'Configure laboratory test panels, specimen requirements, turnaround times, tariffs, and analytical parameter reference intervals.',
            actionsHtml: canManage ? `
                <button type="button" class="btn btn-primary" onclick="LabverseAdminOps.openCatalogueTestModal()">
                    <i data-lucide="plus-circle" style="width:16px;height:16px;"></i> Add New Diagnostic Test
                </button>
            ` : '',
        }) + `<div id="lv-catalogue-body">${UI.loadingSpinner('Loading diagnostic test catalogue...')}</div>`;
        UI.refreshIcons();

        try {
            const [catRes, testRes] = await Promise.all([
                UI.api('/catalogue/categories'),
                UI.api(`/catalogue/tests?category_id=${state.catFilterId}&search=${encodeURIComponent(state.catSearch)}`),
            ]);
            state.catalogueCategories = catRes.categories || [];
            state.catalogueTests = testRes.tests || [];

            const wrap = document.getElementById('lv-catalogue-body');
            if (!wrap) return;

            wrap.innerHTML = `
                <div class="lv-filter-bar d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div class="d-flex flex-wrap gap-1">
                        <button type="button" class="btn btn-sm ${!state.catFilterId ? 'btn-primary' : 'btn-light-surface'}" onclick="LabverseAdminOps.filterCatalogueCategory('')">All Categories</button>
                        ${state.catalogueCategories.map(c => `
                            <button type="button" class="btn btn-sm ${String(state.catFilterId) === String(c.id) ? 'btn-primary' : 'btn-light-surface'}" onclick="LabverseAdminOps.filterCatalogueCategory('${c.id}')">
                                ${UI.esc(c.name)} (${c.tests_count || 0})
                            </button>
                        `).join('')}
                    </div>
                    <div style="min-width:260px;">
                        <input type="search" class="form-control form-control-sm" placeholder="Search test code or name..." value="${UI.esc(state.catSearch)}" oninput="LabverseAdminOps.searchCatalogue(this.value)">
                    </div>
                </div>

                <div class="lv-card">
                    <div class="lv-table-container">
                        <table class="lv-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Diagnostic Test Name</th>
                                    <th>Category</th>
                                    <th>Specimen & Container</th>
                                    <th>Parameters & Reference Ranges</th>
                                    <th>TAT</th>
                                    <th>Standard Price</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${state.catalogueTests.map(t => `
                                    <tr>
                                        <td class="font-mono fw-bold text-primary">${UI.esc(t.code)}</td>
                                        <td>
                                            <div class="fw-semibold">${UI.esc(t.name)}</div>
                                            <div class="small text-secondary">${UI.esc(t.methodology || '')}</div>
                                        </td>
                                        <td><span class="lv-badge lv-badge-info">${UI.esc(t.category?.name || '')}</span></td>
                                        <td class="small">
                                            <div class="fw-medium">${UI.esc(t.sample_type)}</div>
                                            <div class="text-secondary">${UI.esc(t.container_type || '')}</div>
                                        </td>
                                        <td>
                                            <div class="small">
                                                ${(t.parameters || []).slice(0, 3).map(p => `<span class="lv-badge lv-badge-neutral me-1 mb-1">${UI.esc(p.name)} ${p.unit ? `(${UI.esc(p.unit)})` : ''}</span>`).join('')}
                                                ${(t.parameters || []).length > 3 ? `<span class="small text-secondary">+${t.parameters.length - 3} more</span>` : ''}
                                            </div>
                                        </td>
                                        <td>${t.turnaround_hours} hrs</td>
                                        <td class="font-mono fw-bold">${UI.formatCurrency(t.price)}</td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseAdminOps.openCatalogueTestModal(${t.id})">
                                                ${canManage ? 'Configure' : 'View'}
                                            </button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function filterCatalogueCategory(catId) {
        state.catFilterId = catId;
        renderTestCatalogue();
    }

    function searchCatalogue(q) {
        state.catSearch = q;
        renderTestCatalogue();
    }

    function openCatalogueTestModal(testId = null) {
        const t = testId ? state.catalogueTests.find(x => x.id === testId) : null;
        const isEdit = !!t;

        const html = `
            <form method="POST" onsubmit="event.preventDefault(); LabverseAdminOps.submitCatalogueTest(${isEdit ? t.id : 'null'}, this);">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">${isEdit ? `Configure Test — ${UI.esc(t.code)}` : 'Add Diagnostic Test to Catalogue'}</h5>
                    <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDialog()"></button>
                </div>
                <div class="p-4" style="max-height:74vh;overflow-y:auto;">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label for="cat-t-code" class="form-label">Test Code <span class="text-danger">*</span></label>
                            <input type="text" id="cat-t-code" name="code" class="form-control font-mono" required value="${UI.esc(t?.code || '')}">
                        </div>
                        <div class="col-md-5">
                            <label for="cat-t-name" class="form-label">Test Name <span class="text-danger">*</span></label>
                            <input type="text" id="cat-t-name" name="name" class="form-control" required value="${UI.esc(t?.name || '')}">
                        </div>
                        <div class="col-md-4">
                            <label for="cat-t-cat" class="form-label">Department / Category <span class="text-danger">*</span></label>
                            <select id="cat-t-cat" name="category_id" class="form-select" required>
                                ${state.catalogueCategories.map(c => `<option value="${c.id}" ${t?.category_id === c.id ? 'selected' : ''}>${UI.esc(c.name)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="cat-t-smp" class="form-label">Specimen Type <span class="text-danger">*</span></label>
                            <input type="text" id="cat-t-smp" name="sample_type" class="form-control" required value="${UI.esc(t?.sample_type || 'Serum')}">
                        </div>
                        <div class="col-md-4">
                            <label for="cat-t-cnt" class="form-label">Container Tube</label>
                            <input type="text" id="cat-t-cnt" name="container_type" class="form-control" value="${UI.esc(t?.container_type || 'Gold Top (SST Gel)')}">
                        </div>
                        <div class="col-md-2">
                            <label for="cat-t-price" class="form-label">Price (GH₵) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" id="cat-t-price" name="price" class="form-control font-mono" required value="${t?.price ?? 80}">
                        </div>
                        <div class="col-md-2">
                            <label for="cat-t-tat" class="form-label">TAT (Hours) <span class="text-danger">*</span></label>
                            <input type="number" id="cat-t-tat" name="turnaround_hours" class="form-control font-mono" required value="${t?.turnaround_hours ?? 4}">
                        </div>
                    </div>

                    ${t && t.parameters ? `
                        <h6 class="fw-bold small text-uppercase text-secondary border-bottom pb-2">Configured Analytical Parameters (${t.parameters.length})</h6>
                        <table class="lv-table small">
                            <thead>
                                <tr><th>Code</th><th>Parameter</th><th>Type</th><th>Unit</th><th>Male Ref</th><th>Female Ref</th><th>Critical</th></tr>
                            </thead>
                            <tbody>
                                ${t.parameters.map(p => `
                                    <tr>
                                        <td class="font-mono">${UI.esc(p.code)}</td>
                                        <td class="fw-semibold">${UI.esc(p.name)}</td>
                                        <td>${UI.esc(p.input_type)}</td>
                                        <td class="font-mono">${UI.esc(p.unit || '—')}</td>
                                        <td class="font-mono">${p.ref_min_male !== null ? `${p.ref_min_male} - ${p.ref_max_male}` : UI.esc(p.reference_text || '—')}</td>
                                        <td class="font-mono">${p.ref_min_female !== null ? `${p.ref_min_female} - ${p.ref_max_female}` : UI.esc(p.reference_text || '—')}</td>
                                        <td class="font-mono text-danger">${p.critical_low ?? '—'} / ${p.critical_high ?? '—'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : ''}
                </div>
                <div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDialog()">Close</button>
                    ${window.LabverseApp.hasPermission('manage_catalogue') ? `<button type="submit" class="btn btn-primary">${isEdit ? 'Save Test Configuration' : 'Create Test'}</button>` : ''}
                </div>
            </form>
        `;
        window.LabverseApp.openDialog(html, true);
    }

    async function submitCatalogueTest(testId, form) {
        const payload = Object.fromEntries(new FormData(form).entries());
        if (!testId) {
            payload.parameters = [
                { code: payload.code + '-RES', name: payload.name + ' Result', input_type: 'text', reference_text: 'Normal' }
            ];
        }
        try {
            const res = await UI.api(testId ? `/catalogue/tests/${testId}` : '/catalogue/tests', {
                method: testId ? 'PUT' : 'POST',
                body: payload,
            });
            UI.toast(res.message, 'success');
            window.LabverseApp.closeDialog();
            renderTestCatalogue();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    // =========================================================================
    // SYSTEM SETTINGS, SMTP EMAIL, SMS GATEWAY & AUDIT LOGS MODULE
    // =========================================================================
    async function renderSettings(activeTab = 'facility') {
        state.settingsTab = activeTab || state.settingsTab || 'facility';
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Settings, SMTP/SMS & Audit Logs',
            title: 'Facility Configuration, SMTP Email, SMS Gateways & Audit Trail',
            description: 'Configure laboratory branding, SMTP email server, SMS notification gateways (Arkesel, Hubtel, mNotify, Twilio, Termii), automated clinical alerts, and inspect delivery & security logs.',
            actionsHtml: `
                <a href="/install" target="_blank" class="btn btn-light-surface btn-sm">
                    <i data-lucide="wrench" style="width:15px;height:15px;"></i> Server Setup & Health Wizard
                </a>
            `,
        }) + `
            <div class="d-flex flex-wrap gap-2 mb-3 border-bottom pb-2">
                <button type="button" class="btn btn-sm ${state.settingsTab === 'facility' ? 'btn-primary' : 'btn-light-surface'}" onclick="LabverseAdminOps.renderSettings('facility')">
                    <i data-lucide="building-2" style="width:14px;height:14px;"></i> Facility & Clinical Policies
                </button>
                <button type="button" class="btn btn-sm ${state.settingsTab === 'smtp' ? 'btn-primary' : 'btn-light-surface'}" onclick="LabverseAdminOps.renderSettings('smtp')">
                    <i data-lucide="mail" style="width:14px;height:14px;"></i> SMTP Email Integration
                </button>
                <button type="button" class="btn btn-sm ${state.settingsTab === 'sms' ? 'btn-primary' : 'btn-light-surface'}" onclick="LabverseAdminOps.renderSettings('sms')">
                    <i data-lucide="message-square" style="width:14px;height:14px;"></i> SMS Gateway Integration
                </button>
                <button type="button" class="btn btn-sm ${state.settingsTab === 'triggers' ? 'btn-primary' : 'btn-light-surface'}" onclick="LabverseAdminOps.renderSettings('triggers')">
                    <i data-lucide="bell-ring" style="width:14px;height:14px;"></i> Automated Triggers & Templates
                </button>
                <button type="button" class="btn btn-sm ${state.settingsTab === 'outbox' ? 'btn-primary' : 'btn-light-surface'}" onclick="LabverseAdminOps.renderSettings('outbox')">
                    <i data-lucide="send" style="width:14px;height:14px;"></i> Communication Outbox (SMS & Email Logs)
                </button>
                <button type="button" class="btn btn-sm ${state.settingsTab === 'audit' ? 'btn-primary' : 'btn-light-surface'}" onclick="LabverseAdminOps.renderSettings('audit')">
                    <i data-lucide="shield-alert" style="width:14px;height:14px;"></i> Security Audit Trail
                </button>
            </div>
            <div id="lv-settings-body">${UI.loadingSpinner('Loading configuration...')}</div>
        `;
        UI.refreshIcons();

        try {
            if (state.settingsTab === 'outbox') {
                await renderCommunicationOutboxTab();
                return;
            }
            if (state.settingsTab === 'audit') {
                await renderAuditLogsTab();
                return;
            }

            const setRes = await UI.api('/settings');
            const s = setRes.settings || {};
            state.cachedSettings = s;
            const canEdit = window.LabverseApp.hasPermission('manage_settings');
            const isTrue = (v) => ['1', 'true', 'yes', 'on', true].includes(String(v).toLowerCase());

            const wrap = document.getElementById('lv-settings-body');
            if (!wrap) return;

            if (state.settingsTab === 'facility') {
                wrap.innerHTML = `
                    <form class="lv-card" onsubmit="event.preventDefault(); LabverseAdminOps.saveSystemSettings(this);">
                        <div class="lv-card-header">
                            <h2 class="lv-card-title"><i data-lucide="sliders" style="width:17px;height:17px;color:var(--lv-primary);"></i> Facility Branding & Clinical Policy Configuration</h2>
                        </div>
                        <div class="lv-card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="set-fac-name" class="form-label">Official Laboratory Name</label>
                                    <input type="text" id="set-fac-name" name="facility_name" class="form-control" value="${UI.esc(s.facility_name || '')}" ${canEdit ? '' : 'disabled'}>
                                </div>
                                <div class="col-md-6">
                                    <label for="set-fac-acc" class="form-label">Accreditation & HeFRA Registration</label>
                                    <input type="text" id="set-fac-acc" name="facility_accreditation" class="form-control" value="${UI.esc(s.facility_accreditation || '')}" ${canEdit ? '' : 'disabled'}>
                                </div>
                                <div class="col-md-6">
                                    <label for="set-fac-phone" class="form-label">Primary Clinical Desk Phone (Used for Critical Escalation Alerts)</label>
                                    <input type="text" id="set-fac-phone" name="facility_phone" class="form-control font-mono" value="${UI.esc(s.facility_phone || '')}" ${canEdit ? '' : 'disabled'}>
                                </div>
                                <div class="col-md-6">
                                    <label for="set-fac-email" class="form-label">Official Clinical Notifications Email</label>
                                    <input type="email" id="set-fac-email" name="facility_email" class="form-control" value="${UI.esc(s.facility_email || '')}" ${canEdit ? '' : 'disabled'}>
                                </div>
                                <div class="col-12">
                                    <label for="set-crit-pol" class="form-label">Critical Result Escalation Policy</label>
                                    <textarea id="set-crit-pol" name="critical_value_policy" class="form-control" rows="2" ${canEdit ? '' : 'disabled'}>${UI.esc(s.critical_value_policy || '')}</textarea>
                                </div>
                                <div class="col-12">
                                    <label for="set-rep-disc" class="form-label">Medical Report Footer Disclaimer</label>
                                    <textarea id="set-rep-disc" name="report_footer_disclaimer" class="form-control" rows="2" ${canEdit ? '' : 'disabled'}>${UI.esc(s.report_footer_disclaimer || '')}</textarea>
                                </div>
                            </div>
                            ${canEdit ? `
                                <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i data-lucide="save" style="width:15px;height:15px;"></i> Save Facility Configuration
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    </form>
                `;
            } else if (state.settingsTab === 'smtp') {
                wrap.innerHTML = `
                    <div class="row g-4">
                        <div class="col-12 col-lg-7">
                            <form id="lv-smtp-settings-form" class="lv-card" onsubmit="event.preventDefault(); LabverseAdminOps.saveSystemSettings(this);">
                                <div class="lv-card-header d-flex justify-content-between align-items-center">
                                    <h2 class="lv-card-title"><i data-lucide="mail" style="width:17px;height:17px;color:var(--lv-primary);"></i> SMTP Email Server Configuration</h2>
                                    <span class="lv-badge ${isTrue(s.smtp_enabled) ? 'lv-badge-success' : 'lv-badge-neutral'}">${isTrue(s.smtp_enabled) ? 'SMTP Active' : 'Disabled'}</span>
                                </div>
                                <div class="lv-card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="smtp-enabled" class="form-label">Email Notifications Status</label>
                                            <select id="smtp-enabled" name="smtp_enabled" class="form-select" ${canEdit ? '' : 'disabled'}>
                                                <option value="true" ${isTrue(s.smtp_enabled) ? 'selected' : ''}>Enabled (Send Clinical Emails)</option>
                                                <option value="false" ${!isTrue(s.smtp_enabled) ? 'selected' : ''}>Disabled</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="smtp-driver" class="form-label">Mail Delivery Mode</label>
                                            <select id="smtp-driver" name="smtp_driver" class="form-select" ${canEdit ? '' : 'disabled'}>
                                                <option value="log" ${(s.smtp_driver || 'log') === 'log' ? 'selected' : ''}>Sandbox / Local Mail Log (Zero-Config Testing)</option>
                                                <option value="smtp" ${s.smtp_driver === 'smtp' ? 'selected' : ''}>Live External SMTP Relay Server</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="smtp-host" class="form-label">SMTP Hostname</label>
                                            <input type="text" id="smtp-host" name="smtp_host" class="form-control font-mono" placeholder="smtp.gmail.com / mail.yourdomain.com" value="${UI.esc(s.smtp_host || '')}" ${canEdit ? '' : 'disabled'}>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="smtp-port" class="form-label">SMTP Port</label>
                                            <input type="number" id="smtp-port" name="smtp_port" class="form-control font-mono" placeholder="587" value="${UI.esc(s.smtp_port || '587')}" ${canEdit ? '' : 'disabled'}>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="smtp-enc" class="form-label">Encryption</label>
                                            <select id="smtp-enc" name="smtp_encryption" class="form-select" ${canEdit ? '' : 'disabled'}>
                                                <option value="tls" ${(s.smtp_encryption || 'tls') === 'tls' ? 'selected' : ''}>TLS (Port 587)</option>
                                                <option value="ssl" ${s.smtp_encryption === 'ssl' ? 'selected' : ''}>SSL (Port 465)</option>
                                                <option value="none" ${s.smtp_encryption === 'none' ? 'selected' : ''}>None</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="smtp-user" class="form-label">SMTP Username</label>
                                            <input type="text" id="smtp-user" name="smtp_username" class="form-control font-mono" placeholder="apikey or user@domain.com" value="${UI.esc(s.smtp_username || '')}" ${canEdit ? '' : 'disabled'}>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="smtp-pass" class="form-label">SMTP Password / App Key</label>
                                            <input type="password" id="smtp-pass" name="smtp_password" class="form-control font-mono" placeholder="••••••••••••" value="${UI.esc(s.smtp_password || '')}" ${canEdit ? '' : 'disabled'}>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="smtp-from-addr" class="form-label">From Email Address</label>
                                            <input type="email" id="smtp-from-addr" name="smtp_from_address" class="form-control" placeholder="results@labverse.com" value="${UI.esc(s.smtp_from_address || '')}" ${canEdit ? '' : 'disabled'}>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="smtp-from-name" class="form-label">From Sender Name</label>
                                            <input type="text" id="smtp-from-name" name="smtp_from_name" class="form-control" placeholder="Labverse Medical Diagnostics" value="${UI.esc(s.smtp_from_name || '')}" ${canEdit ? '' : 'disabled'}>
                                        </div>
                                    </div>
                                    ${canEdit ? `
                                        <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i data-lucide="save" style="width:15px;height:15px;"></i> Save SMTP Settings
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            </form>
                        </div>
                        <div class="col-12 col-lg-5">
                            <div class="lv-card">
                                <div class="lv-card-header">
                                    <h2 class="lv-card-title"><i data-lucide="send" style="width:17px;height:17px;color:var(--lv-secondary);"></i> Verify & Test SMTP Delivery</h2>
                                </div>
                                <div class="lv-card-body">
                                    <p class="small text-secondary mb-3">
                                        Send a test clinical email using the current form parameters to verify your SMTP server or Sandbox Mailer before enabling live patient report dispatches.
                                    </p>
                                    <form onsubmit="event.preventDefault(); LabverseAdminOps.sendTestEmailNow();">
                                        <div class="mb-3">
                                            <label for="test-email-recipient" class="form-label">Recipient Email Address</label>
                                            <input type="email" id="test-email-recipient" class="form-control" required placeholder="doctor@hospital.org" value="${UI.esc(window.LabverseApp.getCurrentUser()?.email || 'admin@labverse.com')}">
                                        </div>
                                        <button type="submit" id="btn-test-smtp" class="btn btn-outline-primary w-100">
                                            <i data-lucide="mail-check" style="width:15px;height:15px;"></i> Send Live Test Email
                                        </button>
                                    </form>
                                    <div id="test-email-result-box" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else if (state.settingsTab === 'sms') {
                wrap.innerHTML = `
                    <div class="row g-4">
                        <div class="col-12 col-lg-7">
                            <form id="lv-sms-settings-form" class="lv-card" onsubmit="event.preventDefault(); LabverseAdminOps.saveSystemSettings(this);">
                                <div class="lv-card-header d-flex justify-content-between align-items-center">
                                    <h2 class="lv-card-title"><i data-lucide="message-square" style="width:17px;height:17px;color:var(--lv-primary);"></i> SMS Gateway Configuration</h2>
                                    <span class="lv-badge ${isTrue(s.sms_enabled) ? 'lv-badge-success' : 'lv-badge-neutral'}">${isTrue(s.sms_enabled) ? `Active (${(s.sms_provider || 'sandbox').toUpperCase()})` : 'Disabled'}</span>
                                </div>
                                <div class="lv-card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="sms-enabled" class="form-label">SMS Notification Pipeline</label>
                                            <select id="sms-enabled" name="sms_enabled" class="form-select" ${canEdit ? '' : 'disabled'}>
                                                <option value="true" ${isTrue(s.sms_enabled) ? 'selected' : ''}>Enabled (Send Patient & Alert SMS)</option>
                                                <option value="false" ${!isTrue(s.sms_enabled) ? 'selected' : ''}>Disabled</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="sms-provider" class="form-label">SMS Gateway Provider</label>
                                            <select id="sms-provider" name="sms_provider" class="form-select" ${canEdit ? '' : 'disabled'}>
                                                <option value="sandbox" ${(s.sms_provider || 'sandbox') === 'sandbox' ? 'selected' : ''}>Sandbox Simulator (Zero-Cost Local Outbox)</option>
                                                <option value="arkesel" ${s.sms_provider === 'arkesel' ? 'selected' : ''}>Arkesel SMS (Ghana & West Africa V2 API)</option>
                                                <option value="mnotify" ${s.sms_provider === 'mnotify' ? 'selected' : ''}>mNotify BMS (Ghana Quick SMS API)</option>
                                                <option value="hubtel" ${s.sms_provider === 'hubtel' ? 'selected' : ''}>Hubtel SMS Gateway</option>
                                                <option value="termii" ${s.sms_provider === 'termii' ? 'selected' : ''}>Termii Messaging Gateway</option>
                                                <option value="twilio" ${s.sms_provider === 'twilio' ? 'selected' : ''}>Twilio Programmable SMS</option>
                                                <option value="custom_webhook" ${s.sms_provider === 'custom_webhook' ? 'selected' : ''}>Custom REST HTTP Webhook</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="sms-sender" class="form-label">Sender ID (Max 11 Chars)</label>
                                            <input type="text" id="sms-sender" name="sms_sender_id" maxlength="11" class="form-control font-mono" placeholder="LABVERSE" value="${UI.esc(s.sms_sender_id || 'LABVERSE')}" ${canEdit ? '' : 'disabled'}>
                                        </div>
                                        <div class="col-md-8">
                                            <label for="sms-key" class="form-label">Gateway API Key / Auth Token / Client Secret</label>
                                            <input type="password" id="sms-key" name="sms_api_key" class="form-control font-mono" placeholder="Enter provider API key or token..." value="${UI.esc(s.sms_api_key || '')}" ${canEdit ? '' : 'disabled'}>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="sms-secret" class="form-label">Client ID / Account SID (For Hubtel or Twilio)</label>
                                            <input type="text" id="sms-secret" name="sms_api_secret" class="form-control font-mono" placeholder="Optional unless using Hubtel or Twilio" value="${UI.esc(s.sms_api_secret || '')}" ${canEdit ? '' : 'disabled'}>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="sms-webhook" class="form-label">Custom Webhook URL (If Custom HTTP selected)</label>
                                            <input type="url" id="sms-webhook" name="sms_webhook_url" class="form-control font-mono" placeholder="https://sms-gateway.example.com/send" value="${UI.esc(s.sms_webhook_url || '')}" ${canEdit ? '' : 'disabled'}>
                                        </div>
                                    </div>
                                    ${canEdit ? `
                                        <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i data-lucide="save" style="width:15px;height:15px;"></i> Save SMS Gateway Settings
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            </form>
                        </div>
                        <div class="col-12 col-lg-5">
                            <div class="lv-card">
                                <div class="lv-card-header">
                                    <h2 class="lv-card-title"><i data-lucide="smartphone" style="width:17px;height:17px;color:var(--lv-secondary);"></i> Send Test SMS Message</h2>
                                </div>
                                <div class="lv-card-body">
                                    <p class="small text-secondary mb-3">
                                        Test SMS dispatch to any phone number. Numbers starting with <code>024...</code> are automatically normalized to international format (<code>+23324...</code>).
                                    </p>
                                    <form onsubmit="event.preventDefault(); LabverseAdminOps.sendTestSmsNow();">
                                        <div class="mb-3">
                                            <label for="test-sms-recipient" class="form-label">Recipient Mobile Number</label>
                                            <input type="tel" id="test-sms-recipient" class="form-control font-mono" required placeholder="+233 244 100 001" value="${UI.esc(s.facility_phone || '+233 244 100 001')}">
                                        </div>
                                        <button type="submit" id="btn-test-sms" class="btn btn-outline-primary w-100">
                                            <i data-lucide="send" style="width:15px;height:15px;"></i> Dispatch Test SMS
                                        </button>
                                    </form>
                                    <div id="test-sms-result-box" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else if (state.settingsTab === 'triggers') {
                wrap.innerHTML = `
                    <form class="lv-card" onsubmit="event.preventDefault(); LabverseAdminOps.saveTriggerSettings(this);">
                        <div class="lv-card-header">
                            <h2 class="lv-card-title"><i data-lucide="bell-ring" style="width:17px;height:17px;color:var(--lv-primary);"></i> Automated Clinical Notification Triggers & Message Templates</h2>
                        </div>
                        <div class="lv-card-body">
                            <div class="p-3 rounded bg-light border small mb-4">
                                <strong>Available Template Placeholders:</strong>
                                <span class="font-mono ms-1">{facility_name}</span>,
                                <span class="font-mono">{patient_name}</span>,
                                <span class="font-mono">{patient_code}</span>,
                                <span class="font-mono">{order_number}</span>,
                                <span class="font-mono">{report_number}</span>,
                                <span class="font-mono">{verification_code}</span>,
                                <span class="font-mono">{receipt_number}</span>,
                                <span class="font-mono">{amount}</span>,
                                <span class="font-mono">{balance_due}</span>,
                                <span class="font-mono">{test_name}</span>
                            </div>

                            <div class="lv-table-container mb-4">
                                <table class="lv-table">
                                    <thead>
                                        <tr>
                                            <th style="width:26%;">Clinical Lifecycle Event</th>
                                            <th style="width:12%;">Send SMS</th>
                                            <th style="width:12%;">Send Email</th>
                                            <th style="width:50%;">Customizable SMS / Alert Message Template</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">1. Patient Registration Welcome</div>
                                                <div class="small text-secondary">Sent when a new patient profile is created with their Medical Record ID.</div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="trig-pat-sms" data-setting-key="notify_patient_welcome_sms" ${isTrue(s.notify_patient_welcome_sms) ? 'checked' : ''} ${canEdit ? '' : 'disabled'}>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="trig-pat-email" data-setting-key="notify_patient_welcome_email" ${isTrue(s.notify_patient_welcome_email) ? 'checked' : ''} ${canEdit ? '' : 'disabled'}>
                                                </div>
                                            </td>
                                            <td>
                                                <textarea name="tpl_sms_patient_welcome" class="form-control form-control-sm" rows="2" ${canEdit ? '' : 'disabled'}>${UI.esc(s.tpl_sms_patient_welcome || '')}</textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">2. Test Order Registered</div>
                                                <div class="small text-secondary">Sent when diagnostic tests are ordered for a patient.</div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="trig-ord-sms" data-setting-key="notify_order_placed_sms" ${isTrue(s.notify_order_placed_sms) ? 'checked' : ''} ${canEdit ? '' : 'disabled'}>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="trig-ord-email" data-setting-key="notify_order_placed_email" ${isTrue(s.notify_order_placed_email) ? 'checked' : ''} ${canEdit ? '' : 'disabled'}>
                                                </div>
                                            </td>
                                            <td>
                                                <textarea name="tpl_sms_order_placed" class="form-control form-control-sm" rows="2" ${canEdit ? '' : 'disabled'}>${UI.esc(s.tpl_sms_order_placed || '')}</textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">3. Official Report Verified & Released</div>
                                                <div class="small text-secondary">Sent automatically when all tests on an order are approved and released.</div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="trig-rep-sms" data-setting-key="notify_report_released_sms" ${isTrue(s.notify_report_released_sms) ? 'checked' : ''} ${canEdit ? '' : 'disabled'}>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="trig-rep-email" data-setting-key="notify_report_released_email" ${isTrue(s.notify_report_released_email) ? 'checked' : ''} ${canEdit ? '' : 'disabled'}>
                                                </div>
                                            </td>
                                            <td>
                                                <textarea name="tpl_sms_report_released" class="form-control form-control-sm" rows="2" ${canEdit ? '' : 'disabled'}>${UI.esc(s.tpl_sms_report_released || '')}</textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold text-danger">4. Critical / Panic Value Escalation</div>
                                                <div class="small text-secondary">Immediate alert when a scientist submits a panic/critical laboratory result.</div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="trig-crit-sms" data-setting-key="notify_critical_result_sms" ${isTrue(s.notify_critical_result_sms) ? 'checked' : ''} ${canEdit ? '' : 'disabled'}>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="trig-crit-email" data-setting-key="notify_critical_result_email" ${isTrue(s.notify_critical_result_email) ? 'checked' : ''} ${canEdit ? '' : 'disabled'}>
                                                </div>
                                            </td>
                                            <td>
                                                <textarea name="tpl_sms_critical_result" class="form-control form-control-sm" rows="2" ${canEdit ? '' : 'disabled'}>${UI.esc(s.tpl_sms_critical_result || '')}</textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">5. Cashier Payment Receipt</div>
                                                <div class="small text-secondary">Sent when a patient payment is recorded by the cashier.</div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="trig-pay-sms" data-setting-key="notify_payment_receipt_sms" ${isTrue(s.notify_payment_receipt_sms) ? 'checked' : ''} ${canEdit ? '' : 'disabled'}>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="trig-pay-email" data-setting-key="notify_payment_receipt_email" ${isTrue(s.notify_payment_receipt_email) ? 'checked' : ''} ${canEdit ? '' : 'disabled'}>
                                                </div>
                                            </td>
                                            <td>
                                                <textarea name="tpl_sms_payment_receipt" class="form-control form-control-sm" rows="2" ${canEdit ? '' : 'disabled'}>${UI.esc(s.tpl_sms_payment_receipt || '')}</textarea>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            ${canEdit ? `
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i data-lucide="save" style="width:15px;height:15px;"></i> Save Notification Triggers & Templates
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    </form>
                `;
            }

            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    async function renderCommunicationOutboxTab() {
        const wrap = document.getElementById('lv-settings-body');
        if (!wrap) return;

        try {
            const channelFilter = state.commChannelFilter || '';
            const searchFilter = state.commSearchFilter || '';
            const res = await UI.api(`/communications/logs?channel=${encodeURIComponent(channelFilter)}&search=${encodeURIComponent(searchFilter)}`);
            const rows = res.logs?.data || [];
            const sum = res.summary || {};

            wrap.innerHTML = `
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="lv-kpi-card">
                            <div class="lv-kpi-label">Total Dispatches</div>
                            <div class="lv-kpi-value">${sum.total || 0}</div>
                            <div class="lv-kpi-sub">All SMS & Email events</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="lv-kpi-card">
                            <div class="lv-kpi-label">SMS Notifications</div>
                            <div class="lv-kpi-value text-primary">${sum.sms_count || 0}</div>
                            <div class="lv-kpi-sub">Patient & critical alerts</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="lv-kpi-card">
                            <div class="lv-kpi-label">SMTP Emails</div>
                            <div class="lv-kpi-value" style="color:var(--lv-secondary);">${sum.email_count || 0}</div>
                            <div class="lv-kpi-sub">Formatted HTML reports</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="lv-kpi-card">
                            <div class="lv-kpi-label">Failed / Attention</div>
                            <div class="lv-kpi-value text-danger">${sum.failed_count || 0}</div>
                            <div class="lv-kpi-sub">${sum.sent_count || 0} live • ${sum.simulated_count || 0} sandbox</div>
                        </div>
                    </div>
                </div>

                <div class="lv-filter-bar d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm ${!channelFilter ? 'btn-primary' : 'btn-light-surface'}" onclick="LabverseAdminOps.filterCommLogs('')">All Channels</button>
                        <button type="button" class="btn btn-sm ${channelFilter === 'sms' ? 'btn-primary' : 'btn-light-surface'}" onclick="LabverseAdminOps.filterCommLogs('sms')">SMS Only</button>
                        <button type="button" class="btn btn-sm ${channelFilter === 'email' ? 'btn-primary' : 'btn-light-surface'}" onclick="LabverseAdminOps.filterCommLogs('email')">Email Only</button>
                    </div>
                    <div style="min-width:280px;">
                        <input type="search" class="form-control form-control-sm" placeholder="Search recipient phone, email, or ref code..." value="${UI.esc(searchFilter)}" oninput="LabverseAdminOps.searchCommLogs(this.value)">
                    </div>
                </div>

                <div class="lv-card">
                    <div class="lv-table-container">
                        <table class="lv-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Channel & Provider</th>
                                    <th>Event & Ref</th>
                                    <th>Recipient</th>
                                    <th>Message / Subject Preview</th>
                                    <th>Delivery Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows.length === 0 ? `
                                    <tr><td colspan="7" class="text-center py-4 text-secondary">No communication logs found.</td></tr>
                                ` : rows.map(l => `
                                    <tr>
                                        <td class="small text-nowrap">${UI.formatDateTime(l.sent_at || l.created_at)}</td>
                                        <td>
                                            <span class="lv-badge ${l.channel === 'sms' ? 'lv-badge-info' : 'lv-badge-teal'} text-uppercase">${UI.esc(l.channel)}</span>
                                            <span class="d-block font-mono text-secondary mt-1" style="font-size:0.72rem;">${UI.esc(l.provider)}</span>
                                        </td>
                                        <td>
                                            <div class="font-mono small fw-semibold text-primary">${UI.esc(l.reference_code || '—')}</div>
                                            <div class="small text-secondary">${UI.esc(l.event_type)}</div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold small">${UI.esc(l.recipient_name || 'Recipient')}</div>
                                            <div class="font-mono small text-secondary">${UI.esc(l.recipient)}</div>
                                        </td>
                                        <td style="max-width:340px;">
                                            ${l.subject ? `<div class="fw-semibold small">${UI.esc(l.subject)}</div>` : ''}
                                            <div class="small text-secondary text-truncate" title="${UI.esc(l.message)}">${UI.esc(l.message)}</div>
                                            <div class="text-secondary font-mono" style="font-size:0.7rem;">${UI.esc(l.provider_response || '')}</div>
                                        </td>
                                        <td>
                                            <span class="lv-badge ${l.status === 'sent' ? 'lv-badge-success' : (l.status === 'failed' ? 'lv-badge-danger' : 'lv-badge-info')}">
                                                ${UI.esc(l.status.toUpperCase())}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseAdminOps.retryCommLog(${l.id})">
                                                <i data-lucide="refresh-cw" style="width:13px;height:13px;"></i> Resend
                                            </button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    async function renderAuditLogsTab() {
        const wrap = document.getElementById('lv-settings-body');
        if (!wrap) return;

        try {
            const auditRes = await UI.api('/audit-logs');
            const logs = auditRes.data || [];

            wrap.innerHTML = `
                <div class="lv-card">
                    <div class="lv-card-header">
                        <h2 class="lv-card-title"><i data-lucide="shield-alert" style="width:17px;height:17px;color:var(--lv-secondary);"></i> Immutable Security & Clinical Audit Trail</h2>
                    </div>
                    <div class="lv-table-container">
                        <table class="lv-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Staff User</th>
                                    <th>Module / Action</th>
                                    <th>Reference</th>
                                    <th>Audit Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${logs.map(l => `
                                    <tr>
                                        <td class="small text-nowrap">${UI.formatDateTime(l.created_at)}</td>
                                        <td class="small">
                                            <div class="fw-semibold">${UI.esc(l.user?.name || 'System')}</div>
                                            <div class="text-secondary font-mono">${UI.esc(l.user?.staff_id || '')}</div>
                                        </td>
                                        <td>
                                            <span class="lv-badge lv-badge-neutral">${UI.esc(l.module)}</span>
                                            <span class="d-block font-mono text-secondary mt-1" style="font-size:0.72rem;">${UI.esc(l.action)}</span>
                                        </td>
                                        <td class="font-mono small fw-semibold text-primary">${UI.esc(l.target_reference || '—')}</td>
                                        <td class="small">${UI.esc(l.description)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            UI.refreshIcons();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function filterCommLogs(channel) {
        state.commChannelFilter = channel;
        renderCommunicationOutboxTab();
    }

    function searchCommLogs(q) {
        state.commSearchFilter = q;
        renderCommunicationOutboxTab();
    }

    async function retryCommLog(logId) {
        try {
            const res = await UI.api(`/communications/logs/${logId}/retry`, { method: 'POST' });
            UI.toast(res.message, 'success');
            renderCommunicationOutboxTab();
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    async function saveSystemSettings(form) {
        const settings = Object.fromEntries(new FormData(form).entries());
        try {
            const res = await UI.api('/settings', { method: 'PUT', body: { settings } });
            UI.toast(res.message, 'success');
            renderSettings(state.settingsTab);
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    async function saveTriggerSettings(form) {
        const settings = Object.fromEntries(new FormData(form).entries());
        form.querySelectorAll('input[type="checkbox"][data-setting-key]').forEach(chk => {
            settings[chk.getAttribute('data-setting-key')] = chk.checked ? 'true' : 'false';
        });
        try {
            const res = await UI.api('/settings', { method: 'PUT', body: { settings } });
            UI.toast(res.message, 'success');
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    async function sendTestEmailNow() {
        const recipient = document.getElementById('test-email-recipient')?.value?.trim();
        const form = document.getElementById('lv-smtp-settings-form');
        const settings = form ? Object.fromEntries(new FormData(form).entries()) : {};
        const box = document.getElementById('test-email-result-box');

        if (box) box.innerHTML = `<div class="small text-secondary">Dispatching test email...</div>`;
        try {
            const res = await UI.api('/communications/test-email', {
                method: 'POST',
                body: { recipient_email: recipient, settings },
            });
            UI.toast(res.message, 'success');
            if (box) {
                box.innerHTML = `<div class="p-2 rounded border bg-light small text-success"><strong>Success:</strong> ${UI.esc(res.message)}<br><span class="text-secondary font-mono">${UI.esc(res.log?.provider_response || '')}</span></div>`;
            }
        } catch (err) {
            UI.toast(err.message, 'error');
            if (box) {
                box.innerHTML = `<div class="p-2 rounded border bg-light small text-danger"><strong>Failed:</strong> ${UI.esc(err.message)}</div>`;
            }
        }
    }

    async function sendTestSmsNow() {
        const recipient = document.getElementById('test-sms-recipient')?.value?.trim();
        const form = document.getElementById('lv-sms-settings-form');
        const settings = form ? Object.fromEntries(new FormData(form).entries()) : {};
        const box = document.getElementById('test-sms-result-box');

        if (box) box.innerHTML = `<div class="small text-secondary">Dispatching test SMS...</div>`;
        try {
            const res = await UI.api('/communications/test-sms', {
                method: 'POST',
                body: { recipient_phone: recipient, settings },
            });
            UI.toast(res.message, 'success');
            if (box) {
                box.innerHTML = `<div class="p-2 rounded border bg-light small text-success"><strong>Success:</strong> ${UI.esc(res.message)}<br><span class="text-secondary font-mono">${UI.esc(res.log?.provider_response || '')}</span></div>`;
            }
        } catch (err) {
            UI.toast(err.message, 'error');
            if (box) {
                box.innerHTML = `<div class="p-2 rounded border bg-light small text-danger"><strong>Failed:</strong> ${UI.esc(err.message)}</div>`;
            }
        }
    }

    return {
        renderInventory,
        filterInventory,
        setInvStatusFilter,
        resetInvFilter,
        openNewInventoryModal,
        submitNewInventoryItem,
        openStockAdjustmentModal,
        submitStockAdjustment,
        renderBranches,
        openBranchModal,
        submitBranchForm,
        renderStaffAndRbac,
        renderRolePermissionEditor,
        saveRolePermissions,
        openStaffModal,
        submitStaffForm,
        renderTestCatalogue,
        filterCatalogueCategory,
        searchCatalogue,
        openCatalogueTestModal,
        submitCatalogueTest,
        renderSettings,
        saveSystemSettings,
        saveTriggerSettings,
        sendTestEmailNow,
        sendTestSmsNow,
        filterCommLogs,
        searchCommLogs,
        retryCommLog,
    };
})();

