/**
 * ============================================================================
 * LABVERSE — Phase 2: Centralized API Client, Auth & Reusable Component Library
 * ============================================================================
 */
window.LabverseUI = (function () {
    const STORAGE_TOKEN_KEY = 'labverse_sanctum_token';
    const STORAGE_BRANCH_KEY = 'labverse_selected_branch';

    // Escape HTML to prevent XSS
    function esc(val) {
        if (val === null || val === undefined) return '';
        return String(val)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatCurrency(amount) {
        const num = Number(amount || 0);
        return 'GH₵ ' + num.toLocaleString('en-GH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        try {
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return esc(dateStr);
            return d.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        } catch (e) {
            return esc(dateStr);
        }
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '—';
        try {
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return esc(dateStr);
            return d.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            }) + ' ' + d.toLocaleTimeString('en-GB', {
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch (e) {
            return esc(dateStr);
        }
    }

    // Semantic Status Badge Generator
    function statusBadge(status) {
        if (!status) return '<span class="lv-badge lv-badge-neutral">Unknown</span>';
        const s = String(status).toLowerCase();

        const map = {
            // Order & General Statuses
            ordered: { cls: 'lv-badge-info', label: 'Ordered' },
            sample_collected: { cls: 'lv-badge-teal', label: 'Sample Collected' },
            processing: { cls: 'lv-badge-warning', label: 'Processing in Lab' },
            awaiting_approval: { cls: 'lv-badge-warning', label: 'Awaiting Approval' },
            completed: { cls: 'lv-badge-success', label: 'Completed' },
            cancelled: { cls: 'lv-badge-danger', label: 'Cancelled' },

            // Sample Statuses
            awaiting_collection: { cls: 'lv-badge-warning', label: 'Awaiting Collection' },
            collected: { cls: 'lv-badge-info', label: 'Collected' },
            received: { cls: 'lv-badge-teal', label: 'Received at Lab' },
            accepted: { cls: 'lv-badge-success', label: 'Accepted' },
            rejected: { cls: 'lv-badge-danger', label: 'Rejected' },

            // Lab Item & Result Statuses
            pending_sample: { cls: 'lv-badge-neutral', label: 'Awaiting Sample' },
            awaiting_processing: { cls: 'lv-badge-info', label: 'Ready for Assay' },
            in_progress: { cls: 'lv-badge-teal', label: 'In Progress' },
            draft_saved: { cls: 'lv-badge-neutral', label: 'Draft Saved' },
            submitted: { cls: 'lv-badge-warning', label: 'Awaiting Approval' },
            approved: { cls: 'lv-badge-success', label: 'Approved & Verified' },
            correction_requested: { cls: 'lv-badge-danger', label: 'Correction Requested' },

            // Clinical Result Flags
            normal: { cls: 'lv-badge-success', label: 'Normal' },
            low: { cls: 'lv-badge-warning', label: 'Low (L)' },
            high: { cls: 'lv-badge-warning', label: 'High (H)' },
            abnormal: { cls: 'lv-badge-warning', label: 'Abnormal (A)' },
            critical_low: { cls: 'lv-badge-critical', label: 'CRITICAL LOW (LL)' },
            critical_high: { cls: 'lv-badge-critical', label: 'CRITICAL HIGH (HH)' },
            pending: { cls: 'lv-badge-neutral', label: 'Pending' },

            // Payment Statuses
            paid: { cls: 'lv-badge-success', label: 'Paid in Full' },
            partial: { cls: 'lv-badge-warning', label: 'Part Payment' },
            unpaid: { cls: 'lv-badge-danger', label: 'Unpaid' },

            // Priority
            routine: { cls: 'lv-badge-neutral', label: 'Routine' },
            urgent: { cls: 'lv-badge-warning', label: 'Urgent' },
            stat: { cls: 'lv-badge-critical', label: 'STAT / Emergency' },

            // Report & Inventory
            released: { cls: 'lv-badge-success', label: 'Released' },
            draft: { cls: 'lv-badge-neutral', label: 'Draft' },
            in_stock: { cls: 'lv-badge-success', label: 'In Stock' },
            low_stock: { cls: 'lv-badge-warning', label: 'Low Stock' },
            out_of_stock: { cls: 'lv-badge-danger', label: 'Out of Stock' },
        };

        const cfg = map[s] || {
            cls: 'lv-badge-neutral',
            label: s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
        };

        return `<span class="lv-badge ${cfg.cls}">${esc(cfg.label)}</span>`;
    }

    function paymentMethodLabel(method) {
        const labels = {
            cash: 'Cash (GHS)',
            mtn_momo: 'MTN Mobile Money',
            telecel_cash: 'Telecel Cash',
            at_money: 'AT Money',
            card: 'POS / Visa / Mastercard',
            bank_transfer: 'Bank Transfer',
            nhis: 'NHIS / Corporate Insurance',
        };
        return labels[method] || method || 'Cash';
    }

    // Reusable Page Header Component
    function pageHeader({ breadcrumb, title, description, actionsHtml = '' }) {
        return `
            <div class="lv-page-header">
                <div>
                    <nav aria-label="Breadcrumb" class="lv-breadcrumb">
                        <span>Labverse</span>
                        <i data-lucide="chevron-right" style="width:13px;height:13px;"></i>
                        <span class="fw-medium text-dark">${esc(breadcrumb || title)}</span>
                    </nav>
                    <h1 class="lv-page-title">${esc(title)}</h1>
                    ${description ? `<p class="lv-page-description">${esc(description)}</p>` : ''}
                </div>
                ${actionsHtml ? `<div class="d-flex flex-wrap align-items-center gap-2 lv-no-print">${actionsHtml}</div>` : ''}
            </div>
        `;
    }

    // Reusable KPI Stat Card Component
    function statCard({ label, value, meta, icon, tone = 'primary', onClickAttr = '' }) {
        const toneColors = {
            primary: { bg: '#E8F0F8', fg: '#164E8A' },
            secondary: { bg: '#E6F6F5', fg: '#0F9D9A' },
            success: { bg: '#DCFCE7', fg: '#15803D' },
            warning: { bg: '#FEF3C7', fg: '#B45309' },
            danger: { bg: '#FEE2E2', fg: '#B91C1C' },
            info: { bg: '#DBEAFE', fg: '#1D4ED8' },
        };
        const c = toneColors[tone] || toneColors.primary;
        const interactiveClass = onClickAttr ? 'cursor-pointer' : '';
        const styleExtra = onClickAttr ? 'cursor:pointer;' : '';

        return `
            <div class="lv-stat-card ${interactiveClass}" style="${styleExtra}" ${onClickAttr}>
                <div>
                    <div class="lv-stat-label">${esc(label)}</div>
                    <div class="lv-stat-value">${esc(value)}</div>
                    ${meta ? `<div class="lv-stat-meta">${meta}</div>` : ''}
                </div>
                <div class="lv-stat-icon" style="background-color:${c.bg};color:${c.fg};">
                    <i data-lucide="${esc(icon || 'activity')}" style="width:20px;height:20px;"></i>
                </div>
            </div>
        `;
    }

    // Reusable Empty State
    function emptyState({ icon = 'inbox', title = 'No records found', message = 'Adjust your search or filter criteria.', actionHtml = '' }) {
        return `
            <div class="text-center py-5 px-3">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width:48px;height:48px;background:#F1F5F9;color:#64748B;">
                    <i data-lucide="${esc(icon)}" style="width:22px;height:22px;"></i>
                </div>
                <h6 class="fw-semibold mb-1 text-dark">${esc(title)}</h6>
                <p class="text-secondary small mb-3 mx-auto" style="max-width:420px;">${esc(message)}</p>
                ${actionHtml}
            </div>
        `;
    }

    // Reusable Loading State
    function loadingSpinner(label = 'Loading laboratory data...') {
        return `
            <div class="d-flex flex-column align-items-center justify-content-center py-5 text-secondary" role="status" aria-live="polite">
                <div class="spinner-border spinner-border-sm mb-2" style="color:var(--lv-primary);" aria-hidden="true"></div>
                <span class="small fw-medium">${esc(label)}</span>
            </div>
        `;
    }

    // Reusable Pagination Controls
    function pagination(paginatedObj, callbackFnName) {
        if (!paginatedObj || !paginatedObj.last_page || paginatedObj.last_page <= 1) {
            return paginatedObj && paginatedObj.total
                ? `<div class="px-3 py-2 border-top small text-secondary bg-light rounded-bottom">Showing ${paginatedObj.from || 1}–${paginatedObj.to || paginatedObj.total} of ${paginatedObj.total} records</div>`
                : '';
        }

        const current = paginatedObj.current_page;
        const last = paginatedObj.last_page;
        const prevDisabled = current <= 1 ? 'disabled' : '';
        const nextDisabled = current >= last ? 'disabled' : '';

        return `
            <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-top bg-light rounded-bottom gap-2">
                <div class="small text-secondary">
                    Showing <span class="fw-semibold text-dark">${paginatedObj.from || 0}</span> to <span class="fw-semibold text-dark">${paginatedObj.to || 0}</span> of <span class="fw-semibold text-dark">${paginatedObj.total || 0}</span> records
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="Table Pagination">
                    <button type="button" class="btn btn-light-surface" ${prevDisabled} onclick="${callbackFnName}(${current - 1})">
                        <i data-lucide="chevron-left" style="width:14px;height:14px;"></i> Previous
                    </button>
                    <span class="btn btn-light-surface disabled fw-semibold text-dark">Page ${current} of ${last}</span>
                    <button type="button" class="btn btn-light-surface" ${nextDisabled} onclick="${callbackFnName}(${current + 1})">
                        Next <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
                    </button>
                </div>
            </div>
        `;
    }

    // Toast Notification
    function toast(message, type = 'success') {
        const container = document.getElementById('lv-toast-container');
        if (!container) return;

        const colors = {
            success: { bg: '#15803D', icon: 'check-circle-2' },
            error: { bg: '#B91C1C', icon: 'alert-octagon' },
            warning: { bg: '#B45309', icon: 'alert-triangle' },
            info: { bg: '#164E8A', icon: 'info' },
        };
        const cfg = colors[type] || colors.info;

        const id = 'toast-' + Date.now();
        const el = document.createElement('div');
        el.id = id;
        el.className = 'd-flex align-items-center gap-2 text-white px-3 py-2 rounded shadow-sm mb-2';
        el.style.backgroundColor = cfg.bg;
        el.style.minWidth = '290px';
        el.style.maxWidth = '430px';
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        el.innerHTML = `
            <i data-lucide="${cfg.icon}" style="width:18px;height:18px;flex-shrink:0;"></i>
            <div class="small fw-medium flex-grow-1">${esc(message)}</div>
            <button type="button" class="btn-close btn-close-white btn-sm ms-2" aria-label="Close notification" onclick="this.parentElement.remove()"></button>
        `;

        container.appendChild(el);
        if (window.lucide) window.lucide.createIcons();

        setTimeout(() => {
            if (el && el.parentElement) el.remove();
        }, 4500);
    }

    // Centralized Sanctum API Client
    async function api(endpoint, options = {}) {
        const token = localStorage.getItem(STORAGE_TOKEN_KEY);
        const branchId = localStorage.getItem(STORAGE_BRANCH_KEY) || 'all';

        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        };

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        if (branchId && branchId !== 'all') {
            headers['X-Branch-Id'] = branchId;
        }

        let body = options.body;
        if (body && typeof body === 'object' && !(body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }

        const url = endpoint.startsWith('/api') ? endpoint : `/api${endpoint.startsWith('/') ? '' : '/'}${endpoint}`;

        const response = await fetch(url, {
            method: options.method || 'GET',
            headers,
            body,
        });

        let data = {};
        try {
            data = await response.json();
        } catch (_) {
            data = {};
        }

        if (response.status === 401) {
            localStorage.removeItem(STORAGE_TOKEN_KEY);
            if (window.LabverseApp && typeof window.LabverseApp.handleUnauthenticated === 'function') {
                window.LabverseApp.handleUnauthenticated();
            }
            throw new Error(data.message || 'Your session has expired. Please sign in again.');
        }

        if (!response.ok) {
            let errMsg = data.message || 'An unexpected error occurred while processing the request.';
            if (data.errors) {
                const firstKey = Object.keys(data.errors)[0];
                if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey][0]) {
                    errMsg = data.errors[firstKey][0];
                }
            }
            const error = new Error(errMsg);
            error.status = response.status;
            error.payload = data;
            throw error;
        }

        return data;
    }

    function refreshIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    return {
        STORAGE_TOKEN_KEY,
        STORAGE_BRANCH_KEY,
        esc,
        formatCurrency,
        formatDate,
        formatDateTime,
        statusBadge,
        paymentMethodLabel,
        pageHeader,
        statCard,
        emptyState,
        loadingSpinner,
        pagination,
        toast,
        api,
        refreshIcons,
    };
})();

