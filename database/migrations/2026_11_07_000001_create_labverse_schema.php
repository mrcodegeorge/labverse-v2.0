<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Branches
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('manager_name')->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Extend Users for LIMS Staff & Multi-Branch RBAC
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_id', 30)->nullable()->unique()->after('id');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('job_title')->nullable()->after('phone');
            $table->string('department')->nullable()->after('job_title');
            $table->string('license_number', 60)->nullable()->after('department');
            $table->foreignId('branch_id')->nullable()->after('license_number')->constrained('branches')->nullOnDelete();
            $table->boolean('is_platform_admin')->default(false)->after('branch_id');
            $table->boolean('is_active')->default(true)->after('is_platform_admin');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        // 3. Custom RBAC: Roles & Permissions
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 60)->default('General');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['permission_id', 'role_id']);
        });

        // 4. Patients
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_code', 30)->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('other_names')->nullable();
            $table->date('date_of_birth');
            $table->string('gender', 20);
            $table->string('phone', 40);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('nationality')->default('Ghanaian');
            $table->string('national_id', 50)->nullable();
            $table->string('nhis_number', 50)->nullable();
            $table->string('blood_group', 10)->nullable();
            $table->string('genotype', 10)->nullable();
            $table->text('allergies')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('phone');
        });

        // 5. Test Catalogue: Categories, Tests, and Parameters
        Schema::create('test_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('department')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained('test_categories')->cascadeOnDelete();
            $table->string('sample_type', 60); // EDTA Whole Blood, Serum, Citrated Plasma, Urine, Stool, Swab, etc.
            $table->string('container_type', 80)->nullable(); // Purple Top (EDTA), Gold Top (SST), etc.
            $table->string('sample_volume', 40)->nullable(); // e.g. 3 mL
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('nhis_tariff', 10, 2)->nullable();
            $table->integer('turnaround_hours')->default(4);
            $table->string('methodology')->nullable();
            $table->text('clinical_indications')->nullable();
            $table->text('preparation_instructions')->nullable();
            $table->boolean('requires_fasting')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('test_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_test_id')->constrained('lab_tests')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('input_type', 20)->default('numeric'); // numeric, qualitative, text, select
            $table->string('unit', 40)->nullable();
            $table->decimal('ref_min_male', 12, 4)->nullable();
            $table->decimal('ref_max_male', 12, 4)->nullable();
            $table->decimal('ref_min_female', 12, 4)->nullable();
            $table->decimal('ref_max_female', 12, 4)->nullable();
            $table->decimal('critical_low', 12, 4)->nullable();
            $table->decimal('critical_high', 12, 4)->nullable();
            $table->string('reference_text')->nullable(); // e.g. "Non-Reactive", "4.0 - 11.0 x10^9/L"
            $table->json('options_json')->nullable(); // For select/qualitative options
            $table->integer('sort_order')->default(0);
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();
        });

        // 6. Laboratory Orders
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 35)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('priority', 20)->default('routine'); // routine, urgent, stat
            $table->string('referring_doctor')->nullable();
            $table->string('referring_facility')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->string('diagnosis')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);
            $table->string('payment_status', 20)->default('unpaid'); // unpaid, partial, paid
            $table->string('status', 30)->default('ordered'); // ordered, sample_collected, sample_received, processing, awaiting_approval, completed, cancelled
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'payment_status']);
        });

        // 7. Samples
        Schema::create('samples', function (Blueprint $table) {
            $table->id();
            $table->string('sample_code', 35)->unique();
            $table->string('barcode', 50)->unique();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('sample_type', 60);
            $table->string('container_type', 80)->nullable();
            $table->string('collection_site', 80)->nullable();
            $table->string('status', 30)->default('awaiting_collection');
            // awaiting_collection, collected, received, accepted, rejected, processing, completed
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->text('rejection_notes')->nullable();
            $table->string('storage_location', 80)->nullable(); // e.g., Fridge A - Rack 2 - Pos B4
            $table->string('condition_on_arrival', 60)->default('Good');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        // 8. Laboratory Order Items (Ordered Tests)
        Schema::create('lab_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained('lab_tests')->cascadeOnDelete();
            $table->foreignId('sample_id')->nullable()->constrained('samples')->nullOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status', 30)->default('pending_sample');
            // pending_sample, awaiting_processing, in_progress, draft_saved, submitted, approved, rejected, correction_requested
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('review_decision', 30)->nullable(); // approved, rejected, correction_requested
            $table->text('technician_notes')->nullable();
            $table->text('reviewer_comments')->nullable();
            $table->boolean('has_critical_values')->default(false);
            $table->boolean('has_abnormal_values')->default(false);
            $table->integer('repeat_count')->default(0);
            $table->timestamps();
        });

        // 9. Test Results (Parameter-level values)
        Schema::create('test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_item_id')->constrained('lab_order_items')->cascadeOnDelete();
            $table->foreignId('test_parameter_id')->constrained('test_parameters')->cascadeOnDelete();
            $table->string('result_value')->nullable();
            $table->decimal('numeric_value', 12, 4)->nullable();
            $table->string('unit', 40)->nullable();
            $table->string('reference_range', 120)->nullable();
            $table->string('flag', 20)->default('pending'); // normal, low, high, critical_low, critical_high, abnormal, pending
            $table->boolean('is_draft')->default(true);
            $table->text('remarks')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lab_order_item_id', 'test_parameter_id']);
        });

        // 10. Laboratory Reports
        Schema::create('lab_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number', 35)->unique();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('status', 25)->default('released'); // draft, released, amended
            $table->string('verification_code', 60)->unique();
            $table->text('pathologist_comment')->nullable();
            $table->text('clinical_interpretation')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->integer('download_count')->default(0);
            $table->timestamps();
        });

        // 11. Payments & Invoices
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 35)->unique();
            $table->string('invoice_number', 35);
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 35); // cash, mtn_momo, telecel_cash, at_money, card, bank_transfer, nhis
            $table->string('transaction_reference', 80)->nullable();
            $table->string('payer_name')->nullable();
            $table->string('payer_phone', 40)->nullable();
            $table->string('status', 20)->default('completed'); // completed, voided, refunded
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 12. Inventory & Stock Movements
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 35)->unique();
            $table->string('name');
            $table->string('category', 40); // reagent, consumable, calibrator, control, collection_tube, kit
            $table->string('unit', 30); // Box, Bottle, Pack, Vial, Kit, Pieces
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 12, 2)->default(0);
            $table->decimal('reorder_level', 12, 2)->default(10);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->string('supplier_name')->nullable();
            $table->string('supplier_contact')->nullable();
            $table->string('batch_number', 60)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('storage_condition', 60)->nullable(); // 2-8°C, Room Temp, -20°C
            $table->string('location_bin', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('movement_type', 30); // stock_in, consumption, adjustment, expired_writeoff, transfer
            $table->decimal('quantity', 12, 2);
            $table->decimal('previous_quantity', 12, 2);
            $table->decimal('new_quantity', 12, 2);
            $table->string('reference_number', 60)->nullable();
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 13. Audit Logs & Notifications & Facility Settings
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('action', 80); // e.g., patient.registered, order.created, result.entered, result.approved, report.released
            $table->string('module', 50); // Patients, Orders, Samples, Results, Approvals, Reports, Payments, Inventory, RBAC
            $table->string('target_type', 60)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_reference', 80)->nullable();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30)->default('info'); // critical, warning, info, success
            $table->string('category', 40)->default('Clinical'); // Critical Result, Approval, Sample, Inventory, Payment
            $table->string('title');
            $table->text('message');
            $table->string('action_route')->nullable();
            $table->string('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group', 40)->default('general');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('system_notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('lab_reports');
        Schema::dropIfExists('test_results');
        Schema::dropIfExists('lab_order_items');
        Schema::dropIfExists('samples');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('test_parameters');
        Schema::dropIfExists('lab_tests');
        Schema::dropIfExists('test_categories');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn([
                'staff_id', 'first_name', 'last_name', 'phone',
                'job_title', 'department', 'license_number',
                'is_platform_admin', 'is_active', 'last_login_at',
            ]);
        });
        Schema::dropIfExists('branches');
    }
};

