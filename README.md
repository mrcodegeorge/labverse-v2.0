# LABVERSE v2.0 — Enterprise Laboratory Information Management System (LIMS)

**LABVERSE** is a comprehensive, multi-branch **Enterprise Laboratory Information Management System (LIMS)** engineered for medical diagnostic laboratories, hospital pathology departments, and multi-site clinical diagnostic networks.

Built on **Laravel 13**, **Sanctum REST API**, and a responsive **Bootstrap 5 / Inter / Lucide** enterprise healthcare interface, LABVERSE streamlines the entire clinical diagnostic lifecycle—from patient registration and test ordering to phlebotomy specimen tracking, bench result entry with automated panic-value detection, supervisor dual-verification, official report release, cashier billing, and reagent inventory control.

---

## Key Features & Clinical Modules

1. **Operational Command Dashboard**
   - Real-time KPI metrics: Patients Today, Orders Today, Pending Samples, Processing Samples, Pending Results, Awaiting Approval, Released Reports, and Outstanding Payments.
   - Interactive **7-Day Registration & Order Volume** trend charts and **Departmental Workload Distribution** analytics.
   - Critical/Panic result alerts banner, pending laboratory task queue, and real-time audit activity stream.
   - Dynamic date range (`Today`, `7 Days`, `30 Days`, `All Time`) and multi-branch filtering.

2. **Patient Management (EMR & Demographics)**
   - Automated unique patient identifier generation (`LV-PAT-YYYY-XXXX`).
   - Duplicate prevention and instant search by Patient ID, name, phone number, or email.
   - Complete patient profile drawer with historical orders, diagnostic results, and released reports.
   - Seamless *"Register & Proceed to Order"* clinical workflow.

3. **Diagnostic Test Ordering & Billing Calculation**
   - Categorized clinical test catalogue (**Haematology**, **Clinical Chemistry**, **Microbiology**, **Serology**, **Immunology**, **Parasitology**, **Urinalysis**, **Histopathology**).
   - Multi-test selection with automatic sample-type grouping, priority tiers (`Routine`, `Urgent`, `STAT`), discount calculation, and optional immediate payment collection.

4. **Phlebotomy & Sample Lifecycle Tracking**
   - Full chain-of-custody specimen lifecycle: `Awaiting Collection` → `Collected` → `Received` → `Accepted` / `Rejected` → `Processing` → `Completed`.
   - Automated barcode generation (`SMP-YYYYMMDD-XXXX`), printable SVG specimen labels, and barcode scanner lookup support.
   - Mandatory clinical rejection reasons (`Haemolysed specimen`, `Insufficient volume`, `Clotted EDTA sample`, etc.) with automatic audit logging.

5. **Laboratory Bench Workspace & Dynamic Result Entry**
   - Filterable worklist by department, priority (`STAT`/`Urgent`), and processing status with turnaround time (TAT) tracking.
   - Parameter-driven result entry supporting **Numeric**, **Qualitative**, **Text**, and **Select** configurations.
   - Sex-specific and general reference range display with live abnormality evaluation (`NORMAL`, `LOW`, `HIGH`, `CRITICAL_LOW`, `CRITICAL_HIGH`, `ABNORMAL`).
   - Draft saving, repeat-test flagging, and submission to the verification queue—never auto-releasing unverified results.

6. **Supervisor Result Verification & Approval Queue**
   - Dedicated clinical verification workspace displaying patient demographics, technician identity, timestamps, parameter values, reference intervals, and delta/previous history.
   - Strict **Self-Approval Prevention** (`submitter !== approver` enforced for non-platform-administrators) with override option for Platform Administrators.
   - Actions to **Approve**, **Return for Correction**, or **Reject** with mandatory clinical review notes.

7. **Official Diagnostic Reports & Print-Ready Output**
   - Consolidates verified test results into official diagnostic reports (`RPT-YYYYMMDD-XXXX`) with SHA-256 verification hash and QR/barcode metadata.
   - Supports **Release Report** workflow and clean **A4 Print / PDF** clinical formatting with laboratory header, patient details, flagged abnormalities, and digital signatures.

8. **Cashier, Billing & Receipts**
   - Tracks `Unpaid`, `Partial`, `Paid`, and `Refunded` orders with outstanding balance calculation.
   - Supports **Cash**, **Card / POS**, **Bank Transfer**, **Mobile Money**, and **Insurance / HMO** payments with printable receipts (`RCP-YYYYMMDD-XXXX`).

9. **Reagent & Consumables Inventory**
   - Tracks stock levels, minimum reorder thresholds, batch/lot numbers, expiry dates, and supplier details.
   - Automated status classification (`In Stock`, `Low Stock`, `Out of Stock`, `Expired`) and stock ledger movements (`Stock In`, `Consumption`, `Adjustment`, `Wastage`).

10. **Multi-Branch & Custom RBAC Administration**
    - Multi-branch isolation for branch-bound staff while granting Platform Administrators cross-branch visibility and switching.
    - Custom Role-Based Access Control (`roles`, `permissions`, `role_user`, `permission_role`) with granular middleware enforcement (`CheckPermission`) and immutable audit logging (`AuditService`).

---

## Technology Stack

- **Backend**: PHP 8.2+, Laravel 13, Laravel Sanctum (Token + Session SPA Auth)
- **Database**: MySQL / MariaDB / SQLite (zero-configuration local development via SQLite out of the box)
- **Authorization**: Custom Role-Based Access Control (RBAC) with 9 clinical roles and 26 granular permissions
- **Frontend**: Responsive SPA-over-Blade architecture (`resources/views/labverse.blade.php`), Bootstrap 5.3, Inter Typography, Lucide Icons, Chart.js 4

---

## Colour Palette & Design System

| Token | Hex | Purpose |
| :--- | :--- | :--- |
| **Primary** | `#164E8A` | Deep clinical navy for primary actions, headers, and active states |
| **Secondary** | `#0F9D9A` | Diagnostic teal for secondary highlights and clinical accents |
| **Accent** | `#E6B85C` | Warm gold for priority indicators and executive highlights |
| **Background** | `#F5F7FA` | Clean slate workspace canvas |
| **Surface** | `#FFFFFF` | Elevated cards, tables, and drawers |
| **Primary Text** | `#1E293B` | High-contrast clinical data readability |
| **Secondary Text** | `#64748B` | Supporting metadata and labels |
| **Border** | `#E2E8F0` | Crisp structural dividers |

---

## Quick Start & Installation

### Prerequisites
- **PHP** >= 8.2 (with `pdo_sqlite` or `pdo_mysql`, `mbstring`, `openssl`, `ctype`, `json`)
- **Composer** >= 2.x

### 1. Clone the Repository
```bash
git clone https://github.com/mrcodegeorge/labverse-v2.0.git
cd labverse-v2.0
```

### 2. Install PHP Dependencies
```bash
composer install
```

### 3. Configure Environment
```bash
cp .env.example .env
php artisan key:generate
```
*(By default, `.env.example` uses SQLite. If using SQLite, create `database/database.sqlite` or let `php artisan migrate` create it; for MySQL, update `DB_CONNECTION=mysql` and your database credentials in `.env`.)*

### 4. Run Database Migrations & Clinical Seeder
```bash
php artisan migrate:fresh --seed
```
This provisions all 20 tables, 4 diagnostic branches, 9 clinical roles, 26 permissions, 9 staff accounts, 8 test categories, 16 diagnostic tests with 36 clinical parameters & reference ranges, sample patients, clinical orders across all lifecycle stages, inventory items, and audit trails.

### 5. Start the Application Server
```bash
php artisan serve --host=127.0.0.1 --port=8000
```
Open **http://127.0.0.1:8000** in your browser.

---

## Pre-Seeded Demo Accounts (Password: `password`)

The login screen includes a **1-Click Role Switcher** for testing different clinical personas and RBAC permissions:

| Role | Email | Password | Primary Responsibilities |
| :--- | :--- | :--- | :--- |
| **Platform Administrator** | `admin@labverse.health` | `password` | Full cross-branch access, system settings, RBAC, branch & staff management |
| **Facility Administrator** | `facility@labverse.health` | `password` | Branch administration, staff oversight, inventory, and operational reporting |
| **Laboratory Manager** | `manager@labverse.health` | `password` | Lab operations, TAT monitoring, result verification, report release, catalogue |
| **Laboratory Supervisor** | `supervisor@labverse.health` | `password` | Result approval/rejection, clinical review, report sign-off |
| **Medical Lab Scientist** | `scientist@labverse.health` | `password` | Bench testing, result entry, sample processing, repeat testing |
| **Laboratory Technician** | `technician@labverse.health` | `password` | Sample processing, bench assistance, draft result entry |
| **Phlebotomist** | `phlebotomist@labverse.health` | `password` | Venipuncture, barcode label printing, sample reception & rejection |
| **Receptionist** | `reception@labverse.health` | `password` | Patient registration, test order creation, report printing |
| **Cashier** | `cashier@labverse.health` | `password` | Invoice collection, payment recording, refunds, receipt printing |

---

## Project Architecture

```text
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── AdminController.php          # Users, Roles, Permissions, Branches, Settings, Audit Logs
│   │   │   │   ├── AuthController.php           # Login, Logout, Profile, Branch Switch, Notifications
│   │   │   │   ├── DashboardController.php      # Real-time KPIs, Workload Charts, Trends, Alerts
│   │   │   │   ├── InventoryController.php      # Reagent Stock, Reorder Alerts, Stock Movements
│   │   │   │   ├── LabOrderController.php       # Diagnostic Order Creation, Discounts, Line Items
│   │   │   │   ├── LabWorkspaceController.php   # Bench Worklist, Result Entry, Supervisor Approval
│   │   │   │   ├── PatientController.php        # Patient Registration, Search, Clinical History
│   │   │   │   ├── PaymentController.php        # Billing Queue, Payment Recording, Receipts
│   │   │   │   ├── ReportController.php         # Official Report Generation, Release, Print View
│   │   │   │   ├── SampleController.php         # Specimen Collection, Barcode Lookup, Acceptance/Rejection
│   │   │   │   └── TestCatalogueController.php  # Categories, Diagnostic Tests, Reference Parameters
│   │   │   └── Concerns/
│   │   │       └── EnforcesBranchAccess.php     # Multi-branch scoping & cross-branch guard trait
│   │   └── Middleware/
│   │       └── CheckPermission.php              # Custom RBAC permission middleware
│   ├── Models/                                  # 19 Eloquent models (User, Role, Permission, LabOrder, etc.)
│   └── Services/
│       └── AuditService.php                     # Clinical action & security audit trail logger
├── database/
│   ├── migrations/
│   │   └── 2026_11_07_000001_create_labverse_schema.php
│   └── seeders/
│       └── DatabaseSeeder.php                   # Comprehensive clinical dataset seeder
├── public/
│   ├── css/
│   │   └── labverse.css                         # Enterprise design system stylesheet
│   └── js/
│       ├── labverse-ui.js                       # Modals, Drawers, Badges, Barcodes, Print Engine
│       ├── labverse-app.js                      # SPA Router, Auth State, Dashboard & Charts
│       ├── labverse-core-ops.js                 # Patients, Orders, Samples & Barcode Labels
│       ├── labverse-lab-ops.js                  # Bench Workspace, Result Entry, Approvals, Reports
│       └── labverse-admin-ops.js                # Billing, Inventory, Branches, Staff, Catalogue, Settings
├── resources/views/
│   └── labverse.blade.php                       # Unified Enterprise Application Shell
└── routes/
    ├── api.php                                  # Sanctum REST API endpoints with RBAC middleware
    └── web.php                                  # Application entry routes
```

---

## Automated Testing

Run the feature test suite via Artisan:

```bash
php artisan test
```

---

## License

Proprietary / Enterprise Healthcare Software — **LABVERSE Diagnostics Platform**.
