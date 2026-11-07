/**
 * ============================================================================
 * LABVERSE — Phase 3: Global Application Shell, RBAC Navigation & Dashboard
 * ============================================================================
 */
window.LabverseApp = (function () {
    const UI = window.LabverseUI;

    const state = {
        user: null,
        currentRoute: 'dashboard',
        sidebarCollapsed: false,
        mobileNavOpen: false,
        dashboardDate: new Date().toISOString().split('T')[0],
        dashboardData: null,
        workloadChartInstance: null,
        trendsChartInstance: null,
    };

    // Canonical Permission-Aware Sidebar Navigation Definition
    const NAV_GROUPS = [
        {
            label: 'Clinical Operations',
            items: [
                { id: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard', perms: ['view_dashboard'] },
                { id: 'patients', label: 'Patients', icon: 'users', perms: ['view_patients', 'manage_patients', 'place_order'] },
                { id: 'orders', label: 'Orders', icon: 'clipboard-list', perms: ['view_orders', 'place_order'] },
                { id: 'samples', label: 'Sample Collection', icon: 'test-tube-2', perms: ['collect_sample', 'receive_sample', 'manage_samples'] },
            ],
        },
        {
            label: 'Diagnostic Laboratory',
            items: [
                { id: 'laboratory', label: 'Laboratory', icon: 'microscope', perms: ['view_lab_workspace', 'enter_results'] },
                { id: 'results', label: 'Results', icon: 'file-edit', perms: ['enter_results'] },
                { id: 'approvals', label: 'Approvals', icon: 'shield-check', perms: ['approve_result'], badgeKey: 'awaiting_approval' },
                { id: 'reports', label: 'Reports', icon: 'file-check-2', perms: ['view_reports', 'release_report'] },
            ],
        },
        {
            label: 'Finance & Stock',
            items: [
                { id: 'payments', label: 'Payments', icon: 'credit-card', perms: ['view_payments', 'manage_payments'] },
                { id: 'inventory', label: 'Inventory', icon: 'boxes', perms: ['view_inventory', 'manage_inventory'] },
            ],
        },
        {
            label: 'Administration',
            items: [
                { id: 'branches', label: 'Branches', icon: 'building-2', perms: ['manage_branches', 'view_dashboard'] },
                { id: 'staff', label: 'Staff & Users', icon: 'user-cog', perms: ['manage_users', 'manage_roles'] },
                { id: 'catalogue', label: 'Test Catalogue', icon: 'book-open-check', perms: ['manage_catalogue', 'view_lab_workspace', 'place_order'] },
                { id: 'settings', label: 'Settings', icon: 'settings', perms: ['manage_settings', 'manage_users'] },
            ],
        },
    ];

    function hasPermission(permSlug) {
        if (!state.user) return false;
        if (state.user.is_platform_admin) return true;
        const perms = state.user.permissions || [];
        return perms.includes(permSlug);
    }

    function hasAnyPermission(permArray) {
        if (!permArray || permArray.length === 0) return true;
        return permArray.some(p => hasPermission(p));
    }

    function getCurrentRoute() {
        return state.currentRoute;
    }

    async function init() {
        const token = localStorage.getItem(UI.STORAGE_TOKEN_KEY);
        if (!token) {
            return renderLoginScreen();
        }

        try {
            const res = await UI.api('/auth/me');
            state.user = res.user;
            renderShell();
            navigate(state.currentRoute);
        } catch (_) {
            renderLoginScreen();
        }
    }

    function handleUnauthenticated() {
        state.user = null;
        renderLoginScreen();
    }

    // =========================================================================
    // AUTHENTICATION SCREEN WITH ROLE QUICK-SWITCHER FOR TESTING
    // =========================================================================
    async function renderLoginScreen() {
        const root = document.getElementById('lv-app-root');
        if (!root) return;

        root.innerHTML = `
            <div class="min-vh-100 d-flex align-items-center justify-content-center p-3" style="background-color:#0F294A;">
                <div class="lv-card shadow-lg overflow-hidden w-100" style="max-width:980px;">
                    <div class="row g-0">
                        <div class="col-lg-6 p-4 p-md-5">
                            <div class="d-flex align-items-center gap-2 mb-4">
                                <div class="lv-brand-mark">LV</div>
                                <div>
                                    <div class="fw-bold fs-5" style="color:var(--lv-primary);letter-spacing:0.03em;">LABVERSE</div>
                                    <div class="small text-secondary">Enterprise Laboratory Information Management System</div>
                                </div>
                            </div>

                            <h1 class="h4 fw-bold mb-1">Staff Portal Sign In</h1>
                            <p class="text-secondary small mb-4">Sign in with your authorized laboratory credentials to access your role workspace.</p>

                            <form id="lv-login-form" method="POST" action="/api/auth/login" onsubmit="event.preventDefault(); LabverseApp.submitLogin();">
                                <div class="mb-3">
                                    <label for="login-email" class="form-label">Staff Work Email</label>
                                    <input type="email" id="login-email" name="email" class="form-control" autocomplete="username" required value="admin@labverse.com">
                                </div>
                                <div class="mb-3">
                                    <label for="login-password" class="form-label">Password</label>
                                    <input type="password" id="login-password" name="password" class="form-control" autocomplete="current-password" required value="password123">
                                </div>
                                <button type="submit" id="btn-login-submit" class="btn btn-primary w-100 py-2">
                                    <i data-lucide="log-in" style="width:16px;height:16px;"></i> Sign In to Laboratory Workspace
                                </button>
                            </form>
                        </div>

                        <div class="col-lg-6 p-4 p-md-5 border-start bg-light">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h2 class="h6 fw-bold mb-0 text-primary">One-Click Role Verification Accounts</h2>
                                <span class="lv-badge lv-badge-teal">Sanctum RBAC</span>
                            </div>
                            <p class="small text-secondary mb-3">Click any staff profile below to populate credentials and inspect role-specific RBAC navigation and branch scoping:</p>
                            <div id="lv-demo-accounts-list" class="d-flex flex-column gap-2">
                                ${UI.loadingSpinner('Loading laboratory staff roles...')}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        UI.refreshIcons();

        try {
            const res = await UI.api('/auth/demo-accounts');
            const listEl = document.getElementById('lv-demo-accounts-list');
            if (listEl && res.accounts) {
                listEl.innerHTML = res.accounts.map(acc => `
                    <button type="button" class="btn btn-light-surface text-start p-2 d-flex align-items-center justify-content-between" onclick="LabverseApp.quickSignIn('${UI.esc(acc.email)}')">
                        <div>
                            <div class="fw-semibold text-dark small">${UI.esc(acc.name)} <span class="font-mono text-secondary">(${UI.esc(acc.staff_id)})</span></div>
                            <div class="text-secondary" style="font-size:0.75rem;">${UI.esc(acc.job_title)} • <strong>${UI.esc(acc.branch)}</strong></div>
                        </div>
                        <span class="lv-badge lv-badge-info">${UI.esc((acc.roles || [])[0] || 'Staff')}</span>
                    </button>
                `).join('');
            }
        } catch (_) {}
    }

    function quickSignIn(email) {
        const emailEl = document.getElementById('login-email');
        const passEl = document.getElementById('login-password');
        if (emailEl) emailEl.value = email;
        if (passEl) passEl.value = 'password123';
        submitLogin();
    }

    async function submitLogin() {
        const email = document.getElementById('login-email')?.value;
        const password = document.getElementById('login-password')?.value;
        const btn = document.getElementById('btn-login-submit');
        if (btn) btn.disabled = true;

        try {
            const res = await UI.api('/auth/login', {
                method: 'POST',
                body: { email, password },
            });
            localStorage.setItem(UI.STORAGE_TOKEN_KEY, res.token);
            state.user = res.user;
            if (!state.user.can_switch_branches && state.user.branch_id) {
                localStorage.setItem(UI.STORAGE_BRANCH_KEY, String(state.user.branch_id));
            }
            UI.toast(`Welcome back, ${state.user.name}`, 'success');
            renderShell();
            navigate('dashboard');
        } catch (err) {
            UI.toast(err.message, 'error');
            if (btn) btn.disabled = false;
        }
    }

    async function logout() {
        try {
            await UI.api('/auth/logout', { method: 'POST' });
        } catch (_) {}
        localStorage.removeItem(UI.STORAGE_TOKEN_KEY);
        state.user = null;
        renderLoginScreen();
    }

    // =========================================================================
    // GLOBAL APPLICATION SHELL (SIDEBAR + TOPBAR + MAIN WORKSPACE)
    // =========================================================================
    function renderShell() {
        const root = document.getElementById('lv-app-root');
        if (!root || !state.user) return;

        const u = state.user;
        const currentBranch = localStorage.getItem(UI.STORAGE_BRANCH_KEY) || (u.can_switch_branches ? 'all' : String(u.branch_id || ''));

        root.innerHTML = `
            <div id="lv-shell-container" class="lv-shell ${state.sidebarCollapsed ? 'sidebar-collapsed' : ''}">
                <!-- Left Permission-Aware Sidebar -->
                <aside class="lv-sidebar" aria-label="Primary Laboratory Navigation">
                    <div class="lv-sidebar-brand">
                        <a href="#dashboard" class="lv-brand-logo" onclick="event.preventDefault(); LabverseApp.navigate('dashboard');">
                            <div class="lv-brand-mark">LV</div>
                            <div class="lv-brand-text">
                                <span class="lv-brand-title">LABVERSE</span>
                                <span class="lv-brand-subtitle">Enterprise LIMS</span>
                            </div>
                        </a>
                        <button type="button" class="btn btn-sm text-white p-1 d-none d-lg-inline-flex" aria-label="Toggle Sidebar Collapse" title="Collapse / Expand Sidebar" onclick="LabverseApp.toggleSidebarCollapse()">
                            <i data-lucide="panel-left-close" style="width:17px;height:17px;"></i>
                        </button>
                    </div>

                    <nav class="lv-sidebar-nav" id="lv-sidebar-nav-items">
                        ${renderSidebarNavItemsHtml()}
                    </nav>

                    <div class="lv-sidebar-footer">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0" style="width:34px;height:34px;background:var(--lv-primary);font-size:0.8rem;">
                            ${UI.esc((u.first_name?.[0] || 'L') + (u.last_name?.[0] || 'V'))}
                        </div>
                        <div class="lv-sidebar-footer-details overflow-hidden">
                            <div class="fw-semibold text-white text-truncate small">${UI.esc(u.name)}</div>
                            <div class="text-truncate" style="font-size:0.72rem;color:#94A3B8;">${UI.esc((u.roles?.[0]?.name) || u.job_title || 'Staff')}</div>
                        </div>
                    </div>
                </aside>

                <!-- Main Content Wrapper -->
                <div class="lv-main-wrapper">
                    <!-- Top Navigation Bar -->
                    <header class="lv-topbar">
                        <div class="d-flex align-items-center gap-2 flex-grow-1">
                            <button type="button" class="btn btn-light-surface btn-sm d-lg-none" aria-label="Open Navigation Drawer" onclick="LabverseApp.toggleMobileNav()">
                                <i data-lucide="menu" style="width:18px;height:18px;"></i>
                            </button>

                            <div class="lv-global-search-wrap">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white border-end-0"><i data-lucide="search" style="width:15px;height:15px;color:#64748B;"></i></span>
                                    <input type="search" id="lv-global-search-input" class="form-control border-start-0" placeholder="Global search: Patient ID, Name, Order #, Sample Barcode, Report #..." autocomplete="off" oninput="LabverseApp.handleGlobalSearch(this.value)">
                                </div>
                                <div id="lv-global-search-results" class="lv-global-search-dropdown d-none"></div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <!-- Current Branch Selector -->
                            <div class="d-flex align-items-center gap-1">
                                <i data-lucide="building-2" class="text-secondary d-none d-md-inline" style="width:15px;height:15px;"></i>
                                <select id="lv-branch-selector" class="form-select form-select-sm fw-medium" style="max-width:230px;" aria-label="Select Active Laboratory Branch" ${u.can_switch_branches ? '' : 'disabled'} onchange="LabverseApp.switchBranch(this.value)">
                                    ${u.can_switch_branches ? `<option value="all" ${currentBranch === 'all' ? 'selected' : ''}>All Branches (Network View)</option>` : ''}
                                    ${(u.accessible_branches || []).map(b => `
                                        <option value="${b.id}" ${String(currentBranch) === String(b.id) ? 'selected' : ''}>${UI.esc(b.name)}</option>
                                    `).join('')}
                                </select>
                            </div>

                            <!-- Notifications Button -->
                            <button type="button" class="btn btn-light-surface btn-sm position-relative" aria-label="Clinical Notifications" title="Notifications & Critical Alerts" onclick="LabverseApp.openNotificationsDrawer()">
                                <i data-lucide="bell" style="width:16px;height:16px;"></i>
                                <span id="lv-notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" style="font-size:0.65rem;">0</span>
                            </button>

                            <!-- Help Centre Button -->
                            <button type="button" class="btn btn-light-surface btn-sm d-none d-sm-inline-flex" title="Clinical LIMS Help Guide" onclick="LabverseApp.openHelpModal()">
                                <i data-lucide="help-circle" style="width:16px;height:16px;"></i>
                                <span class="d-none d-xl-inline">Help</span>
                            </button>

                            <!-- Account Profile Button -->
                            <button type="button" class="btn btn-light-surface btn-sm" title="Account Profile & Password" onclick="LabverseApp.openAccountProfileModal()">
                                <i data-lucide="user" style="width:16px;height:16px;"></i>
                                <span class="d-none d-md-inline">${UI.esc(u.first_name || u.name)}</span>
                            </button>

                            <!-- Logout Button -->
                            <button type="button" class="btn btn-outline-danger btn-sm" title="Sign Out" onclick="LabverseApp.logout()">
                                <i data-lucide="log-out" style="width:15px;height:15px;"></i>
                                <span class="d-none d-xl-inline">Logout</span>
                            </button>
                        </div>
                    </header>

                    <!-- Main Operational Workspace -->
                    <main class="lv-workspace" id="lv-workspace-content" role="main"></main>
                </div>
            </div>
        `;
        UI.refreshIcons();
        refreshNotificationBadge();
    }

    function renderSidebarNavItemsHtml() {
        return NAV_GROUPS.map(group => {
            const visibleItems = group.items.filter(item => hasAnyPermission(item.perms));
            if (visibleItems.length === 0) return '';

            return `
                <div class="lv-nav-section-label">${UI.esc(group.label)}</div>
                ${visibleItems.map(item => `
                    <button type="button"
                        class="lv-nav-item ${state.currentRoute === item.id ? 'active' : ''}"
                        data-route="${item.id}"
                        title="${UI.esc(item.label)}"
                        onclick="LabverseApp.navigate('${item.id}')">
                        <i data-lucide="${item.icon}" class="lv-nav-icon"></i>
                        <span class="lv-nav-label">${UI.esc(item.label)}</span>
                    </button>
                `).join('')}
            `;
        }).join('');
    }

    function toggleSidebarCollapse() {
        state.sidebarCollapsed = !state.sidebarCollapsed;
        const shell = document.getElementById('lv-shell-container');
        if (shell) shell.classList.toggle('sidebar-collapsed', state.sidebarCollapsed);
    }

    function toggleMobileNav() {
        state.mobileNavOpen = !state.mobileNavOpen;
        const shell = document.getElementById('lv-shell-container');
        if (shell) shell.classList.toggle('mobile-nav-open', state.mobileNavOpen);
    }

    function switchBranch(branchId) {
        localStorage.setItem(UI.STORAGE_BRANCH_KEY, branchId);
        const sel = document.getElementById('lv-branch-selector');
        if (sel) sel.value = branchId;
        UI.toast('Active branch scope updated.', 'info');
        navigate(state.currentRoute);
    }

    // Route Dispatcher
    function navigate(routeId, options = {}) {
        state.currentRoute = routeId;
        state.mobileNavOpen = false;
        const shell = document.getElementById('lv-shell-container');
        if (shell) shell.classList.remove('mobile-nav-open');

        // Highlight active item in sidebar
        document.querySelectorAll('.lv-nav-item').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-route') === routeId);
        });

        switch (routeId) {
            case 'dashboard':
                renderDashboard();
                break;
            case 'patients':
                window.LabverseCoreOps.renderPatients(1);
                break;
            case 'orders':
                window.LabverseCoreOps.renderOrders(options);
                break;
            case 'samples':
                window.LabverseCoreOps.renderSamples(1);
                break;
            case 'laboratory':
                window.LabverseLabOps.renderLaboratoryWorkspace(1);
                break;
            case 'results':
                window.LabverseLabOps.renderResultsModule(1);
                break;
            case 'approvals':
                window.LabverseLabOps.renderApprovalsModule(1);
                break;
            case 'reports':
                window.LabverseLabOps.renderReportsModule(1);
                break;
            case 'payments':
                window.LabverseCoreOps.renderPayments(1);
                break;
            case 'inventory':
                window.LabverseAdminOps.renderInventory();
                break;
            case 'branches':
                window.LabverseAdminOps.renderBranches();
                break;
            case 'staff':
                window.LabverseAdminOps.renderStaffAndRbac();
                break;
            case 'catalogue':
                window.LabverseAdminOps.renderTestCatalogue();
                break;
            case 'settings':
                window.LabverseAdminOps.renderSettings();
                break;
            default:
                renderDashboard();
        }
    }

    // =========================================================================
    // 5. OPERATIONAL DASHBOARD IMPLEMENTATION
    // =========================================================================
    async function renderDashboard() {
        const container = document.getElementById('lv-workspace-content');
        if (!container) return;

        const canOrder = hasPermission('place_order');
        const canRegister = hasPermission('manage_patients');

        container.innerHTML = UI.pageHeader({
            breadcrumb: 'Operational Dashboard',
            title: 'Laboratory Operations Overview',
            description: 'Real-time diagnostic metrics, specimen turnaround queues, critical value alerts, and branch performance.',
            actionsHtml: `
                <div class="d-flex align-items-center gap-2">
                    <label for="dash-date-picker" class="small text-secondary fw-medium mb-0">Date:</label>
                    <input type="date" id="dash-date-picker" class="form-control form-control-sm" style="width:150px;" value="${UI.esc(state.dashboardDate)}" onchange="LabverseApp.updateDashboardDate(this.value)">
                    ${canRegister ? `
                        <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseCoreOps.openPatientModal()">
                            <i data-lucide="user-plus" style="width:15px;height:15px;"></i> New Patient
                        </button>
                    ` : ''}
                    ${canOrder ? `
                        <button type="button" class="btn btn-primary btn-sm" onclick="LabverseApp.navigate('orders', { mode: 'new' })">
                            <i data-lucide="plus-circle" style="width:15px;height:15px;"></i> New Test Order
                        </button>
                    ` : ''}
                </div>
            `,
        }) + `<div id="lv-dashboard-body">${UI.loadingSpinner('Fetching real-time laboratory telemetry...')}</div>`;
        UI.refreshIcons();

        try {
            const data = await UI.api(`/dashboard?date=${encodeURIComponent(state.dashboardDate)}`);
            state.dashboardData = data;
            const k = data.kpis || {};
            const dashBody = document.getElementById('lv-dashboard-body');
            if (!dashBody) return;

            dashBody.innerHTML = `
                <!-- 8 Primary Operational KPI Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">${UI.statCard({ label: 'Patients Today', value: k.patients_today ?? 0, meta: `Total Registry: ${k.patients_total ?? 0}`, icon: 'users', tone: 'primary', onClickAttr: `onclick="LabverseApp.navigate('patients')"` })}</div>
                    <div class="col-6 col-md-3">${UI.statCard({ label: 'Test Orders Today', value: k.orders_today ?? 0, meta: `Total Orders: ${k.orders_total ?? 0}`, icon: 'clipboard-list', tone: 'secondary', onClickAttr: `onclick="LabverseApp.navigate('orders')"` })}</div>
                    <div class="col-6 col-md-3">${UI.statCard({ label: 'Pending Samples', value: k.pending_samples ?? 0, meta: 'Awaiting collection / reception', icon: 'test-tube-2', tone: 'warning', onClickAttr: `onclick="LabverseApp.navigate('samples')"` })}</div>
                    <div class="col-6 col-md-3">${UI.statCard({ label: 'Samples Processing', value: k.samples_processing ?? 0, meta: 'Accepted on lab benches', icon: 'microscope', tone: 'info', onClickAttr: `onclick="LabverseApp.navigate('laboratory')"` })}</div>
                    <div class="col-6 col-md-3">${UI.statCard({ label: 'Pending Results', value: k.pending_results ?? 0, meta: 'In assay / draft entry', icon: 'file-edit', tone: 'primary', onClickAttr: `onclick="LabverseApp.navigate('results')"` })}</div>
                    <div class="col-6 col-md-3">${UI.statCard({ label: 'Awaiting Approval', value: k.awaiting_approval ?? 0, meta: 'Submitted for supervisor verification', icon: 'shield-check', tone: 'warning', onClickAttr: `onclick="LabverseApp.navigate('approvals')"` })}</div>
                    <div class="col-6 col-md-3">${UI.statCard({ label: 'Released Reports', value: k.released_reports ?? 0, meta: 'Verified & ready for print/PDF', icon: 'file-check-2', tone: 'success', onClickAttr: `onclick="LabverseApp.navigate('reports')"` })}</div>
                    <div class="col-6 col-md-3">${UI.statCard({ label: 'Outstanding Payments', value: UI.formatCurrency(k.outstanding_payments_amount), meta: `${k.outstanding_payments_count ?? 0} unpaid orders • Today: ${UI.formatCurrency(k.revenue_today)}`, icon: 'credit-card', tone: 'danger', onClickAttr: `onclick="LabverseApp.navigate('payments')"` })}</div>
                </div>

                <!-- G. Critical Alerts & Clinical Notifications Strip -->
                ${(data.notifications || []).some(n => !n.is_read) ? `
                    <div class="lv-card mb-4 border-danger">
                        <div class="lv-card-header bg-danger-subtle">
                            <h2 class="lv-card-title text-danger"><i data-lucide="alert-octagon" style="width:17px;height:17px;"></i> Active Clinical & Operational Alerts</h2>
                            <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseApp.markAllNotificationsRead()">Acknowledge All</button>
                        </div>
                        <div class="lv-card-body py-2">
                            ${(data.notifications || []).filter(n => !n.is_read).slice(0, 3).map(n => `
                                <div class="d-flex align-items-center justify-content-between py-2 border-bottom last-border-0 gap-2">
                                    <div>
                                        <span class="lv-badge ${n.type === 'critical' ? 'lv-badge-critical' : 'lv-badge-warning'} me-2">${UI.esc(n.category)}</span>
                                        <strong class="small">${UI.esc(n.title)}:</strong>
                                        <span class="small text-secondary ms-1">${UI.esc(n.message)}</span>
                                    </div>
                                    ${n.action_route ? `<button type="button" class="btn btn-outline-primary btn-sm flex-shrink-0" onclick="LabverseApp.navigate('${UI.esc(n.action_route)}')">Open Workspace</button>` : ''}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}

                <!-- A & B: Laboratory Workload Chart & Patient Registration Trends -->
                <div class="row g-4 mb-4">
                    <div class="col-12 col-lg-6">
                        <div class="lv-card h-100">
                            <div class="lv-card-header">
                                <h2 class="lv-card-title"><i data-lucide="bar-chart-3" style="width:17px;height:17px;color:var(--lv-primary);"></i> Laboratory Workload by Clinical Department</h2>
                            </div>
                            <div class="lv-card-body">
                                <div style="height:250px;"><canvas id="lv-workload-chart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="lv-card h-100">
                            <div class="lv-card-header">
                                <h2 class="lv-card-title"><i data-lucide="trending-up" style="width:17px;height:17px;color:var(--lv-secondary);"></i> Patient Registrations & Order Volume Trends</h2>
                            </div>
                            <div class="lv-card-body">
                                <div style="height:250px;"><canvas id="lv-trends-chart"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- D & C: Pending Laboratory Tasks + Recent Patient Registrations -->
                <div class="row g-4 mb-4">
                    <div class="col-12 col-xl-7">
                        <div class="lv-card h-100">
                            <div class="lv-card-header">
                                <h2 class="lv-card-title"><i data-lucide="flask-conical" style="width:17px;height:17px;color:var(--lv-primary);"></i> Pending Laboratory Bench & Verification Tasks</h2>
                                <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseApp.navigate('laboratory')">Open Full Queue</button>
                            </div>
                            <div class="lv-table-container">
                                <table class="lv-table">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Patient</th>
                                            <th>Assay</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${(data.pending_lab_tasks || []).map(t => `
                                            <tr>
                                                <td class="font-mono fw-semibold text-primary">${UI.esc(t.order?.order_number)}</td>
                                                <td>${UI.esc(t.order?.patient?.full_name)}</td>
                                                <td>
                                                    <div class="fw-medium">${UI.esc(t.test?.name)}</div>
                                                    <div class="small text-secondary font-mono">${UI.esc(t.sample?.barcode || '')}</div>
                                                </td>
                                                <td>${UI.statusBadge(t.order?.priority)}</td>
                                                <td>${UI.statusBadge(t.status)}</td>
                                                <td class="text-end">
                                                    ${t.status === 'submitted' && hasPermission('approve_result') ? `
                                                        <button type="button" class="btn btn-primary btn-sm" onclick="LabverseLabOps.openApprovalReviewDrawer(${t.id})">Verify</button>
                                                    ` : `
                                                        <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseLabOps.openResultEntryModal(${t.id})">Open</button>
                                                    `}
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-5">
                        <div class="lv-card h-100">
                            <div class="lv-card-header">
                                <h2 class="lv-card-title"><i data-lucide="users" style="width:17px;height:17px;color:var(--lv-secondary);"></i> Recent Patient Registrations</h2>
                                <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseApp.navigate('patients')">All Patients</button>
                            </div>
                            <div class="lv-table-container">
                                <table class="lv-table">
                                    <thead>
                                        <tr>
                                            <th>Patient ID</th>
                                            <th>Name & Demographics</th>
                                            <th>Branch</th>
                                            <th class="text-end">Profile</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${(data.recent_patients || []).map(p => `
                                            <tr>
                                                <td class="font-mono fw-semibold text-primary">${UI.esc(p.patient_code)}</td>
                                                <td>
                                                    <div class="fw-semibold">${UI.esc(p.full_name)}</div>
                                                    <div class="small text-secondary">${UI.esc(p.gender)} • ${p.age ?? '—'} yrs • ${UI.esc(p.phone)}</div>
                                                </td>
                                                <td><span class="lv-badge lv-badge-neutral">${UI.esc(p.branch?.code || '')}</span></td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseCoreOps.viewPatientProfile(${p.id})">View</button>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- E & F: Outstanding Payments & Recent Activities -->
                <div class="row g-4 mb-4">
                    <div class="col-12 col-lg-6">
                        <div class="lv-card h-100">
                            <div class="lv-card-header">
                                <h2 class="lv-card-title"><i data-lucide="wallet" style="width:17px;height:17px;color:#B91C1C;"></i> Outstanding Patient Balances</h2>
                                <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseApp.navigate('payments')">Billing Desk</button>
                            </div>
                            <div class="lv-table-container">
                                <table class="lv-table">
                                    <thead>
                                        <tr><th>Order #</th><th>Patient</th><th>Total</th><th>Balance Due</th><th class="text-end">Collect</th></tr>
                                    </thead>
                                    <tbody>
                                        ${(data.outstanding_orders || []).map(o => `
                                            <tr>
                                                <td class="font-mono fw-semibold">${UI.esc(o.order_number)}</td>
                                                <td>${UI.esc(o.patient?.full_name)}</td>
                                                <td class="font-mono">${UI.formatCurrency(o.total_amount)}</td>
                                                <td class="font-mono fw-bold text-danger">${UI.formatCurrency(o.balance_due)}</td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="LabverseCoreOps.openRecordPaymentModal(${o.id}, '${UI.esc(o.order_number)}', ${o.balance_due})">Pay</button>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="lv-card h-100">
                            <div class="lv-card-header">
                                <h2 class="lv-card-title"><i data-lucide="activity" style="width:17px;height:17px;color:var(--lv-primary);"></i> Recent Laboratory Audit Activities</h2>
                            </div>
                            <div class="lv-card-body py-2">
                                ${(data.recent_activities || []).map(act => `
                                    <div class="d-flex justify-content-between align-items-start py-2 border-bottom small gap-2">
                                        <div>
                                            <span class="lv-badge lv-badge-neutral me-1">${UI.esc(act.module)}</span>
                                            <span class="text-dark">${UI.esc(act.description)}</span>
                                            <div class="text-secondary mt-1" style="font-size:0.74rem;">By: ${UI.esc(act.user?.name || 'System')} • ${UI.esc(act.branch?.name || '')}</div>
                                        </div>
                                        <span class="text-secondary text-nowrap" style="font-size:0.74rem;">${UI.formatDateTime(act.created_at)}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- H. Multi-Branch Performance Table (Authorized Users) -->
                ${(data.branch_performance || []).length > 0 ? `
                    <div class="lv-card">
                        <div class="lv-card-header">
                            <h2 class="lv-card-title"><i data-lucide="building-2" style="width:17px;height:17px;color:var(--lv-primary);"></i> Multi-Branch Operational Comparison</h2>
                        </div>
                        <div class="lv-table-container">
                            <table class="lv-table">
                                <thead>
                                    <tr>
                                        <th>Branch Code</th>
                                        <th>Branch Facility Name</th>
                                        <th>Patients</th>
                                        <th>Total Orders</th>
                                        <th>Pending Samples</th>
                                        <th>Awaiting Approval</th>
                                        <th>Released Reports</th>
                                        <th>Revenue Collected</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.branch_performance.map(bp => `
                                        <tr>
                                            <td class="font-mono fw-bold text-primary">${UI.esc(bp.code)}</td>
                                            <td class="fw-semibold">${UI.esc(bp.name)} (${UI.esc(bp.city)})</td>
                                            <td>${bp.patients_count}</td>
                                            <td>${bp.orders_count}</td>
                                            <td>${bp.pending_samples}</td>
                                            <td>${bp.awaiting_approval}</td>
                                            <td>${bp.released_reports}</td>
                                            <td class="font-mono fw-bold text-success">${UI.formatCurrency(bp.revenue_collected)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ` : ''}
            `;
            UI.refreshIcons();
            renderDashboardCharts(data);
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function updateDashboardDate(val) {
        state.dashboardDate = val;
        renderDashboard();
    }

    function renderDashboardCharts(data) {
        if (!window.Chart) return;

        const workloadCtx = document.getElementById('lv-workload-chart');
        if (workloadCtx) {
            if (state.workloadChartInstance) state.workloadChartInstance.destroy();
            const wl = data.workload_by_category || [];
            state.workloadChartInstance = new window.Chart(workloadCtx, {
                type: 'bar',
                data: {
                    labels: wl.map(w => w.category),
                    datasets: [
                        {
                            label: 'Approved / Completed',
                            data: wl.map(w => w.completed),
                            backgroundColor: '#0F9D9A',
                        },
                        {
                            label: 'In Progress / Submitted',
                            data: wl.map(w => w.in_progress),
                            backgroundColor: '#164E8A',
                        },
                        {
                            label: 'Awaiting Sample',
                            data: wl.map(w => w.pending_sample),
                            backgroundColor: '#E6B85C',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } },
                },
            });
        }

        const trendsCtx = document.getElementById('lv-trends-chart');
        if (trendsCtx) {
            if (state.trendsChartInstance) state.trendsChartInstance.destroy();
            const tr = data.registration_trends || [];
            state.trendsChartInstance = new window.Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: tr.map(t => t.label),
                    datasets: [
                        {
                            label: 'Test Orders',
                            data: tr.map(t => t.orders),
                            borderColor: '#164E8A',
                            backgroundColor: 'rgba(22, 78, 138, 0.1)',
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: 'New Patients',
                            data: tr.map(t => t.patients),
                            borderColor: '#0F9D9A',
                            backgroundColor: 'rgba(15, 157, 154, 0.1)',
                            tension: 0.3,
                            fill: true,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                },
            });
        }
    }

    // =========================================================================
    // GLOBAL SEARCH, NOTIFICATIONS, PROFILE & NATIVE DIALOGS/DRAWERS
    // =========================================================================
    async function handleGlobalSearch(q) {
        const box = document.getElementById('lv-global-search-results');
        if (!box) return;
        if (!q || q.trim().length < 2) {
            box.classList.add('d-none');
            box.innerHTML = '';
            return;
        }

        try {
            const res = await UI.api(`/search?q=${encodeURIComponent(q.trim())}`);
            const hasAny = (res.patients?.length || 0) + (res.orders?.length || 0) + (res.samples?.length || 0) + (res.reports?.length || 0) > 0;
            box.classList.remove('d-none');

            if (!hasAny) {
                box.innerHTML = `<div class="p-3 small text-secondary text-center">No matching patients, orders, samples, or reports found.</div>`;
                return;
            }

            box.innerHTML = `
                <div class="p-2">
                    ${(res.patients || []).length > 0 ? `
                        <div class="small fw-bold text-uppercase text-secondary px-2 py-1">Patients</div>
                        ${res.patients.map(p => `
                            <button type="button" class="btn btn-light-surface btn-sm w-100 text-start mb-1 d-flex justify-content-between" onclick="LabverseApp.hideGlobalSearch(); LabverseCoreOps.viewPatientProfile(${p.id})">
                                <span><strong class="font-mono text-primary">${UI.esc(p.patient_code)}</strong> ${UI.esc(p.full_name)}</span>
                                <span class="small text-secondary">${UI.esc(p.phone)}</span>
                            </button>
                        `).join('')}
                    ` : ''}
                    ${(res.orders || []).length > 0 ? `
                        <div class="small fw-bold text-uppercase text-secondary px-2 py-1 mt-1">Laboratory Orders</div>
                        ${res.orders.map(o => `
                            <button type="button" class="btn btn-light-surface btn-sm w-100 text-start mb-1 d-flex justify-content-between" onclick="LabverseApp.hideGlobalSearch(); LabverseCoreOps.viewOrderDetails(${o.id})">
                                <span><strong class="font-mono text-primary">${UI.esc(o.order_number)}</strong> — ${UI.esc(o.patient?.full_name || '')}</span>
                                ${UI.statusBadge(o.status)}
                            </button>
                        `).join('')}
                    ` : ''}
                    ${(res.samples || []).length > 0 ? `
                        <div class="small fw-bold text-uppercase text-secondary px-2 py-1 mt-1">Specimens / Barcodes</div>
                        ${res.samples.map(s => `
                            <button type="button" class="btn btn-light-surface btn-sm w-100 text-start mb-1 d-flex justify-content-between" onclick="LabverseApp.hideGlobalSearch(); LabverseCoreOps.openSampleInspector(${s.id}, '${UI.esc(s.barcode)}')">
                                <span><strong class="font-mono text-primary">${UI.esc(s.barcode)}</strong> (${UI.esc(s.sample_type)})</span>
                                ${UI.statusBadge(s.status)}
                            </button>
                        `).join('')}
                    ` : ''}
                    ${(res.reports || []).length > 0 ? `
                        <div class="small fw-bold text-uppercase text-secondary px-2 py-1 mt-1">Medical Reports</div>
                        ${res.reports.map(r => `
                            <button type="button" class="btn btn-light-surface btn-sm w-100 text-start mb-1 d-flex justify-content-between" onclick="LabverseApp.hideGlobalSearch(); LabverseLabOps.openReportPreview(${r.id})">
                                <span><strong class="font-mono text-primary">${UI.esc(r.report_number)}</strong> — ${UI.esc(r.patient?.full_name || '')}</span>
                                <span class="lv-badge lv-badge-success">Released</span>
                            </button>
                        `).join('')}
                    ` : ''}
                </div>
            `;
        } catch (_) {}
    }

    function hideGlobalSearch() {
        const box = document.getElementById('lv-global-search-results');
        if (box) box.classList.add('d-none');
    }

    async function refreshNotificationBadge() {
        try {
            const res = await UI.api('/notifications');
            const badge = document.getElementById('lv-notif-badge');
            if (badge) {
                const count = res.unread_count || 0;
                badge.textContent = count;
                badge.classList.toggle('d-none', count === 0);
            }
        } catch (_) {}
    }

    async function openNotificationsDrawer() {
        openDrawer(UI.loadingSpinner('Loading clinical alerts & notifications...'));
        try {
            const res = await UI.api('/notifications');
            const list = res.notifications || [];
            const html = `
                <div class="lv-drawer-header">
                    <h5 class="mb-0 fw-bold">Clinical Alerts & System Notifications</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light-surface btn-sm" onclick="LabverseApp.markAllNotificationsRead()">Mark All Read</button>
                        <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDrawer()"></button>
                    </div>
                </div>
                <div class="lv-drawer-body">
                    ${list.length === 0 ? UI.emptyState({ icon: 'bell', title: 'No notifications', message: 'You are all caught up.' }) : list.map(n => `
                        <div class="p-3 border rounded mb-2 ${n.is_read ? 'bg-white' : 'bg-light border-primary'}">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="lv-badge ${n.type === 'critical' ? 'lv-badge-critical' : 'lv-badge-warning'}">${UI.esc(n.category)}</span>
                                <span class="small text-secondary">${UI.formatDateTime(n.created_at)}</span>
                            </div>
                            <div class="fw-bold small mb-1">${UI.esc(n.title)}</div>
                            <div class="small text-secondary mb-2">${UI.esc(n.message)}</div>
                            ${n.action_route ? `<button type="button" class="btn btn-outline-primary btn-sm" onclick="LabverseApp.closeDrawer(); LabverseApp.navigate('${UI.esc(n.action_route)}')">Go to ${UI.esc(n.action_route)}</button>` : ''}
                        </div>
                    `).join('')}
                </div>
            `;
            openDrawer(html);
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    async function markAllNotificationsRead() {
        try {
            await UI.api('/notifications/read-all', { method: 'POST' });
            refreshNotificationBadge();
            closeDrawer();
            if (state.currentRoute === 'dashboard') renderDashboard();
        } catch (_) {}
    }

    function openAccountProfileModal() {
        const u = state.user;
        if (!u) return;

        const html = `
            <form method="POST" onsubmit="event.preventDefault(); LabverseApp.submitProfileUpdate(this);">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">My Staff Account Profile — ${UI.esc(u.staff_id)}</h5>
                    <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDialog()"></button>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="prof-fname" class="form-label">First Name</label>
                            <input type="text" id="prof-fname" name="first_name" class="form-control" required value="${UI.esc(u.first_name || '')}">
                        </div>
                        <div class="col-md-6">
                            <label for="prof-lname" class="form-label">Last Name</label>
                            <input type="text" id="prof-lname" name="last_name" class="form-control" required value="${UI.esc(u.last_name || '')}">
                        </div>
                        <div class="col-md-6">
                            <label for="prof-phone" class="form-label">Phone Number</label>
                            <input type="tel" id="prof-phone" name="phone" class="form-control" value="${UI.esc(u.phone || '')}">
                        </div>
                        <div class="col-md-6">
                            <label for="prof-title" class="form-label">Job Title</label>
                            <input type="text" id="prof-title" name="job_title" class="form-control" value="${UI.esc(u.job_title || '')}">
                        </div>
                    </div>
                    <div class="mt-3 p-3 rounded border bg-light small">
                        <div class="fw-bold text-secondary text-uppercase mb-1">Active RBAC Roles & Permissions (${(u.permissions || []).length})</div>
                        <div class="d-flex flex-wrap gap-1">
                            ${(u.permissions || []).map(p => `<span class="lv-badge lv-badge-neutral font-mono">${UI.esc(p)}</span>`).join('')}
                        </div>
                    </div>
                </div>
                <div class="p-3 border-top bg-light d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light-surface" onclick="LabverseApp.closeDialog()">Close</button>
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </div>
            </form>
        `;
        openDialog(html);
    }

    async function submitProfileUpdate(form) {
        const payload = Object.fromEntries(new FormData(form).entries());
        try {
            const res = await UI.api('/auth/profile', { method: 'PUT', body: payload });
            state.user = res.user;
            UI.toast(res.message, 'success');
            closeDialog();
            renderShell();
            navigate(state.currentRoute);
        } catch (err) {
            UI.toast(err.message, 'error');
        }
    }

    function openHelpModal() {
        const html = `
            <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Labverse Standard Operating Procedures (SOP) Guide</h5>
                <button type="button" class="btn-close" aria-label="Close" onclick="LabverseApp.closeDialog()"></button>
            </div>
            <div class="p-4 small">
                <ol class="mb-0 ps-3 d-flex flex-column gap-2">
                    <li><strong>Patient Registration:</strong> Receptionists verify patient identity or register a new demographic profile in <em>Patients</em>, then click <strong>Save & Proceed to Test Order</strong>.</li>
                    <li><strong>Test Ordering & Barcoding:</strong> Select diagnostic panels in <em>Orders</em>. Submitting generates unique specimen containers and barcodes per sample type.</li>
                    <li><strong>Specimen Lifecycle:</strong> Phlebotomists and Technicians log specimen collection, laboratory reception, and acceptance in <em>Sample Collection</em>.</li>
                    <li><strong>Laboratory Result Entry:</strong> Once a specimen is <em>Accepted</em>, Scientists open the assay in <em>Laboratory</em> or <em>Results</em>, enter values against sex-specific reference intervals, and click <strong>Submit Results for Review</strong>.</li>
                    <li><strong>Supervisor Verification & Report Release:</strong> Supervisors verify submitted results in <em>Approvals</em>. Once all tests on an order are approved, the official report is unlocked in <em>Reports</em>.</li>
                </ol>
            </div>
            <div class="p-3 border-top bg-light text-end">
                <button type="button" class="btn btn-primary btn-sm" onclick="LabverseApp.closeDialog()">Got It</button>
            </div>
        `;
        openDialog(html);
    }

    // Native <dialog> Modal Helper
    function openDialog(innerHtml, large = false) {
        const dlg = document.getElementById('lv-native-dialog');
        if (!dlg) return;
        dlg.className = `lv-dialog ${large ? 'lv-dialog-lg' : ''}`;
        dlg.innerHTML = innerHtml;
        if (!dlg.open) dlg.showModal();
        UI.refreshIcons();
    }

    function closeDialog() {
        const dlg = document.getElementById('lv-native-dialog');
        if (dlg && dlg.open) dlg.close();
    }

    // Contextual Slide-Over Drawer Helper
    function openDrawer(innerHtml, xl = false) {
        const host = document.getElementById('lv-drawer-host');
        if (!host) return;
        host.classList.remove('d-none');
        host.innerHTML = `
            <div class="lv-drawer-backdrop" onclick="if(event.target === this) LabverseApp.closeDrawer()">
                <div class="lv-drawer ${xl ? 'lv-drawer-xl' : ''}" role="dialog" aria-modal="true">
                    ${innerHtml}
                </div>
            </div>
        `;
        UI.refreshIcons();
    }

    function closeDrawer() {
        const host = document.getElementById('lv-drawer-host');
        if (!host) return;
        host.classList.add('d-none');
        host.innerHTML = '';
    }

    return {
        init,
        hasPermission,
        hasAnyPermission,
        getCurrentRoute,
        handleUnauthenticated,
        quickSignIn,
        submitLogin,
        logout,
        toggleSidebarCollapse,
        toggleMobileNav,
        switchBranch,
        navigate,
        renderDashboard,
        updateDashboardDate,
        handleGlobalSearch,
        hideGlobalSearch,
        openNotificationsDrawer,
        markAllNotificationsRead,
        openAccountProfileModal,
        submitProfileUpdate,
        openHelpModal,
        openDialog,
        closeDialog,
        openDrawer,
        closeDrawer,
    };
})();

document.addEventListener('DOMContentLoaded', () => {
    window.LabverseApp.init();
});

