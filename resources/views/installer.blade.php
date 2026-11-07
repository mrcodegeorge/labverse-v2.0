<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>LABVERSE v2.0 — Guided Server Setup & Installation Wizard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --lv-primary: #164E8A;
            --lv-secondary: #0F9D9A;
            --lv-accent: #E6B85C;
            --lv-bg: #F5F7FA;
            --lv-surface: #FFFFFF;
            --lv-text: #1E293B;
            --lv-muted: #64748B;
            --lv-border: #E2E8F0;
        }
        body {
            background-color: var(--lv-bg);
            color: var(--lv-text);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
        }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .wiz-header {
            background: var(--lv-primary);
            color: #fff;
            padding: 1.5rem 0;
            border-bottom: 3px solid var(--lv-secondary);
        }
        .wiz-brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.28);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .wiz-card {
            background: var(--lv-surface);
            border: 1px solid var(--lv-border);
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
        }
        .wiz-stepper {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .wiz-step-pill {
            background: #fff;
            border: 1px solid var(--lv-border);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .wiz-step-pill.active {
            border-color: var(--lv-primary);
            background: #EFF6FF;
        }
        .wiz-step-pill.done {
            border-color: #10B981;
            background: #ECFDF5;
        }
        .wiz-step-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #E2E8F0;
            color: #475569;
            font-weight: 700;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .wiz-step-pill.active .wiz-step-num {
            background: var(--lv-primary);
            color: #fff;
        }
        .wiz-step-pill.done .wiz-step-num {
            background: #10B981;
            color: #fff;
        }
        .btn-lv-primary {
            background: var(--lv-primary);
            color: #fff;
            border: none;
            font-weight: 600;
        }
        .btn-lv-primary:hover {
            background: #103B6B;
            color: #fff;
        }
        @media (max-width: 768px) {
            .wiz-stepper { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <header class="wiz-header mb-4">
        <div class="container" style="max-width: 960px;">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="wiz-brand-mark">LV</div>
                    <div>
                        <h1 class="h4 mb-0 fw-bold">LABVERSE v2.0 — Guided Server Installer</h1>
                        <div class="small opacity-75">No-Code Visual Setup Wizard for Medical Diagnostic Laboratories</div>
                    </div>
                </div>
                <div>
                    @if($installed)
                        <a href="/" class="btn btn-light btn-sm fw-semibold">
                            <i data-lucide="external-link" style="width:15px;height:15px;"></i> Open Installed LABVERSE App
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <main class="container pb-5" style="max-width: 960px;">
        @if($installed)
            <div class="alert alert-success border d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <strong><i data-lucide="check-circle-2" style="width:18px;height:18px;"></i> LABVERSE is currently installed and active on this server!</strong>
                    <div class="small text-secondary mt-1">
                        @if($lockMeta)
                            Installed for <strong>{{ $lockMeta['facility_name'] ?? 'Laboratory' }}</strong> ({{ strtoupper($lockMeta['db_connection'] ?? $currentDbConnection) }} • {{ ucfirst($lockMeta['install_mode'] ?? 'demo') }} Mode).
                        @else
                            Database tables and staff accounts are already provisioned.
                        @endif
                        You can launch the application now, or re-run this wizard below to reset/reconfigure the server.
                    </div>
                </div>
                <a href="/" class="btn btn-success btn-sm fw-semibold">Go to Login & Dashboard &rarr;</a>
            </div>
        @endif

        <!-- 4-Step Visual Progress Bar -->
        <div class="wiz-stepper">
            <div class="wiz-step-pill active" id="step-pill-1" onclick="goToStep(1)">
                <div class="wiz-step-num">1</div>
                <div>
                    <div class="small fw-bold">Server Check</div>
                    <div class="text-secondary" style="font-size:0.73rem;">PHP & Permissions</div>
                </div>
            </div>
            <div class="wiz-step-pill" id="step-pill-2" onclick="goToStep(2)">
                <div class="wiz-step-num">2</div>
                <div>
                    <div class="small fw-bold">Database</div>
                    <div class="text-secondary" style="font-size:0.73rem;">1-Click SQLite or MySQL</div>
                </div>
            </div>
            <div class="wiz-step-pill" id="step-pill-3" onclick="goToStep(3)">
                <div class="wiz-step-num">3</div>
                <div>
                    <div class="small fw-bold">Lab & Admin</div>
                    <div class="text-secondary" style="font-size:0.73rem;">Branding & Login</div>
                </div>
            </div>
            <div class="wiz-step-pill" id="step-pill-4" onclick="goToStep(4)">
                <div class="wiz-step-num">4</div>
                <div>
                    <div class="small fw-bold">SMTP, SMS & Finish</div>
                    <div class="text-secondary" style="font-size:0.73rem;">Alerts & 1-Click Install</div>
                </div>
            </div>
        </div>

        <form id="lv-installer-form" onsubmit="event.preventDefault(); executeInstallation();">
            <!-- STEP 1: SERVER REQUIREMENTS -->
            <div class="wiz-card p-4" id="wiz-step-1">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Step 1: Automatic Server Readiness Check</h2>
                        <p class="small text-secondary mb-0">We automatically checked your server's PHP version, required extensions, and folder permissions.</p>
                    </div>
                    @if($allPassed)
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">All Checks Passed</span>
                    @else
                        <span class="badge bg-warning-subtle text-warning border px-3 py-2">Action Needed</span>
                    @endif
                </div>

                <div class="row g-4">
                    <div class="col-md-7">
                        <h6 class="small fw-bold text-uppercase text-secondary mb-2">PHP Extensions & Runtime</h6>
                        <div class="list-group small">
                            @foreach($checks['requirements'] as $req)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>{{ $req['name'] }}</span>
                                    <span class="font-mono fw-semibold {{ $req['passed'] ? 'text-success' : 'text-danger' }}">
                                        {{ $req['current'] }} {!! $req['passed'] ? '&#10003;' : '&#10007;' !!}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-5">
                        <h6 class="small fw-bold text-uppercase text-secondary mb-2">Writable Directory Permissions</h6>
                        <div class="list-group small mb-3">
                            @foreach($checks['permissions'] as $perm)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="font-mono">{{ $perm['path'] }}</span>
                                    <span class="fw-semibold {{ $perm['passed'] ? 'text-success' : 'text-danger' }}">
                                        {{ $perm['passed'] ? 'Writable ✓' : 'Read-Only ✗' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="p-3 rounded bg-light border small text-secondary">
                            <strong>Non-Developer Tip:</strong> Everything with a green checkmark is already configured on your server. Click <strong>Next: Database Setup</strong> below to continue.
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end border-top pt-3 mt-4">
                    <button type="button" class="btn btn-lv-primary px-4" onclick="goToStep(2)">
                        Next: Database Setup &rarr;
                    </button>
                </div>
            </div>

            <!-- STEP 2: DATABASE CONFIGURATION -->
            <div class="wiz-card p-4 d-none" id="wiz-step-2">
                <div class="border-bottom pb-3 mb-3">
                    <h2 class="h5 fw-bold mb-1">Step 2: Choose Your Database</h2>
                    <p class="small text-secondary mb-0">Select <strong>1-Click SQLite</strong> for instant setup with zero configuration, or connect to a <strong>MySQL / MariaDB</strong> database.</p>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="p-3 rounded border d-block cursor-pointer h-100" style="cursor:pointer;" onclick="toggleDbMode('sqlite')">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="db_connection" id="db-sqlite" value="sqlite" checked onchange="toggleDbMode('sqlite')">
                                <span class="fw-bold text-primary">1-Click Embedded SQLite (Recommended)</span>
                            </div>
                            <div class="small text-secondary mt-2">
                                Best for non-developers, clinic servers, and quick deployments. Creates a self-contained database file automatically — <strong>no database username or password required</strong>.
                            </div>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="p-3 rounded border d-block cursor-pointer h-100" style="cursor:pointer;" onclick="toggleDbMode('mysql')">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="db_connection" id="db-mysql" value="mysql" onchange="toggleDbMode('mysql')">
                                <span class="fw-bold">MySQL / MariaDB Server</span>
                            </div>
                            <div class="small text-secondary mt-2">
                                Choose this if you are installing on cPanel/Plesk or a cloud server where you created a MySQL database and username.
                            </div>
                        </label>
                    </div>
                </div>

                <div id="mysql-fields-box" class="p-3 rounded border bg-light mb-3 d-none">
                    <h6 class="fw-bold small text-uppercase text-secondary mb-3">MySQL / MariaDB Connection Details</h6>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">Database Host</label>
                            <input type="text" name="db_host" id="inp-db-host" class="form-control font-mono" value="127.0.0.1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Port</label>
                            <input type="number" name="db_port" id="inp-db-port" class="form-control font-mono" value="3306">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Database Name</label>
                            <input type="text" name="db_database" id="inp-db-name" class="form-control font-mono" value="labverse">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Database Username</label>
                            <input type="text" name="db_username" id="inp-db-user" class="form-control font-mono" value="root">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Database Password</label>
                            <input type="password" name="db_password" id="inp-db-pass" class="form-control font-mono" placeholder="Leave blank if no password">
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3 mb-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="testDatabaseConnection()">
                        <i data-lucide="database" style="width:15px;height:15px;"></i> Test Database Connection
                    </button>
                    <div id="db-test-feedback" class="small"></div>
                </div>

                <div class="d-flex justify-content-between border-top pt-3 mt-4">
                    <button type="button" class="btn btn-light border" onclick="goToStep(1)">&larr; Back</button>
                    <button type="button" class="btn btn-lv-primary px-4" onclick="goToStep(3)">Next: Laboratory & Admin Profile &rarr;</button>
                </div>
            </div>

            <!-- STEP 3: LABORATORY PROFILE & ADMIN ACCOUNT -->
            <div class="wiz-card p-4 d-none" id="wiz-step-3">
                <div class="border-bottom pb-3 mb-3">
                    <h2 class="h5 fw-bold mb-1">Step 3: Laboratory Branding & Administrator Login</h2>
                    <p class="small text-secondary mb-0">Enter your diagnostic center's details (printed on official medical reports) and create your Chief Administrator login.</p>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Official Laboratory / Hospital Name <span class="text-danger">*</span></label>
                        <input type="text" name="facility_name" class="form-control" required value="LABVERSE DIAGNOSTIC & REFERENCE LABORATORIES LTD.">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Accreditation / Registration Number</label>
                        <input type="text" name="facility_accreditation" class="form-control" value="HeFRA Reg: HF-LAB-2026-0814 | ISO 15189:2022">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Headquarters Branch Name <span class="text-danger">*</span></label>
                        <input type="text" name="branch_name" class="form-control" required value="Accra Central Reference Laboratory">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">City / Region <span class="text-danger">*</span></label>
                        <input type="text" name="branch_city" class="form-control" required value="Accra">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Official Laboratory Phone <span class="text-danger">*</span></label>
                        <input type="text" name="branch_phone" class="form-control font-mono" required value="+233 302 784 100">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Physical Street Address <span class="text-danger">*</span></label>
                        <input type="text" name="branch_address" class="form-control" required value="14 Castle Road, West Ridge Medical Enclave, Accra">
                    </div>
                </div>

                <div class="p-3 rounded border bg-light mb-4">
                    <h6 class="fw-bold small text-uppercase text-primary mb-3">Chief Administrator Account</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Administrator First Name <span class="text-danger">*</span></label>
                            <input type="text" name="admin_first_name" class="form-control" required value="Dr. Kwame">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Administrator Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="admin_last_name" class="form-control" required value="Mensah-Bonsu">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Administrator Login Email <span class="text-danger">*</span></label>
                            <input type="email" name="admin_email" id="inp-admin-email" class="form-control font-mono" required value="admin@labverse.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Administrator Password (Min 8 chars) <span class="text-danger">*</span></label>
                            <input type="text" name="admin_password" id="inp-admin-pass" class="form-control font-mono" required minlength="8" value="password123">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-secondary">Initial Database Content Mode</label>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="p-3 rounded border d-block h-100" style="cursor:pointer;">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="install_mode" value="demo" checked>
                                    <span class="fw-bold text-primary">Full Clinical Demo & Catalogue (Recommended for Testing)</span>
                                </div>
                                <div class="small text-secondary mt-1">
                                    Includes 8 Departments, 16 Diagnostic Tests & Reference Ranges, 6 Role Demo Accounts, plus sample patients, orders, results & reports so you can explore every screen immediately.
                                </div>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="p-3 rounded border d-block h-100" style="cursor:pointer;">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="install_mode" value="production">
                                    <span class="fw-bold">Clean Live Production Laboratory</span>
                                </div>
                                <div class="small text-secondary mt-1">
                                    Pre-loads the 8 Diagnostic Categories, 16 Clinical Tests & Reference Ranges, and RBAC Roles, with <strong>zero demo patients or orders</strong> — ready for live patients on Day 1.
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between border-top pt-3 mt-4">
                    <button type="button" class="btn btn-light border" onclick="goToStep(2)">&larr; Back</button>
                    <button type="button" class="btn btn-lv-primary px-4" onclick="goToStep(4)">Next: SMTP, SMS & Finish &rarr;</button>
                </div>
            </div>

            <!-- STEP 4: OPTIONAL SMTP & SMS + 1-CLICK INSTALL -->
            <div class="wiz-card p-4 d-none" id="wiz-step-4">
                <div class="border-bottom pb-3 mb-3">
                    <h2 class="h5 fw-bold mb-1">Step 4: Email (SMTP) & SMS Gateway Setup</h2>
                    <p class="small text-secondary mb-0">Configure how LABVERSE sends patient report alerts and receipts. You can leave these on <strong>Sandbox Mode</strong> now and update them anytime inside <strong>Settings</strong>.</p>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 rounded border h-100">
                            <h6 class="fw-bold small text-uppercase text-primary mb-3"><i data-lucide="mail" style="width:15px;height:15px;"></i> SMTP Email Configuration</h6>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Email Delivery Mode</label>
                                <select name="smtp_driver" class="form-select form-select-sm">
                                    <option value="log">Sandbox Mail Log (Works immediately without SMTP server)</option>
                                    <option value="smtp">Live SMTP Server (Gmail, Mailgun, Office365, cPanel Email)</option>
                                </select>
                            </div>
                            <div class="row g-2">
                                <div class="col-8">
                                    <label class="form-label small">SMTP Host</label>
                                    <input type="text" name="smtp_host" class="form-control form-control-sm font-mono" placeholder="smtp.gmail.com">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small">Port</label>
                                    <input type="number" name="smtp_port" class="form-control form-control-sm font-mono" value="587">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">SMTP Username</label>
                                    <input type="text" name="smtp_username" class="form-control form-control-sm font-mono" placeholder="Optional">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">SMTP Password</label>
                                    <input type="password" name="smtp_password" class="form-control form-control-sm font-mono" placeholder="Optional">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 rounded border h-100">
                            <h6 class="fw-bold small text-uppercase text-primary mb-3"><i data-lucide="message-square" style="width:15px;height:15px;"></i> SMS Gateway Configuration</h6>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">SMS Provider</label>
                                <select name="sms_provider" class="form-select form-select-sm">
                                    <option value="sandbox">Sandbox Simulator (Logs SMS in LABVERSE Outbox)</option>
                                    <option value="arkesel">Arkesel SMS (Ghana / West Africa)</option>
                                    <option value="mnotify">mNotify SMS</option>
                                    <option value="hubtel">Hubtel SMS</option>
                                    <option value="termii">Termii SMS</option>
                                    <option value="twilio">Twilio Programmable SMS</option>
                                </select>
                            </div>
                            <div class="row g-2">
                                <div class="col-5">
                                    <label class="form-label small">Sender ID (11 chars)</label>
                                    <input type="text" name="sms_sender_id" maxlength="11" class="form-control form-control-sm font-mono" value="LABVERSE">
                                </div>
                                <div class="col-7">
                                    <label class="form-label small">SMS API Key / Token</label>
                                    <input type="password" name="sms_api_key" class="form-control form-control-sm font-mono" placeholder="Optional — can add later">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small">Client ID / Account SID (Hubtel or Twilio only)</label>
                                    <input type="text" name="sms_api_secret" class="form-control form-control-sm font-mono" placeholder="Optional">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="install-progress-box" class="d-none mb-3"></div>

                <div class="d-flex justify-content-between align-items-center border-top pt-3">
                    <button type="button" class="btn btn-light border" onclick="goToStep(3)">&larr; Back</button>
                    <button type="submit" id="btn-run-install" class="btn btn-success px-4 py-2 fw-bold">
                        <i data-lucide="rocket" style="width:17px;height:17px;"></i> Install & Launch LABVERSE Now
                    </button>
                </div>
            </div>
        </form>
    </main>

    <script>
        lucide.createIcons();

        function goToStep(step) {
            for (let i = 1; i <= 4; i++) {
                document.getElementById(`wiz-step-${i}`).classList.toggle('d-none', i !== step);
                const pill = document.getElementById(`step-pill-${i}`);
                pill.classList.remove('active', 'done');
                if (i === step) pill.classList.add('active');
                else if (i < step) pill.classList.add('done');
            }
            lucide.createIcons();
        }

        function toggleDbMode(mode) {
            document.getElementById('mysql-fields-box').classList.toggle('d-none', mode !== 'mysql');
            document.getElementById('db-test-feedback').innerHTML = '';
        }

        async function testDatabaseConnection() {
            const fb = document.getElementById('db-test-feedback');
            fb.innerHTML = '<span class="text-secondary">Testing database connection...</span>';
            const form = document.getElementById('lv-installer-form');
            const fd = Object.fromEntries(new FormData(form).entries());

            try {
                const res = await fetch('/install/check-database', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(fd),
                });
                const data = await res.json();
                if (res.ok && data.connected) {
                    fb.innerHTML = `<span class="text-success fw-semibold">&#10003; ${data.message}</span>`;
                } else {
                    fb.innerHTML = `<span class="text-danger fw-semibold">&#10007; ${data.message || 'Connection failed'}</span>`;
                }
            } catch (e) {
                fb.innerHTML = `<span class="text-danger fw-semibold">Error: ${e.message}</span>`;
            }
        }

        async function executeInstallation() {
            const btn = document.getElementById('btn-run-install');
            const box = document.getElementById('install-progress-box');
            const form = document.getElementById('lv-installer-form');
            const payload = Object.fromEntries(new FormData(form).entries());

            btn.disabled = true;
            box.classList.remove('d-none');
            box.innerHTML = `
                <div class="alert alert-info d-flex align-items-center gap-3 mb-0">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <div>
                        <strong>Installing LABVERSE v2.0 Enterprise LIMS...</strong>
                        <div class="small">Provisioning database schema, clinical test catalogue, RBAC permissions, and administrator account. Please wait...</div>
                    </div>
                </div>
            `;

            try {
                const res = await fetch('/install/run', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (res.ok && data.installed) {
                    localStorage.removeItem('labverse_auth_token');
                    box.innerHTML = `
                        <div class="alert alert-success p-4 mb-0">
                            <h5 class="fw-bold mb-2">&#10003; ${data.message}</h5>
                            <p class="small mb-3">Your laboratory management system is ready. Sign in using your Administrator credentials:</p>
                            <div class="p-3 bg-white rounded border font-mono small mb-3">
                                <div><strong>Login URL:</strong> ${window.location.origin}/</div>
                                <div><strong>Admin Email:</strong> ${data.admin_email}</div>
                                <div><strong>Password:</strong> ${payload.admin_password}</div>
                            </div>
                            <a href="/" class="btn btn-success fw-bold px-4">Launch LABVERSE Application &rarr;</a>
                        </div>
                    `;
                } else {
                    btn.disabled = false;
                    box.innerHTML = `<div class="alert alert-danger mb-0"><strong>Installation Failed:</strong> ${data.message || 'Please check your database settings in Step 2.'}</div>`;
                }
            } catch (err) {
                btn.disabled = false;
                box.innerHTML = `<div class="alert alert-danger mb-0"><strong>Network Error:</strong> ${err.message}</div>`;
            }
        }
    </script>
</body>
</html>

