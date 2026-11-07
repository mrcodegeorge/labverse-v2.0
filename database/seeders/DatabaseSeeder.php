<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CommunicationLog;
use App\Models\InventoryItem;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabReport;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sample;
use App\Models\StockMovement;
use App\Models\SystemNotification;
use App\Models\SystemSetting;
use App\Models\TestCategory;
use App\Models\TestParameter;
use App\Models\TestResult;
use App\Models\User;
use App\Services\CommunicationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Facility System Settings
        $defaultSettings = [
            'facility_name' => 'LABVERSE DIAGNOSTIC & REFERENCE LABORATORIES LTD.',
            'facility_tagline' => 'ISO 15189 Accredited Medical Diagnostic & Pathology Network',
            'facility_accreditation' => 'HeFRA Reg: HF-LAB-2026-0814 | ISO 15189:2022',
            'facility_phone' => '+233 (0) 302 784 900 / +233 (0) 244 310 555',
            'facility_email' => 'clinical.desk@labverse-gh.com',
            'facility_website' => 'www.labverse-diagnostics.com',
            'currency_code' => 'GHS',
            'currency_symbol' => 'GH₵',
            'critical_value_policy' => 'Immediate verbal & electronic alert to requesting clinician within 30 minutes of supervisor verification.',
            'independent_verification_required' => 'true',
            'report_footer_disclaimer' => 'This diagnostic laboratory report is confidential and intended for clinical correlation by a licensed medical practitioner. Results relate only to the specimen(s) tested.',
        ];

        foreach ($defaultSettings as $k => $v) {
            SystemSetting::updateOrCreate(['key' => $k], ['value' => $v, 'group' => 'facility']);
        }

        // 2. Multi-Branch Setup
        $accraBranch = Branch::create([
            'code' => 'LV-ACC-01',
            'name' => 'Accra Central Reference Laboratory',
            'region' => 'Greater Accra',
            'city' => 'Accra (Ridge)',
            'address' => '14 Castle Road, West Ridge Medical Enclave, Accra',
            'phone' => '+233 302 784 901',
            'email' => 'accra.central@labverse-gh.com',
            'manager_name' => 'Dr. Kwame Mensah-Bonsu',
            'is_main' => true,
            'is_active' => true,
        ]);

        $kumasiBranch = Branch::create([
            'code' => 'LV-KMS-02',
            'name' => 'Kumasi Ashanti Diagnostic Centre',
            'region' => 'Ashanti Region',
            'city' => 'Kumasi (Adum / Bantama)',
            'address' => 'Plot 28 Bantama High Street, Opposite Komfo Anokye, Kumasi',
            'phone' => '+233 322 041 820',
            'email' => 'kumasi@labverse-gh.com',
            'manager_name' => 'Dr. Abena Osei-Tutu',
            'is_main' => false,
            'is_active' => true,
        ]);

        $takoradiBranch = Branch::create([
            'code' => 'LV-TKD-03',
            'name' => 'Takoradi Western Harbour Clinic Lab',
            'region' => 'Western Region',
            'city' => 'Takoradi',
            'address' => '8 Liberation Road, Harbour View, Takoradi',
            'phone' => '+233 312 029 415',
            'email' => 'takoradi@labverse-gh.com',
            'manager_name' => 'Mr. Kofi Yankson',
            'is_main' => false,
            'is_active' => true,
        ]);

        // 3. Custom RBAC Permissions (Categorized)
        $permissionsList = [
            ['name' => 'View Operational Dashboard', 'slug' => 'view_dashboard', 'category' => 'Dashboard & Analytics', 'description' => 'Access real-time operational KPIs, charts, and branch activity feeds.'],
            ['name' => 'View Patient Records', 'slug' => 'view_patients', 'category' => 'Patient Management', 'description' => 'Search and view patient demographic profiles and diagnostic history.'],
            ['name' => 'Register & Edit Patients', 'slug' => 'manage_patients', 'category' => 'Patient Management', 'description' => 'Register new patients and update demographic or emergency contact details.'],
            ['name' => 'View Laboratory Orders', 'slug' => 'view_orders', 'category' => 'Test Ordering', 'description' => 'View laboratory test requisitions and order progress.'],
            ['name' => 'Place Laboratory Orders', 'slug' => 'place_order', 'category' => 'Test Ordering', 'description' => 'Create new test orders, apply authorized discounts, and assign priority.'],
            ['name' => 'Collect Patient Samples', 'slug' => 'collect_sample', 'category' => 'Sample Management', 'description' => 'Generate barcodes, record phlebotomy/specimen collection, and print labels.'],
            ['name' => 'Receive & Accept/Reject Samples', 'slug' => 'receive_sample', 'category' => 'Sample Management', 'description' => 'Receive specimens into the lab, verify integrity, and accept or reject samples.'],
            ['name' => 'Manage Sample Storage & Tracking', 'slug' => 'manage_samples', 'category' => 'Sample Management', 'description' => 'Full lifecycle control over specimen locations, recollections, and logs.'],
            ['name' => 'Access Laboratory Work Queue', 'slug' => 'view_lab_workspace', 'category' => 'Laboratory Operations', 'description' => 'View assigned, urgent, and pending diagnostic tests in the laboratory bench queue.'],
            ['name' => 'Enter & Submit Test Results', 'slug' => 'enter_results', 'category' => 'Laboratory Operations', 'description' => 'Record numeric/qualitative test findings, save drafts, and submit for supervisor review.'],
            ['name' => 'Approve & Verify Test Results', 'slug' => 'approve_result', 'category' => 'Quality & Approvals', 'description' => 'Authorize submitted test results, request corrections, or reject invalid runs.'],
            ['name' => 'View Medical Reports', 'slug' => 'view_reports', 'category' => 'Clinical Reports', 'description' => 'Preview, print, and download released laboratory diagnostic reports.'],
            ['name' => 'Generate & Release Reports', 'slug' => 'release_report', 'category' => 'Clinical Reports', 'description' => 'Sign off and officially release verified diagnostic reports.'],
            ['name' => 'View Financial Transactions', 'slug' => 'view_payments', 'category' => 'Finance & Billing', 'description' => 'View invoices, payment receipts, and outstanding patient balances.'],
            ['name' => 'Record Payments & Issue Receipts', 'slug' => 'manage_payments', 'category' => 'Finance & Billing', 'description' => 'Collect Cash, Mobile Money, Card, or NHIS payments and generate official receipts.'],
            ['name' => 'View Inventory & Reagents', 'slug' => 'view_inventory', 'category' => 'Inventory & Stock', 'description' => 'Monitor reagent stock levels, lot numbers, and expiry dates.'],
            ['name' => 'Manage Inventory & Stock Adjustments', 'slug' => 'manage_inventory', 'category' => 'Inventory & Stock', 'description' => 'Register reagents/consumables and record stock-in, consumption, or write-offs.'],
            ['name' => 'Manage Multi-Branch Operations', 'slug' => 'manage_branches', 'category' => 'Administration', 'description' => 'View cross-branch performance, switch branches, and configure branch facilities.'],
            ['name' => 'Manage Staff & User Accounts', 'slug' => 'manage_users', 'category' => 'Administration', 'description' => 'Create staff accounts, assign roles/branches, and activate or deactivate users.'],
            ['name' => 'Manage Roles & Permissions', 'slug' => 'manage_roles', 'category' => 'Administration', 'description' => 'Configure custom RBAC roles and permission matrices.'],
            ['name' => 'Manage Diagnostic Test Catalogue', 'slug' => 'manage_catalogue', 'category' => 'Administration', 'description' => 'Configure test panels, parameters, reference ranges, and tariff prices.'],
            ['name' => 'Manage System & Clinical Settings', 'slug' => 'manage_settings', 'category' => 'Administration', 'description' => 'Update laboratory accreditation details, report headers, and clinical policies.'],
        ];

        $permMap = [];
        foreach ($permissionsList as $p) {
            $perm = Permission::create($p);
            $permMap[$p['slug']] = $perm->id;
        }

        // 4. Custom Roles
        $rolesConfig = [
            [
                'name' => 'Platform Administrator',
                'slug' => 'platform_admin',
                'description' => 'Full organization-wide administrative and clinical oversight across all branches.',
                'is_system' => true,
                'perms' => array_keys($permMap),
            ],
            [
                'name' => 'Facility / Laboratory Manager',
                'slug' => 'lab_manager',
                'description' => 'Oversees branch operations, staff, approvals, inventory, and financial summaries.',
                'is_system' => true,
                'perms' => [
                    'view_dashboard', 'view_patients', 'manage_patients', 'view_orders', 'place_order',
                    'collect_sample', 'receive_sample', 'manage_samples', 'view_lab_workspace',
                    'enter_results', 'approve_result', 'view_reports', 'release_report',
                    'view_payments', 'manage_payments', 'view_inventory', 'manage_inventory',
                    'manage_users', 'manage_catalogue',
                ],
            ],
            [
                'name' => 'Laboratory Supervisor / Pathologist',
                'slug' => 'lab_supervisor',
                'description' => 'Verifies and approves laboratory results, reviews critical values, and releases reports.',
                'is_system' => true,
                'perms' => [
                    'view_dashboard', 'view_patients', 'view_orders', 'receive_sample', 'manage_samples',
                    'view_lab_workspace', 'enter_results', 'approve_result', 'view_reports', 'release_report',
                    'view_inventory',
                ],
            ],
            [
                'name' => 'Medical Laboratory Scientist',
                'slug' => 'lab_scientist',
                'description' => 'Receives specimens, performs diagnostic assays, and enters/submits test results.',
                'is_system' => true,
                'perms' => [
                    'view_dashboard', 'view_patients', 'view_orders', 'collect_sample', 'receive_sample',
                    'manage_samples', 'view_lab_workspace', 'enter_results', 'view_reports', 'view_inventory',
                ],
            ],
            [
                'name' => 'Laboratory Technician / Phlebotomist',
                'slug' => 'lab_technician',
                'description' => 'Collects patient specimens, prints barcodes, and logs sample reception.',
                'is_system' => true,
                'perms' => [
                    'view_dashboard', 'view_patients', 'view_orders', 'collect_sample', 'receive_sample',
                    'view_lab_workspace', 'enter_results',
                ],
            ],
            [
                'name' => 'Front Desk Receptionist',
                'slug' => 'receptionist',
                'description' => 'Registers patients, places test orders, and prints released reports for patients.',
                'is_system' => true,
                'perms' => [
                    'view_dashboard', 'view_patients', 'manage_patients', 'view_orders', 'place_order',
                    'view_reports', 'view_payments',
                ],
            ],
            [
                'name' => 'Cashier / Billing Officer',
                'slug' => 'cashier',
                'description' => 'Processes patient payments, Mobile Money transactions, invoices, and receipts.',
                'is_system' => true,
                'perms' => [
                    'view_dashboard', 'view_patients', 'view_orders', 'view_payments', 'manage_payments',
                ],
            ],
        ];

        $roleMap = [];
        foreach ($rolesConfig as $rc) {
            $role = Role::create([
                'name' => $rc['name'],
                'slug' => $rc['slug'],
                'description' => $rc['description'],
                'is_system' => $rc['is_system'],
            ]);
            $role->permissions()->sync(array_map(fn ($s) => $permMap[$s], $rc['perms']));
            $roleMap[$rc['slug']] = $role;
        }

        // 5. Staff Accounts across Roles & Branches (Password for all demo accounts: password123)
        $defaultPassword = Hash::make('password123');

        $adminUser = User::create([
            'staff_id' => 'STF-0001',
            'name' => 'Dr. Kwame Mensah-Bonsu',
            'first_name' => 'Kwame',
            'last_name' => 'Mensah-Bonsu',
            'email' => 'admin@labverse.com',
            'phone' => '+233 244 100 001',
            'job_title' => 'Chief Medical Director & Platform Admin',
            'department' => 'Executive & Clinical Governance',
            'license_number' => 'MDC-GH-04912',
            'branch_id' => $accraBranch->id,
            'is_platform_admin' => true,
            'is_active' => true,
            'last_login_at' => now()->subMinutes(10),
            'password' => $defaultPassword,
        ]);
        $adminUser->roles()->attach($roleMap['platform_admin']->id);

        $supervisorUser = User::create([
            'staff_id' => 'STF-0002',
            'name' => 'Dr. Efua Djan-Baffour',
            'first_name' => 'Efua',
            'last_name' => 'Djan-Baffour',
            'email' => 'supervisor@labverse.com',
            'phone' => '+233 244 100 002',
            'job_title' => 'Consultant Chemical Pathologist & Lab Supervisor',
            'department' => 'Pathology & Quality Assurance',
            'license_number' => 'AHPC-MLS-01984',
            'branch_id' => $accraBranch->id,
            'is_platform_admin' => false,
            'is_active' => true,
            'last_login_at' => now()->subMinutes(25),
            'password' => $defaultPassword,
        ]);
        $supervisorUser->roles()->attach($roleMap['lab_supervisor']->id);

        $scientistUser = User::create([
            'staff_id' => 'STF-0003',
            'name' => 'Nana Yaw Asante',
            'first_name' => 'Nana Yaw',
            'last_name' => 'Asante',
            'email' => 'scientist@labverse.com',
            'phone' => '+233 244 100 003',
            'job_title' => 'Senior Medical Laboratory Scientist',
            'department' => 'Haematology & Clinical Chemistry',
            'license_number' => 'AHPC-MLS-05412',
            'branch_id' => $accraBranch->id,
            'is_platform_admin' => false,
            'is_active' => true,
            'last_login_at' => now()->subMinutes(18),
            'password' => $defaultPassword,
        ]);
        $scientistUser->roles()->attach($roleMap['lab_scientist']->id);

        $receptionistUser = User::create([
            'staff_id' => 'STF-0004',
            'name' => 'Akosua Serwaa Boateng',
            'first_name' => 'Akosua Serwaa',
            'last_name' => 'Boateng',
            'email' => 'reception@labverse.com',
            'phone' => '+233 244 100 004',
            'job_title' => 'Senior Patient Relations & Front Desk Officer',
            'department' => 'Patient Services & Reception',
            'license_number' => null,
            'branch_id' => $accraBranch->id,
            'is_platform_admin' => false,
            'is_active' => true,
            'last_login_at' => now()->subMinutes(40),
            'password' => $defaultPassword,
        ]);
        $receptionistUser->roles()->attach($roleMap['receptionist']->id);

        $cashierUser = User::create([
            'staff_id' => 'STF-0005',
            'name' => 'Emmanuel Tetteh Quarshie',
            'first_name' => 'Emmanuel',
            'last_name' => 'Quarshie',
            'email' => 'cashier@labverse.com',
            'phone' => '+233 244 100 005',
            'job_title' => 'Senior Billing & Accounts Officer',
            'department' => 'Finance & Revenue',
            'license_number' => null,
            'branch_id' => $accraBranch->id,
            'is_platform_admin' => false,
            'is_active' => true,
            'last_login_at' => now()->subHour(),
            'password' => $defaultPassword,
        ]);
        $cashierUser->roles()->attach($roleMap['cashier']->id);

        $kumasiManager = User::create([
            'staff_id' => 'STF-0006',
            'name' => 'Dr. Abena Osei-Tutu',
            'first_name' => 'Abena',
            'last_name' => 'Osei-Tutu',
            'email' => 'kumasi.manager@labverse.com',
            'phone' => '+233 322 041 829',
            'job_title' => 'Branch Laboratory Manager (Kumasi)',
            'department' => 'Branch Operations',
            'license_number' => 'AHPC-MLS-02819',
            'branch_id' => $kumasiBranch->id,
            'is_platform_admin' => false,
            'is_active' => true,
            'last_login_at' => now()->subHours(2),
            'password' => $defaultPassword,
        ]);
        $kumasiManager->roles()->attach($roleMap['lab_manager']->id);

        // 6. Test Categories & Comprehensive Diagnostic Test Catalogue
        $categoriesData = [
            ['code' => 'HAEM', 'name' => 'Haematology', 'department' => 'Haematology & Coagulation', 'sort_order' => 1, 'description' => 'Complete blood counts, ESR, reticulocytes, sickle cell screening, and coagulation profiles.'],
            ['code' => 'CHEM', 'name' => 'Clinical Chemistry', 'department' => 'Chemical Pathology', 'sort_order' => 2, 'description' => 'Renal function, liver function, lipid profile, blood glucose, HbA1c, and electrolytes.'],
            ['code' => 'MICRO', 'name' => 'Microbiology', 'department' => 'Medical Microbiology', 'sort_order' => 3, 'description' => 'Bacteriology culture & sensitivity, Gram stain, AFB microscopy, and blood cultures.'],
            ['code' => 'SERO', 'name' => 'Serology', 'department' => 'Serology & Infectious Diseases', 'sort_order' => 4, 'description' => 'Hepatitis B/C screening, Widal test, VDRL/Syphilis, HIV 1&2, and rapid antigen assays.'],
            ['code' => 'IMMU', 'name' => 'Immunology', 'department' => 'Immunology & Endocrinology', 'sort_order' => 5, 'description' => 'Thyroid function panel (TSH, FT3, FT4), PSA, fertility hormones, and tumor markers.'],
            ['code' => 'PARA', 'name' => 'Parasitology', 'department' => 'Medical Parasitology', 'sort_order' => 6, 'description' => 'Blood film for malaria parasites (BF for MP), stool R/E for ova and cysts.'],
            ['code' => 'URIN', 'name' => 'Urinalysis', 'department' => 'Clinical Microscopy', 'sort_order' => 7, 'description' => 'Urine routine examination (dipstick biochemistry and sediment microscopy).'],
            ['code' => 'HISTO', 'name' => 'Histopathology', 'department' => 'Anatomic Pathology & Cytology', 'sort_order' => 8, 'description' => 'Tissue biopsy histopathology, Pap smear cytology, and FNAC evaluation.'],
        ];

        $catMap = [];
        foreach ($categoriesData as $cd) {
            $catMap[$cd['code']] = TestCategory::create($cd);
        }

        // Create Realistic Tests with Clinical Parameters & Reference Ranges
        $catalogueTests = [
            [
                'code' => 'FBC-01',
                'name' => 'Full Blood Count (FBC / 5-Part Differential)',
                'category_id' => $catMap['HAEM']->id,
                'sample_type' => 'EDTA Whole Blood',
                'container_type' => 'Purple Top (K2-EDTA 4mL)',
                'sample_volume' => '3 mL',
                'price' => 85.00,
                'nhis_tariff' => 45.00,
                'turnaround_hours' => 2,
                'methodology' => 'Automated Flow Cytometry (Sysmex XN-1000)',
                'requires_fasting' => false,
                'parameters' => [
                    ['code' => 'HB', 'name' => 'Haemoglobin (Hb)', 'input_type' => 'numeric', 'unit' => 'g/dL', 'ref_min_male' => 13.0, 'ref_max_male' => 17.5, 'ref_min_female' => 11.5, 'ref_max_female' => 15.5, 'critical_low' => 7.0, 'critical_high' => 20.0],
                    ['code' => 'TWBC', 'name' => 'Total White Blood Cell Count (WBC)', 'input_type' => 'numeric', 'unit' => 'x10^9/L', 'ref_min_male' => 4.0, 'ref_max_male' => 11.0, 'ref_min_female' => 4.0, 'ref_max_female' => 11.0, 'critical_low' => 2.0, 'critical_high' => 30.0],
                    ['code' => 'PLT', 'name' => 'Platelet Count', 'input_type' => 'numeric', 'unit' => 'x10^9/L', 'ref_min_male' => 150, 'ref_max_male' => 450, 'ref_min_female' => 150, 'ref_max_female' => 450, 'critical_low' => 50, 'critical_high' => 1000],
                    ['code' => 'HCT', 'name' => 'Haematocrit (PCV)', 'input_type' => 'numeric', 'unit' => '%', 'ref_min_male' => 40.0, 'ref_max_male' => 52.0, 'ref_min_female' => 36.0, 'ref_max_female' => 46.0, 'critical_low' => 20.0, 'critical_high' => 60.0],
                    ['code' => 'MCV', 'name' => 'Mean Corpuscular Volume (MCV)', 'input_type' => 'numeric', 'unit' => 'fL', 'ref_min_male' => 80.0, 'ref_max_male' => 100.0, 'ref_min_female' => 80.0, 'ref_max_female' => 100.0, 'critical_low' => null, 'critical_high' => null],
                    ['code' => 'NEUT', 'name' => 'Neutrophils (%)', 'input_type' => 'numeric', 'unit' => '%', 'ref_min_male' => 40.0, 'ref_max_male' => 75.0, 'ref_min_female' => 40.0, 'ref_max_female' => 75.0, 'critical_low' => null, 'critical_high' => null],
                    ['code' => 'LYMPH', 'name' => 'Lymphocytes (%)', 'input_type' => 'numeric', 'unit' => '%', 'ref_min_male' => 20.0, 'ref_max_male' => 45.0, 'ref_min_female' => 20.0, 'ref_max_female' => 45.0, 'critical_low' => null, 'critical_high' => null],
                ],
            ],
            [
                'code' => 'ESR-02',
                'name' => 'Erythrocyte Sedimentation Rate (ESR)',
                'category_id' => $catMap['HAEM']->id,
                'sample_type' => 'EDTA Whole Blood',
                'container_type' => 'Purple Top (EDTA)',
                'sample_volume' => '2 mL',
                'price' => 45.00,
                'nhis_tariff' => 25.00,
                'turnaround_hours' => 2,
                'methodology' => 'Modified Westergren Method',
                'requires_fasting' => false,
                'parameters' => [
                    ['code' => 'ESR', 'name' => 'ESR (1 Hour)', 'input_type' => 'numeric', 'unit' => 'mm/hr', 'ref_min_male' => 0, 'ref_max_male' => 15, 'ref_min_female' => 0, 'ref_max_female' => 20, 'critical_low' => null, 'critical_high' => 100],
                ],
            ],
            [
                'code' => 'RFT-01',
                'name' => 'Renal Function Test (BUE & Creatinine)',
                'category_id' => $catMap['CHEM']->id,
                'sample_type' => 'Serum',
                'container_type' => 'Gold Top (SST Gel 5mL)',
                'sample_volume' => '4 mL',
                'price' => 160.00,
                'nhis_tariff' => 95.00,
                'turnaround_hours' => 4,
                'methodology' => 'Spectrophotometry & ISE (Cobas c311)',
                'requires_fasting' => false,
                'parameters' => [
                    ['code' => 'NA', 'name' => 'Sodium (Na+)', 'input_type' => 'numeric', 'unit' => 'mmol/L', 'ref_min_male' => 135, 'ref_max_male' => 145, 'ref_min_female' => 135, 'ref_max_female' => 145, 'critical_low' => 120, 'critical_high' => 160],
                    ['code' => 'K', 'name' => 'Potassium (K+)', 'input_type' => 'numeric', 'unit' => 'mmol/L', 'ref_min_male' => 3.5, 'ref_max_male' => 5.1, 'ref_min_female' => 3.5, 'ref_max_female' => 5.1, 'critical_low' => 2.8, 'critical_high' => 6.2],
                    ['code' => 'CL', 'name' => 'Chloride (Cl-)', 'input_type' => 'numeric', 'unit' => 'mmol/L', 'ref_min_male' => 98, 'ref_max_male' => 107, 'ref_min_female' => 98, 'ref_max_female' => 107, 'critical_low' => 80, 'critical_high' => 120],
                    ['code' => 'UREA', 'name' => 'Blood Urea', 'input_type' => 'numeric', 'unit' => 'mmol/L', 'ref_min_male' => 2.5, 'ref_max_male' => 7.8, 'ref_min_female' => 2.5, 'ref_max_female' => 7.8, 'critical_low' => null, 'critical_high' => 25.0],
                    ['code' => 'CREAT', 'name' => 'Serum Creatinine', 'input_type' => 'numeric', 'unit' => 'µmol/L', 'ref_min_male' => 62, 'ref_max_male' => 106, 'ref_min_female' => 44, 'ref_max_female' => 80, 'critical_low' => null, 'critical_high' => 500],
                    ['code' => 'EGFR', 'name' => 'Estimated GFR (CKD-EPI)', 'input_type' => 'numeric', 'unit' => 'mL/min/1.73m²', 'ref_min_male' => 90, 'ref_max_male' => 140, 'ref_min_female' => 90, 'ref_max_female' => 140, 'critical_low' => 15, 'critical_high' => null],
                ],
            ],
            [
                'code' => 'LFT-01',
                'name' => 'Liver Function Test (Comprehensive LFT)',
                'category_id' => $catMap['CHEM']->id,
                'sample_type' => 'Serum',
                'container_type' => 'Gold Top (SST Gel 5mL)',
                'sample_volume' => '4 mL',
                'price' => 175.00,
                'nhis_tariff' => 100.00,
                'turnaround_hours' => 4,
                'methodology' => 'IFCC Kinetic Spectrophotometry (Cobas c311)',
                'requires_fasting' => false,
                'parameters' => [
                    ['code' => 'TBIL', 'name' => 'Total Bilirubin', 'input_type' => 'numeric', 'unit' => 'µmol/L', 'ref_min_male' => 3.0, 'ref_max_male' => 21.0, 'ref_min_female' => 3.0, 'ref_max_female' => 21.0, 'critical_low' => null, 'critical_high' => 250.0],
                    ['code' => 'DBIL', 'name' => 'Direct Bilirubin', 'input_type' => 'numeric', 'unit' => 'µmol/L', 'ref_min_male' => 0.0, 'ref_max_male' => 5.0, 'ref_min_female' => 0.0, 'ref_max_female' => 5.0, 'critical_low' => null, 'critical_high' => null],
                    ['code' => 'ALT', 'name' => 'ALT (SGPT)', 'input_type' => 'numeric', 'unit' => 'U/L', 'ref_min_male' => 7, 'ref_max_male' => 45, 'ref_min_female' => 7, 'ref_max_female' => 35, 'critical_low' => null, 'critical_high' => 500],
                    ['code' => 'AST', 'name' => 'AST (SGOT)', 'input_type' => 'numeric', 'unit' => 'U/L', 'ref_min_male' => 8, 'ref_max_male' => 40, 'ref_min_female' => 8, 'ref_max_female' => 35, 'critical_low' => null, 'critical_high' => 500],
                    ['code' => 'ALP', 'name' => 'Alkaline Phosphatase (ALP)', 'input_type' => 'numeric', 'unit' => 'U/L', 'ref_min_male' => 40, 'ref_max_male' => 130, 'ref_min_female' => 35, 'ref_max_female' => 105, 'critical_low' => null, 'critical_high' => null],
                    ['code' => 'ALB', 'name' => 'Serum Albumin', 'input_type' => 'numeric', 'unit' => 'g/L', 'ref_min_male' => 35, 'ref_max_male' => 52, 'ref_min_female' => 35, 'ref_max_female' => 52, 'critical_low' => 20, 'critical_high' => null],
                ],
            ],
            [
                'code' => 'FBS-01',
                'name' => 'Fasting Blood Glucose (FBS)',
                'category_id' => $catMap['CHEM']->id,
                'sample_type' => 'Fluoride Oxalate Plasma',
                'container_type' => 'Grey Top (Fluoride Oxalate)',
                'sample_volume' => '2 mL',
                'price' => 40.00,
                'nhis_tariff' => 20.00,
                'turnaround_hours' => 1,
                'methodology' => 'Hexokinase Enzymatic Assay',
                'requires_fasting' => true,
                'parameters' => [
                    ['code' => 'FBG', 'name' => 'Fasting Plasma Glucose', 'input_type' => 'numeric', 'unit' => 'mmol/L', 'ref_min_male' => 3.9, 'ref_max_male' => 5.6, 'ref_min_female' => 3.9, 'ref_max_female' => 5.6, 'critical_low' => 2.5, 'critical_high' => 22.0],
                ],
            ],
            [
                'code' => 'LIP-01',
                'name' => 'Fasting Lipid Profile',
                'category_id' => $catMap['CHEM']->id,
                'sample_type' => 'Serum',
                'container_type' => 'Gold Top (SST Gel)',
                'sample_volume' => '4 mL',
                'price' => 150.00,
                'nhis_tariff' => 85.00,
                'turnaround_hours' => 4,
                'methodology' => 'Enzymatic Colorimetric Assay',
                'requires_fasting' => true,
                'parameters' => [
                    ['code' => 'TCHOL', 'name' => 'Total Cholesterol', 'input_type' => 'numeric', 'unit' => 'mmol/L', 'ref_min_male' => 3.0, 'ref_max_male' => 5.2, 'ref_min_female' => 3.0, 'ref_max_female' => 5.2, 'critical_low' => null, 'critical_high' => 10.0],
                    ['code' => 'TRIG', 'name' => 'Triglycerides', 'input_type' => 'numeric', 'unit' => 'mmol/L', 'ref_min_male' => 0.4, 'ref_max_male' => 1.7, 'ref_min_female' => 0.4, 'ref_max_female' => 1.7, 'critical_low' => null, 'critical_high' => 11.0],
                    ['code' => 'HDL', 'name' => 'HDL Cholesterol', 'input_type' => 'numeric', 'unit' => 'mmol/L', 'ref_min_male' => 1.0, 'ref_max_male' => 2.2, 'ref_min_female' => 1.2, 'ref_max_female' => 2.5, 'critical_low' => null, 'critical_high' => null],
                    ['code' => 'LDL', 'name' => 'LDL Cholesterol (Direct)', 'input_type' => 'numeric', 'unit' => 'mmol/L', 'ref_min_male' => 0.0, 'ref_max_male' => 3.4, 'ref_min_female' => 0.0, 'ref_max_female' => 3.4, 'critical_low' => null, 'critical_high' => 6.5],
                ],
            ],
            [
                'code' => 'MP-01',
                'name' => 'Blood Film for Malaria Parasites (BF for MP + RDT)',
                'category_id' => $catMap['PARA']->id,
                'sample_type' => 'EDTA Whole Blood',
                'container_type' => 'Purple Top (EDTA)',
                'sample_volume' => '2 mL',
                'price' => 55.00,
                'nhis_tariff' => 30.00,
                'turnaround_hours' => 1,
                'methodology' => 'Giemsa Thick/Thin Film Microscopy & HRP2/pLDH RDT',
                'requires_fasting' => false,
                'parameters' => [
                    ['code' => 'MPRDT', 'name' => 'Malaria Rapid Antigen (HRP2/pLDH)', 'input_type' => 'select', 'unit' => null, 'reference_text' => 'Negative', 'options_json' => ['Negative', 'Positive (P. falciparum)', 'Positive (Pan-malaria)'] ],
                    ['code' => 'MPMIC', 'name' => 'Thick Film Microscopy', 'input_type' => 'select', 'unit' => null, 'reference_text' => 'No Malaria Parasites Seen', 'options_json' => ['No Malaria Parasites Seen', 'Trophozoites of P. falciparum seen (+)', 'Trophozoites of P. falciparum seen (++)', 'Hyperparasitaemia (+++)'] ],
                    ['code' => 'MPDEN', 'name' => 'Parasite Density', 'input_type' => 'text', 'unit' => 'parasites/µL', 'reference_text' => '0 parasites/µL'],
                ],
            ],
            [
                'code' => 'URN-01',
                'name' => 'Urine Routine Examination (Urine R/E)',
                'category_id' => $catMap['URIN']->id,
                'sample_type' => 'Mid-Stream Urine',
                'container_type' => 'Sterile Universal Container',
                'sample_volume' => '15 mL',
                'price' => 60.00,
                'nhis_tariff' => 30.00,
                'turnaround_hours' => 2,
                'methodology' => '10-Parameter Dipstick & Centrifuged Sediment Microscopy',
                'requires_fasting' => false,
                'parameters' => [
                    ['code' => 'UCOL', 'name' => 'Appearance / Colour', 'input_type' => 'select', 'unit' => null, 'reference_text' => 'Amber & Clear', 'options_json' => ['Straw & Clear', 'Amber & Clear', 'Cloudy / Turbid', 'Blood-stained'] ],
                    ['code' => 'UPH', 'name' => 'Urine pH', 'input_type' => 'numeric', 'unit' => null, 'ref_min_male' => 4.5, 'ref_max_male' => 8.0, 'ref_min_female' => 4.5, 'ref_max_female' => 8.0, 'critical_low' => null, 'critical_high' => null],
                    ['code' => 'UPROT', 'name' => 'Urine Protein', 'input_type' => 'select', 'unit' => null, 'reference_text' => 'Negative', 'options_json' => ['Negative', 'Trace', 'Positive (+)', 'Positive (++)', 'Positive (+++)'] ],
                    ['code' => 'UGLU', 'name' => 'Urine Glucose', 'input_type' => 'select', 'unit' => null, 'reference_text' => 'Negative', 'options_json' => ['Negative', 'Trace', 'Positive (+)', 'Positive (++)'] ],
                    ['code' => 'ULEUK', 'name' => 'Leukocyte Esterase', 'input_type' => 'select', 'unit' => null, 'reference_text' => 'Negative', 'options_json' => ['Negative', 'Trace', 'Positive (+)', 'Positive (++)'] ],
                    ['code' => 'UPUS', 'name' => 'Pus Cells (Microscopy)', 'input_type' => 'text', 'unit' => '/HPF', 'reference_text' => '0 - 4 /HPF'],
                ],
            ],
            [
                'code' => 'HBS-01',
                'name' => 'Hepatitis B Surface Antigen (HBsAg Quantitative/Screen)',
                'category_id' => $catMap['SERO']->id,
                'sample_type' => 'Serum',
                'container_type' => 'Gold Top (SST Gel)',
                'sample_volume' => '3 mL',
                'price' => 70.00,
                'nhis_tariff' => 40.00,
                'turnaround_hours' => 2,
                'methodology' => 'Immunochromatographic / ELISA Confirmation',
                'requires_fasting' => false,
                'parameters' => [
                    ['code' => 'HBSAG', 'name' => 'HBsAg Result', 'input_type' => 'select', 'unit' => null, 'reference_text' => 'Non-Reactive', 'options_json' => ['Non-Reactive', 'Reactive (Positive)', 'Indeterminate'] ],
                ],
            ],
            [
                'code' => 'TFT-01',
                'name' => 'Thyroid Function Panel (TSH, Free T3, Free T4)',
                'category_id' => $catMap['IMMU']->id,
                'sample_type' => 'Serum',
                'container_type' => 'Gold Top (SST Gel)',
                'sample_volume' => '4 mL',
                'price' => 280.00,
                'nhis_tariff' => 160.00,
                'turnaround_hours' => 6,
                'methodology' => 'Electrochemiluminescence Immunoassay (ECLIA)',
                'requires_fasting' => false,
                'parameters' => [
                    ['code' => 'TSH', 'name' => 'Thyroid Stimulating Hormone (TSH)', 'input_type' => 'numeric', 'unit' => 'mIU/L', 'ref_min_male' => 0.4, 'ref_max_male' => 4.2, 'ref_min_female' => 0.4, 'ref_max_female' => 4.2, 'critical_low' => 0.05, 'critical_high' => 30.0],
                    ['code' => 'FT4', 'name' => 'Free Thyroxine (FT4)', 'input_type' => 'numeric', 'unit' => 'pmol/L', 'ref_min_male' => 12.0, 'ref_max_male' => 22.0, 'ref_min_female' => 12.0, 'ref_max_female' => 22.0, 'critical_low' => 5.0, 'critical_high' => 50.0],
                    ['code' => 'FT3', 'name' => 'Free Triiodothyronine (FT3)', 'input_type' => 'numeric', 'unit' => 'pmol/L', 'ref_min_male' => 3.1, 'ref_max_male' => 6.8, 'ref_min_female' => 3.1, 'ref_max_female' => 6.8, 'critical_low' => null, 'critical_high' => null],
                ],
            ],
            [
                'code' => 'UCS-01',
                'name' => 'Urine Culture & Sensitivity (M/C/S)',
                'category_id' => $catMap['MICRO']->id,
                'sample_type' => 'Mid-Stream Urine',
                'container_type' => 'Sterile Universal Container',
                'sample_volume' => '20 mL',
                'price' => 190.00,
                'nhis_tariff' => 110.00,
                'turnaround_hours' => 48,
                'methodology' => 'CLED Agar Culture & Kirby-Bauer Disc Diffusion',
                'requires_fasting' => false,
                'parameters' => [
                    ['code' => 'CULT', 'name' => 'Culture Isolate (48h at 37°C)', 'input_type' => 'select', 'unit' => null, 'reference_text' => 'No Significant Bacterial Growth', 'options_json' => ['No Significant Bacterial Growth', 'Escherichia coli (>10^5 CFU/mL)', 'Klebsiella pneumoniae (>10^5 CFU/mL)', 'Staphylococcus saprophyticus (>10^5 CFU/mL)'] ],
                    ['code' => 'SENS', 'name' => 'Antimicrobial Susceptibility Summary', 'input_type' => 'text', 'unit' => null, 'reference_text' => 'N/A'],
                ],
            ],
            [
                'code' => 'BX-01',
                'name' => 'Small Tissue Biopsy Histopathology',
                'category_id' => $catMap['HISTO']->id,
                'sample_type' => 'Formalin-Fixed Tissue Biopsy',
                'container_type' => '10% Neutral Buffered Formalin Pot',
                'sample_volume' => 'Biopsy Specimen',
                'price' => 420.00,
                'nhis_tariff' => null,
                'turnaround_hours' => 72,
                'methodology' => 'Paraffin Embedding, H&E Staining & Microscopic Evaluation',
                'requires_fasting' => false,
                'parameters' => [
                    ['code' => 'MACR', 'name' => 'Macroscopic Description', 'input_type' => 'text', 'unit' => null, 'reference_text' => 'Gross morphology'],
                    ['code' => 'MICR', 'name' => 'Microscopic Findings', 'input_type' => 'text', 'unit' => null, 'reference_text' => 'Histological evaluation'],
                    ['code' => 'DIAG', 'name' => 'Histopathological Diagnosis', 'input_type' => 'text', 'unit' => null, 'reference_text' => 'Benign / Normal Tissue'],
                ],
            ],
        ];

        $testMap = [];
        foreach ($catalogueTests as $td) {
            $params = $td['parameters'];
            unset($td['parameters']);
            $t = LabTest::create($td);
            foreach ($params as $idx => $p) {
                TestParameter::create([
                    'lab_test_id' => $t->id,
                    ...$p,
                    'sort_order' => $idx + 1,
                ]);
            }
            $testMap[$t->code] = $t;
        }

        // 7. Seed Realistic Patients across Accra & Kumasi Branches
        $patientsData = [
            [
                'patient_code' => 'LV-2026-0001',
                'first_name' => 'Kofi',
                'last_name' => 'Agyeman-Prempeh',
                'other_names' => 'Baffour',
                'date_of_birth' => '1978-04-12',
                'gender' => 'Male',
                'phone' => '+233 244 819 201',
                'email' => 'k.agyeman@gmail.com',
                'address' => 'House 18, Labone Crescent, Accra',
                'city' => 'Accra',
                'nhis_number' => 'NHIS-84920112',
                'blood_group' => 'O+',
                'genotype' => 'AA',
                'emergency_contact_name' => 'Ama Agyeman-Prempeh',
                'emergency_contact_phone' => '+233 244 819 209',
                'emergency_contact_relation' => 'Spouse',
                'branch_id' => $accraBranch->id,
                'registered_by' => $receptionistUser->id,
                'created_at' => now()->subDays(2),
            ],
            [
                'patient_code' => 'LV-2026-0002',
                'first_name' => 'Adwoa',
                'last_name' => 'Mensima-Korsah',
                'other_names' => 'Nana',
                'date_of_birth' => '1992-09-23',
                'gender' => 'Female',
                'phone' => '+233 554 302 918',
                'email' => 'adwoa.korsah@yahoo.com',
                'address' => 'East Legon, American House Lane 4, Accra',
                'city' => 'Accra',
                'nhis_number' => 'NHIS-51908374',
                'blood_group' => 'A+',
                'genotype' => 'AS',
                'emergency_contact_name' => 'Yaw Korsah',
                'emergency_contact_phone' => '+233 208 112 340',
                'emergency_contact_relation' => 'Brother',
                'branch_id' => $accraBranch->id,
                'registered_by' => $receptionistUser->id,
                'created_at' => now()->subDay(),
            ],
            [
                'patient_code' => 'LV-2026-0003',
                'first_name' => 'Nii Armah',
                'last_name' => 'Vanderpuye',
                'other_names' => 'Tetteh',
                'date_of_birth' => '1965-11-05',
                'gender' => 'Male',
                'phone' => '+233 207 492 100',
                'email' => 'nii.vanderpuye@outlook.com',
                'address' => '24 Ringway Estates, Osu, Accra',
                'city' => 'Accra',
                'nhis_number' => 'NHIS-10938475',
                'blood_group' => 'B+',
                'genotype' => 'AA',
                'emergency_contact_name' => 'Naa Dedei Vanderpuye',
                'emergency_contact_phone' => '+233 207 492 105',
                'emergency_contact_relation' => 'Spouse',
                'branch_id' => $accraBranch->id,
                'registered_by' => $receptionistUser->id,
                'created_at' => now()->subHours(5),
            ],
            [
                'patient_code' => 'LV-2026-0004',
                'first_name' => 'Esi',
                'last_name' => 'Arhinful-Quansah',
                'other_names' => 'Maame',
                'date_of_birth' => '2001-02-18',
                'gender' => 'Female',
                'phone' => '+233 249 611 402',
                'email' => 'esi.quansah@ug.edu.gh',
                'address' => 'Legon Hall Annex B, University of Ghana',
                'city' => 'Accra',
                'nhis_number' => 'NHIS-77382910',
                'blood_group' => 'O-',
                'genotype' => 'AA',
                'emergency_contact_name' => 'Prof. Kobina Quansah',
                'emergency_contact_phone' => '+233 244 331 098',
                'emergency_contact_relation' => 'Father',
                'branch_id' => $accraBranch->id,
                'registered_by' => $receptionistUser->id,
                'created_at' => now()->subHours(2),
            ],
            [
                'patient_code' => 'LV-2026-0005',
                'first_name' => 'Kwaku',
                'last_name' => 'Sarpong-Bonsu',
                'other_names' => 'Osei',
                'date_of_birth' => '1984-07-30',
                'gender' => 'Male',
                'phone' => '+233 243 998 112',
                'email' => 'ksarpong@ashantigold.com',
                'address' => 'Ahodwo Roundabout, Nhyiaeso, Kumasi',
                'city' => 'Kumasi',
                'nhis_number' => 'NHIS-66291044',
                'blood_group' => 'AB+',
                'genotype' => 'AS',
                'emergency_contact_name' => 'Afia Sarpong',
                'emergency_contact_phone' => '+233 243 998 199',
                'emergency_contact_relation' => 'Spouse',
                'branch_id' => $kumasiBranch->id,
                'registered_by' => $kumasiManager->id,
                'created_at' => now()->subHours(3),
            ],
        ];

        $patients = [];
        foreach ($patientsData as $pd) {
            $patients[$pd['patient_code']] = Patient::create($pd);
        }

        // 8. Seed Orders, Samples, Results, Approvals, Reports, and Payments across Lifecycle States

        // CASE 1: Completed Order with Approved Results & Released Report (Patient 1: Kofi Agyeman-Prempeh)
        $order1 = LabOrder::create([
            'order_number' => 'ORD-20261107-0001',
            'patient_id' => $patients['LV-2026-0001']->id,
            'branch_id' => $accraBranch->id,
            'priority' => 'routine',
            'referring_doctor' => 'Dr. Samuel Koranteng (Ridge Hospital)',
            'referring_facility' => 'Greater Accra Regional Hospital',
            'clinical_notes' => 'Annual executive wellness & hypertension follow-up.',
            'diagnosis' => 'Essential Hypertension',
            'subtotal' => 245.00,
            'discount_amount' => 15.00,
            'discount_reason' => 'Corporate Wellness Partner Tariff',
            'total_amount' => 230.00,
            'amount_paid' => 230.00,
            'balance_due' => 0.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'ordered_by' => $receptionistUser->id,
            'ordered_at' => now()->subDay(),
            'completed_at' => now()->subHours(16),
            'created_at' => now()->subDay(),
        ]);

        $sample1a = Sample::create([
            'sample_code' => 'SMP-20261107-0001',
            'barcode' => 'LV26110700001',
            'lab_order_id' => $order1->id,
            'patient_id' => $order1->patient_id,
            'branch_id' => $accraBranch->id,
            'sample_type' => 'EDTA Whole Blood',
            'container_type' => 'Purple Top (K2-EDTA 4mL)',
            'collection_site' => 'Left Antecubital Venipuncture',
            'status' => 'completed',
            'collected_by' => $scientistUser->id,
            'collected_at' => now()->subHours(22),
            'received_by' => $scientistUser->id,
            'received_at' => now()->subHours(21),
            'accepted_by' => $scientistUser->id,
            'accepted_at' => now()->subHours(21),
            'storage_location' => 'Haematology Rack A1',
        ]);

        $sample1b = Sample::create([
            'sample_code' => 'SMP-20261107-0002',
            'barcode' => 'LV26110700002',
            'lab_order_id' => $order1->id,
            'patient_id' => $order1->patient_id,
            'branch_id' => $accraBranch->id,
            'sample_type' => 'Serum',
            'container_type' => 'Gold Top (SST Gel 5mL)',
            'collection_site' => 'Left Antecubital Venipuncture',
            'status' => 'completed',
            'collected_by' => $scientistUser->id,
            'collected_at' => now()->subHours(22),
            'received_by' => $scientistUser->id,
            'received_at' => now()->subHours(21),
            'accepted_by' => $scientistUser->id,
            'accepted_at' => now()->subHours(21),
            'storage_location' => 'Chemistry Cold Rack C2',
        ]);

        $item1Fbc = LabOrderItem::create([
            'lab_order_id' => $order1->id,
            'lab_test_id' => $testMap['FBC-01']->id,
            'sample_id' => $sample1a->id,
            'price' => 85.00,
            'status' => 'approved',
            'assigned_to' => $scientistUser->id,
            'entered_by' => $scientistUser->id,
            'started_at' => now()->subHours(20),
            'submitted_at' => now()->subHours(18),
            'approved_by' => $supervisorUser->id,
            'approved_at' => now()->subHours(16),
            'review_decision' => 'approved',
            'technician_notes' => 'QC Level 1 & 2 passed on Sysmex XN-1000.',
            'reviewer_comments' => 'Normocytic normochromic blood picture. Verified.',
            'has_critical_values' => false,
            'has_abnormal_values' => false,
        ]);

        $fbcValues = [
            'HB' => ['val' => '14.8', 'num' => 14.8, 'ref' => '13 - 17.5', 'flag' => 'normal'],
            'TWBC' => ['val' => '6.4', 'num' => 6.4, 'ref' => '4 - 11', 'flag' => 'normal'],
            'PLT' => ['val' => '265', 'num' => 265, 'ref' => '150 - 450', 'flag' => 'normal'],
            'HCT' => ['val' => '44.2', 'num' => 44.2, 'ref' => '40 - 52', 'flag' => 'normal'],
            'MCV' => ['val' => '88.5', 'num' => 88.5, 'ref' => '80 - 100', 'flag' => 'normal'],
            'NEUT' => ['val' => '58.0', 'num' => 58.0, 'ref' => '40 - 75', 'flag' => 'normal'],
            'LYMPH' => ['val' => '32.0', 'num' => 32.0, 'ref' => '20 - 45', 'flag' => 'normal'],
        ];
        foreach ($testMap['FBC-01']->parameters as $param) {
            $v = $fbcValues[$param->code];
            TestResult::create([
                'lab_order_item_id' => $item1Fbc->id,
                'test_parameter_id' => $param->id,
                'result_value' => $v['val'],
                'numeric_value' => $v['num'],
                'unit' => $param->unit,
                'reference_range' => $v['ref'],
                'flag' => $v['flag'],
                'is_draft' => false,
                'entered_by' => $scientistUser->id,
                'updated_by' => $scientistUser->id,
            ]);
        }

        $item1Rft = LabOrderItem::create([
            'lab_order_id' => $order1->id,
            'lab_test_id' => $testMap['RFT-01']->id,
            'sample_id' => $sample1b->id,
            'price' => 160.00,
            'status' => 'approved',
            'assigned_to' => $scientistUser->id,
            'entered_by' => $scientistUser->id,
            'started_at' => now()->subHours(20),
            'submitted_at' => now()->subHours(18),
            'approved_by' => $supervisorUser->id,
            'approved_at' => now()->subHours(16),
            'review_decision' => 'approved',
            'technician_notes' => 'Serum clear, non-haemolysed.',
            'reviewer_comments' => 'Mildly elevated creatinine; correlate clinically with antihypertensive therapy.',
            'has_critical_values' => false,
            'has_abnormal_values' => true,
        ]);

        $rftValues = [
            'NA' => ['val' => '140', 'num' => 140, 'ref' => '135 - 145', 'flag' => 'normal'],
            'K' => ['val' => '4.6', 'num' => 4.6, 'ref' => '3.5 - 5.1', 'flag' => 'normal'],
            'CL' => ['val' => '102', 'num' => 102, 'ref' => '98 - 107', 'flag' => 'normal'],
            'UREA' => ['val' => '6.8', 'num' => 6.8, 'ref' => '2.5 - 7.8', 'flag' => 'normal'],
            'CREAT' => ['val' => '114', 'num' => 114, 'ref' => '62 - 106', 'flag' => 'high'],
            'EGFR' => ['val' => '68', 'num' => 68, 'ref' => '90 - 140', 'flag' => 'low'],
        ];
        foreach ($testMap['RFT-01']->parameters as $param) {
            $v = $rftValues[$param->code];
            TestResult::create([
                'lab_order_item_id' => $item1Rft->id,
                'test_parameter_id' => $param->id,
                'result_value' => $v['val'],
                'numeric_value' => $v['num'],
                'unit' => $param->unit,
                'reference_range' => $v['ref'],
                'flag' => $v['flag'],
                'is_draft' => false,
                'entered_by' => $scientistUser->id,
                'updated_by' => $scientistUser->id,
            ]);
        }

        LabReport::create([
            'report_number' => 'RPT-20261107-0001',
            'lab_order_id' => $order1->id,
            'patient_id' => $order1->patient_id,
            'branch_id' => $accraBranch->id,
            'status' => 'released',
            'verification_code' => 'LV-VER-9824XK71PL',
            'pathologist_comment' => 'Haematological parameters are within normal reference limits. Mild elevation in serum creatinine (114 µmol/L) with eGFR 68 mL/min/1.73m² noted; recommend repeat renal panel in 3 months.',
            'clinical_interpretation' => 'Stage 2 mild reduction in GFR; normal electrolytes and blood count.',
            'generated_by' => $supervisorUser->id,
            'approved_by' => $supervisorUser->id,
            'released_at' => now()->subHours(16),
            'download_count' => 2,
        ]);

        Payment::create([
            'receipt_number' => 'RCP-20261107-0001',
            'invoice_number' => 'INV-20261107-0001',
            'lab_order_id' => $order1->id,
            'patient_id' => $order1->patient_id,
            'branch_id' => $accraBranch->id,
            'amount' => 230.00,
            'payment_method' => 'mtn_momo',
            'transaction_reference' => 'MOMO-GH-3948102948',
            'payer_name' => $patients['LV-2026-0001']->full_name,
            'payer_phone' => $patients['LV-2026-0001']->phone,
            'status' => 'completed',
            'received_by' => $cashierUser->id,
            'paid_at' => now()->subDay(),
            'notes' => 'Full settlement via MTN Mobile Money.',
        ]);

        // CASE 2: Urgent Order Submitted & Awaiting Approval with a CRITICAL value (Patient 3: Nii Armah Vanderpuye)
        $order2 = LabOrder::create([
            'order_number' => 'ORD-20261107-0002',
            'patient_id' => $patients['LV-2026-0003']->id,
            'branch_id' => $accraBranch->id,
            'priority' => 'stat',
            'referring_doctor' => 'Dr. Matilda Ofori-Atta (37 Military Hospital)',
            'referring_facility' => '37 Military Hospital',
            'clinical_notes' => 'Known diabetic presenting with polyuria, lethargy, and severe hyperglycaemia symptoms.',
            'diagnosis' => 'Uncontrolled Type 2 Diabetes Mellitus / Rule out DKA',
            'subtotal' => 200.00,
            'discount_amount' => 0.00,
            'total_amount' => 200.00,
            'amount_paid' => 100.00,
            'balance_due' => 100.00,
            'payment_status' => 'partial',
            'status' => 'awaiting_approval',
            'ordered_by' => $receptionistUser->id,
            'ordered_at' => now()->subHours(4),
            'created_at' => now()->subHours(4),
        ]);

        $sample2a = Sample::create([
            'sample_code' => 'SMP-20261107-0003',
            'barcode' => 'LV26110700003',
            'lab_order_id' => $order2->id,
            'patient_id' => $order2->patient_id,
            'branch_id' => $accraBranch->id,
            'sample_type' => 'Fluoride Oxalate Plasma',
            'container_type' => 'Grey Top (Fluoride Oxalate)',
            'collection_site' => 'Right Forearm Venipuncture',
            'status' => 'processing',
            'collected_by' => $scientistUser->id,
            'collected_at' => now()->subHours(3),
            'received_by' => $scientistUser->id,
            'received_at' => now()->subHours(3),
            'accepted_by' => $scientistUser->id,
            'accepted_at' => now()->subHours(3),
            'storage_location' => 'STAT Bench 1',
        ]);

        $sample2b = Sample::create([
            'sample_code' => 'SMP-20261107-0004',
            'barcode' => 'LV26110700004',
            'lab_order_id' => $order2->id,
            'patient_id' => $order2->patient_id,
            'branch_id' => $accraBranch->id,
            'sample_type' => 'Serum',
            'container_type' => 'Gold Top (SST Gel 5mL)',
            'collection_site' => 'Right Forearm Venipuncture',
            'status' => 'accepted',
            'collected_by' => $scientistUser->id,
            'collected_at' => now()->subHours(3),
            'received_by' => $scientistUser->id,
            'received_at' => now()->subHours(3),
            'accepted_by' => $scientistUser->id,
            'accepted_at' => now()->subHours(3),
            'storage_location' => 'STAT Bench 1',
        ]);

        $item2Fbs = LabOrderItem::create([
            'lab_order_id' => $order2->id,
            'lab_test_id' => $testMap['FBS-01']->id,
            'sample_id' => $sample2a->id,
            'price' => 40.00,
            'status' => 'submitted',
            'assigned_to' => $scientistUser->id,
            'entered_by' => $scientistUser->id,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'technician_notes' => 'CRITICAL HIGH GLUCOSE: Repeated dilution assay on Cobas c311; confirmed 24.6 mmol/L.',
            'has_critical_values' => true,
            'has_abnormal_values' => true,
            'repeat_count' => 1,
        ]);

        $fbgParam = $testMap['FBS-01']->parameters->first();
        TestResult::create([
            'lab_order_item_id' => $item2Fbs->id,
            'test_parameter_id' => $fbgParam->id,
            'result_value' => '24.6',
            'numeric_value' => 24.6,
            'unit' => 'mmol/L',
            'reference_range' => '3.9 - 5.6',
            'flag' => 'critical_high',
            'is_draft' => false,
            'remarks' => 'Verified by repeat analysis.',
            'entered_by' => $scientistUser->id,
            'updated_by' => $scientistUser->id,
        ]);

        // Second item on Order 2: RFT awaiting result entry in Laboratory Workspace
        LabOrderItem::create([
            'lab_order_id' => $order2->id,
            'lab_test_id' => $testMap['RFT-01']->id,
            'sample_id' => $sample2b->id,
            'price' => 160.00,
            'status' => 'awaiting_processing',
            'assigned_to' => $scientistUser->id,
        ]);

        Payment::create([
            'receipt_number' => 'RCP-20261107-0002',
            'invoice_number' => 'INV-20261107-0002',
            'lab_order_id' => $order2->id,
            'patient_id' => $order2->patient_id,
            'branch_id' => $accraBranch->id,
            'amount' => 100.00,
            'payment_method' => 'cash',
            'transaction_reference' => 'CASH-DESK-01',
            'payer_name' => $patients['LV-2026-0003']->full_name,
            'payer_phone' => $patients['LV-2026-0003']->phone,
            'status' => 'completed',
            'received_by' => $cashierUser->id,
            'paid_at' => now()->subHours(4),
            'notes' => '50% deposit paid at emergency reception.',
        ]);

        // CASE 3: Sample Accepted & Ready for Result Entry (Patient 2: Adwoa Mensima-Korsah)
        $order3 = LabOrder::create([
            'order_number' => 'ORD-20261107-0003',
            'patient_id' => $patients['LV-2026-0002']->id,
            'branch_id' => $accraBranch->id,
            'priority' => 'urgent',
            'referring_doctor' => 'Dr. Kwesi Yankah (Nyaho Medical Centre)',
            'referring_facility' => 'Nyaho Medical Centre',
            'clinical_notes' => 'Acute febrile illness, headache, and chills for 3 days.',
            'diagnosis' => 'Suspected Acute Malaria / UTI',
            'subtotal' => 115.00,
            'discount_amount' => 0.00,
            'total_amount' => 115.00,
            'amount_paid' => 115.00,
            'balance_due' => 0.00,
            'payment_status' => 'paid',
            'status' => 'processing',
            'ordered_by' => $receptionistUser->id,
            'ordered_at' => now()->subHours(3),
            'created_at' => now()->subHours(3),
        ]);

        $sample3a = Sample::create([
            'sample_code' => 'SMP-20261107-0005',
            'barcode' => 'LV26110700005',
            'lab_order_id' => $order3->id,
            'patient_id' => $order3->patient_id,
            'branch_id' => $accraBranch->id,
            'sample_type' => 'EDTA Whole Blood',
            'container_type' => 'Purple Top (EDTA)',
            'collection_site' => 'Left Antecubital',
            'status' => 'accepted',
            'collected_by' => $scientistUser->id,
            'collected_at' => now()->subHours(2),
            'received_by' => $scientistUser->id,
            'received_at' => now()->subHours(2),
            'accepted_by' => $scientistUser->id,
            'accepted_at' => now()->subHours(2),
            'storage_location' => 'Parasitology Bench B2',
        ]);

        $sample3b = Sample::create([
            'sample_code' => 'SMP-20261107-0006',
            'barcode' => 'LV26110700006',
            'lab_order_id' => $order3->id,
            'patient_id' => $order3->patient_id,
            'branch_id' => $accraBranch->id,
            'sample_type' => 'Mid-Stream Urine',
            'container_type' => 'Sterile Universal Container',
            'collection_site' => 'Clean-Catch Mid-Stream',
            'status' => 'collected',
            'collected_by' => $scientistUser->id,
            'collected_at' => now()->subHour(),
        ]);

        $item3Mp = LabOrderItem::create([
            'lab_order_id' => $order3->id,
            'lab_test_id' => $testMap['MP-01']->id,
            'sample_id' => $sample3a->id,
            'price' => 55.00,
            'status' => 'submitted',
            'assigned_to' => $scientistUser->id,
            'entered_by' => $scientistUser->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinutes(35),
            'technician_notes' => 'Giemsa thick and thin blood film examined under 100x oil immersion.',
            'has_critical_values' => false,
            'has_abnormal_values' => true,
        ]);

        $mpValues = [
            'MPRDT' => ['val' => 'Positive (P. falciparum)', 'flag' => 'abnormal'],
            'MPMIC' => ['val' => 'Trophozoites of P. falciparum seen (+)', 'flag' => 'abnormal'],
            'MPDEN' => ['val' => '1,450', 'flag' => 'abnormal'],
        ];
        foreach ($testMap['MP-01']->parameters as $param) {
            $v = $mpValues[$param->code];
            TestResult::create([
                'lab_order_item_id' => $item3Mp->id,
                'test_parameter_id' => $param->id,
                'result_value' => $v['val'],
                'numeric_value' => null,
                'unit' => $param->unit,
                'reference_range' => $param->reference_text,
                'flag' => $v['flag'],
                'is_draft' => false,
                'entered_by' => $scientistUser->id,
                'updated_by' => $scientistUser->id,
            ]);
        }

        LabOrderItem::create([
            'lab_order_id' => $order3->id,
            'lab_test_id' => $testMap['URN-01']->id,
            'sample_id' => $sample3b->id,
            'price' => 60.00,
            'status' => 'pending_sample',
        ]);

        Payment::create([
            'receipt_number' => 'RCP-20261107-0003',
            'invoice_number' => 'INV-20261107-0003',
            'lab_order_id' => $order3->id,
            'patient_id' => $order3->patient_id,
            'branch_id' => $accraBranch->id,
            'amount' => 115.00,
            'payment_method' => 'telecel_cash',
            'transaction_reference' => 'TCEL-88291044',
            'payer_name' => $patients['LV-2026-0002']->full_name,
            'payer_phone' => $patients['LV-2026-0002']->phone,
            'status' => 'completed',
            'received_by' => $cashierUser->id,
            'paid_at' => now()->subHours(3),
        ]);

        // CASE 4: Newly Ordered Today — Awaiting Sample Collection & Unpaid (Patient 4: Esi Arhinful-Quansah)
        $order4 = LabOrder::create([
            'order_number' => 'ORD-20261107-0004',
            'patient_id' => $patients['LV-2026-0004']->id,
            'branch_id' => $accraBranch->id,
            'priority' => 'routine',
            'referring_doctor' => 'Dr. Joyce Aryee (University Hospital Legon)',
            'referring_facility' => 'University of Ghana Hospital',
            'clinical_notes' => 'Pre-employment medical screening & liver panel.',
            'diagnosis' => 'Routine Medical Fitness Screening',
            'subtotal' => 245.00,
            'discount_amount' => 0.00,
            'total_amount' => 245.00,
            'amount_paid' => 0.00,
            'balance_due' => 245.00,
            'payment_status' => 'unpaid',
            'status' => 'ordered',
            'ordered_by' => $receptionistUser->id,
            'ordered_at' => now()->subHour(),
            'created_at' => now()->subHour(),
        ]);

        $sample4 = Sample::create([
            'sample_code' => 'SMP-20261107-0007',
            'barcode' => 'LV26110700007',
            'lab_order_id' => $order4->id,
            'patient_id' => $order4->patient_id,
            'branch_id' => $accraBranch->id,
            'sample_type' => 'Serum',
            'container_type' => 'Gold Top (SST Gel 5mL)',
            'status' => 'awaiting_collection',
        ]);

        LabOrderItem::create([
            'lab_order_id' => $order4->id,
            'lab_test_id' => $testMap['LFT-01']->id,
            'sample_id' => $sample4->id,
            'price' => 175.00,
            'status' => 'pending_sample',
        ]);

        LabOrderItem::create([
            'lab_order_id' => $order4->id,
            'lab_test_id' => $testMap['HBS-01']->id,
            'sample_id' => $sample4->id,
            'price' => 70.00,
            'status' => 'pending_sample',
        ]);

        // CASE 5: Kumasi Branch Order (Patient 5: Kwaku Sarpong-Bonsu)
        $order5 = LabOrder::create([
            'order_number' => 'ORD-20261107-0005',
            'patient_id' => $patients['LV-2026-0005']->id,
            'branch_id' => $kumasiBranch->id,
            'priority' => 'routine',
            'referring_doctor' => 'Dr. Osei Bonsu (Komfo Anokye Teaching Hospital)',
            'referring_facility' => 'KATH Kumasi',
            'clinical_notes' => 'Fasting lipid profile & thyroid evaluation.',
            'diagnosis' => 'Dyslipidaemia evaluation',
            'subtotal' => 430.00,
            'discount_amount' => 30.00,
            'discount_reason' => 'Senior Citizen Courtesy',
            'total_amount' => 400.00,
            'amount_paid' => 400.00,
            'balance_due' => 0.00,
            'payment_status' => 'paid',
            'status' => 'processing',
            'ordered_by' => $kumasiManager->id,
            'ordered_at' => now()->subHours(2),
            'created_at' => now()->subHours(2),
        ]);

        $sample5 = Sample::create([
            'sample_code' => 'SMP-20261107-0008',
            'barcode' => 'LV26110700008',
            'lab_order_id' => $order5->id,
            'patient_id' => $order5->patient_id,
            'branch_id' => $kumasiBranch->id,
            'sample_type' => 'Serum',
            'container_type' => 'Gold Top (SST Gel)',
            'status' => 'accepted',
            'collected_by' => $kumasiManager->id,
            'collected_at' => now()->subHours(2),
            'received_by' => $kumasiManager->id,
            'received_at' => now()->subHour(),
            'accepted_by' => $kumasiManager->id,
            'accepted_at' => now()->subHour(),
            'storage_location' => 'Kumasi Bench 1',
        ]);

        LabOrderItem::create([
            'lab_order_id' => $order5->id,
            'lab_test_id' => $testMap['LIP-01']->id,
            'sample_id' => $sample5->id,
            'price' => 150.00,
            'status' => 'awaiting_processing',
            'assigned_to' => $kumasiManager->id,
        ]);

        LabOrderItem::create([
            'lab_order_id' => $order5->id,
            'lab_test_id' => $testMap['TFT-01']->id,
            'sample_id' => $sample5->id,
            'price' => 280.00,
            'status' => 'awaiting_processing',
            'assigned_to' => $kumasiManager->id,
        ]);

        Payment::create([
            'receipt_number' => 'RCP-20261107-0004',
            'invoice_number' => 'INV-20261107-0005',
            'lab_order_id' => $order5->id,
            'patient_id' => $order5->patient_id,
            'branch_id' => $kumasiBranch->id,
            'amount' => 400.00,
            'payment_method' => 'mtn_momo',
            'transaction_reference' => 'MOMO-KMS-77482910',
            'payer_name' => $patients['LV-2026-0005']->full_name,
            'payer_phone' => $patients['LV-2026-0005']->phone,
            'status' => 'completed',
            'received_by' => $kumasiManager->id,
            'paid_at' => now()->subHours(2),
        ]);

        // 9. Seed Inventory Items & Stock Movements
        $inventorySeed = [
            [
                'sku' => 'REG-HAEM-001',
                'name' => 'Sysmex Cellpack DCL Diluent (20L)',
                'category' => 'reagent',
                'unit' => 'Cubitaner',
                'branch_id' => $accraBranch->id,
                'quantity_on_hand' => 14,
                'reorder_level' => 5,
                'unit_cost' => 680.00,
                'supplier_name' => 'MedLab Diagnostics Ghana Ltd.',
                'supplier_contact' => '+233 302 229 410',
                'batch_number' => 'LOT-SX2026-09',
                'expiry_date' => now()->addMonths(8)->toDateString(),
                'storage_condition' => '15-30°C Room Temp',
                'location_bin' => 'Store A - Shelf 1',
            ],
            [
                'sku' => 'REG-CHEM-002',
                'name' => 'Cobas Creatinine Jaffé Gen.2 Reagent Cassette',
                'category' => 'reagent',
                'unit' => 'Cassette',
                'branch_id' => $accraBranch->id,
                'quantity_on_hand' => 3, // Low stock!
                'reorder_level' => 6,
                'unit_cost' => 920.00,
                'supplier_name' => 'Roche Diagnostics West Africa',
                'supplier_contact' => '+233 302 761 090',
                'batch_number' => 'LOT-RC88412',
                'expiry_date' => now()->addDays(28)->toDateString(), // Expiring soon!
                'storage_condition' => '2-8°C Cold Room',
                'location_bin' => 'Cold Room Fridge 2',
            ],
            [
                'sku' => 'CON-TUBE-003',
                'name' => 'BD Vacutainer K2-EDTA Purple Top Tubes (Pack of 100)',
                'category' => 'collection_tube',
                'unit' => 'Pack',
                'branch_id' => $accraBranch->id,
                'quantity_on_hand' => 28,
                'reorder_level' => 10,
                'unit_cost' => 145.00,
                'supplier_name' => 'Gold Coast Medical Supplies',
                'supplier_contact' => '+233 244 600 811',
                'batch_number' => 'BD-491022',
                'expiry_date' => now()->addMonths(14)->toDateString(),
                'storage_condition' => 'Room Temp',
                'location_bin' => 'Phlebotomy Store B3',
            ],
            [
                'sku' => 'CON-TUBE-004',
                'name' => 'BD Vacutainer SST II Advance Gold Top Tubes (Pack of 100)',
                'category' => 'collection_tube',
                'unit' => 'Pack',
                'branch_id' => $accraBranch->id,
                'quantity_on_hand' => 4, // Low stock!
                'reorder_level' => 8,
                'unit_cost' => 165.00,
                'supplier_name' => 'Gold Coast Medical Supplies',
                'supplier_contact' => '+233 244 600 811',
                'batch_number' => 'BD-491099',
                'expiry_date' => now()->addMonths(11)->toDateString(),
                'storage_condition' => 'Room Temp',
                'location_bin' => 'Phlebotomy Store B4',
            ],
            [
                'sku' => 'KIT-PARA-005',
                'name' => 'SD Bioline Malaria Ag P.f/Pan Rapid Test Kit (25 Tests)',
                'category' => 'kit',
                'unit' => 'Box',
                'branch_id' => $accraBranch->id,
                'quantity_on_hand' => 18,
                'reorder_level' => 5,
                'unit_cost' => 210.00,
                'supplier_name' => 'Abbott Rapid Diagnostics GH',
                'supplier_contact' => '+233 302 814 200',
                'batch_number' => 'SDB-2026-77',
                'expiry_date' => now()->addMonths(6)->toDateString(),
                'storage_condition' => '2-30°C',
                'location_bin' => 'Parasitology Cabinet C1',
            ],
            [
                'sku' => 'REG-KMS-006',
                'name' => 'Cobas Lipid Panel Calibrator & Control Set',
                'category' => 'calibrator',
                'unit' => 'Kit',
                'branch_id' => $kumasiBranch->id,
                'quantity_on_hand' => 7,
                'reorder_level' => 3,
                'unit_cost' => 540.00,
                'supplier_name' => 'Roche Diagnostics West Africa',
                'supplier_contact' => '+233 302 761 090',
                'batch_number' => 'LOT-KMS-339',
                'expiry_date' => now()->addMonths(5)->toDateString(),
                'storage_condition' => '2-8°C',
                'location_bin' => 'Kumasi Cold Fridge 1',
            ],
        ];

        foreach ($inventorySeed as $inv) {
            $item = InventoryItem::create($inv);
            StockMovement::create([
                'inventory_item_id' => $item->id,
                'branch_id' => $item->branch_id,
                'movement_type' => 'stock_in',
                'quantity' => $item->quantity_on_hand,
                'previous_quantity' => 0,
                'new_quantity' => $item->quantity_on_hand,
                'reference_number' => 'GRN-2026-' . $item->id,
                'reason' => 'Opening verified laboratory stock balance',
                'performed_by' => $adminUser->id,
            ]);
        }

        // 10. System Notifications & Audit Logs
        SystemNotification::create([
            'branch_id' => $accraBranch->id,
            'type' => 'critical',
            'category' => 'Critical Result',
            'title' => 'CRITICAL HIGH GLUCOSE (24.6 mmol/L) — ORD-20261107-0002',
            'message' => 'Fasting Plasma Glucose for Nii Armah Vanderpuye (LV-2026-0003) recorded at 24.6 mmol/L (Critical High > 22.0). Awaiting immediate supervisor verification.',
            'action_route' => 'approvals',
            'reference_id' => 'ORD-20261107-0002',
            'is_read' => false,
        ]);

        SystemNotification::create([
            'branch_id' => $accraBranch->id,
            'type' => 'warning',
            'category' => 'Approval',
            'title' => 'Positive Malaria Microscopy Submitted — ORD-20261107-0003',
            'message' => 'Trophozoites of P. falciparum (+) submitted for Adwoa Mensima-Korsah. Ready for supervisor sign-off.',
            'action_route' => 'approvals',
            'reference_id' => 'ORD-20261107-0003',
            'is_read' => false,
        ]);

        SystemNotification::create([
            'branch_id' => $accraBranch->id,
            'type' => 'warning',
            'category' => 'Inventory',
            'title' => 'Low Stock & Near Expiry: Cobas Creatinine Cassette',
            'message' => 'REG-CHEM-002 has 3 cassettes remaining (Reorder level: 6) and expires in 28 days.',
            'action_route' => 'inventory',
            'reference_id' => 'REG-CHEM-002',
            'is_read' => false,
        ]);

        AuditLog::create([
            'user_id' => $receptionistUser->id,
            'branch_id' => $accraBranch->id,
            'action' => 'order.created',
            'module' => 'Orders',
            'target_type' => 'LabOrder',
            'target_id' => $order4->id,
            'target_reference' => $order4->order_number,
            'description' => "Placed laboratory order {$order4->order_number} for Esi Arhinful-Quansah (LFT-01, HBS-01).",
            'ip_address' => '192.168.10.14',
        ]);

        AuditLog::create([
            'user_id' => $scientistUser->id,
            'branch_id' => $accraBranch->id,
            'action' => 'result.submitted',
            'module' => 'Results',
            'target_type' => 'LabOrderItem',
            'target_id' => $item2Fbs->id,
            'target_reference' => $order2->order_number,
            'description' => "Submitted CRITICAL result (24.6 mmol/L) for Fasting Blood Glucose on {$order2->order_number}.",
            'ip_address' => '192.168.10.22',
        ]);

        AuditLog::create([
            'user_id' => $supervisorUser->id,
            'branch_id' => $accraBranch->id,
            'action' => 'report.released',
            'module' => 'Reports',
            'target_type' => 'LabReport',
            'target_id' => 1,
            'target_reference' => 'RPT-20261107-0001',
            'description' => 'Approved results and released official diagnostic report RPT-20261107-0001 for Kofi Agyeman-Prempeh.',
            'ip_address' => '192.168.10.5',
        ]);

        // Seed default SMTP & SMS settings if not already set
        foreach (CommunicationService::defaultSettings() as $k => $v) {
            SystemSetting::firstOrCreate(
                ['key' => $k],
                ['value' => (string) $v, 'group' => str_starts_with($k, 'smtp_') ? 'smtp' : (str_starts_with($k, 'sms_') ? 'sms' : 'notifications')]
            );
        }

        // Seed initial SMS & Email communication outbox history
        if (Schema::hasTable('communication_logs')) {
            $pat1 = $patients['LV-2026-0001'];
            $pat3 = $patients['LV-2026-0003'];

            CommunicationLog::create([
                'branch_id' => $accraBranch->id,
                'patient_id' => $pat1->id,
                'triggered_by' => $supervisorUser->id,
                'channel' => 'sms',
                'provider' => 'sandbox',
                'event_type' => 'report.released',
                'recipient' => $pat1->phone,
                'recipient_name' => $pat1->full_name,
                'subject' => null,
                'message' => "Hello {$pat1->full_name}, your official medical laboratory report RPT-20261107-0001 (Order {$order1->order_number}) is ready at LABVERSE DIAGNOSTIC & REFERENCE LABORATORIES LTD.",
                'reference_type' => 'LabReport',
                'reference_code' => 'RPT-20261107-0001',
                'status' => 'simulated',
                'provider_response' => 'Simulated SMS dispatch (Sender: LABVERSE).',
                'sent_at' => now()->subMinutes(15),
            ]);

            CommunicationLog::create([
                'branch_id' => $accraBranch->id,
                'patient_id' => $pat1->id,
                'triggered_by' => $supervisorUser->id,
                'channel' => 'email',
                'provider' => 'sandbox',
                'event_type' => 'report.released',
                'recipient' => $pat1->email ?: 'kofi.prempeh@example.com',
                'recipient_name' => $pat1->full_name,
                'subject' => 'Official Medical Laboratory Report Ready — RPT-20261107-0001',
                'message' => "Dear {$pat1->full_name}, Your official diagnostic laboratory report RPT-20261107-0001 has been verified and released.",
                'reference_type' => 'LabReport',
                'reference_code' => 'RPT-20261107-0001',
                'status' => 'simulated',
                'provider_response' => 'Captured in LABVERSE Mail Sandbox.',
                'sent_at' => now()->subMinutes(15),
            ]);

            CommunicationLog::create([
                'branch_id' => $accraBranch->id,
                'patient_id' => $pat3->id,
                'triggered_by' => $scientistUser->id,
                'channel' => 'sms',
                'provider' => 'sandbox',
                'event_type' => 'result.critical',
                'recipient' => '+233302784100',
                'recipient_name' => $pat3->full_name,
                'subject' => null,
                'message' => "URGENT CLINICAL ALERT: Critical diagnostic value (Glucose 24.6 mmol/L) detected for {$pat3->full_name} ({$pat3->patient_code}). Immediate review required.",
                'reference_type' => 'LabOrder',
                'reference_code' => $order2->order_number,
                'status' => 'simulated',
                'provider_response' => 'Critical Value Escalation SMS dispatched.',
                'sent_at' => now()->subMinutes(8),
            ]);
        }
    }
}
