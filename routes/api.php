<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LabOrderController;
use App\Http\Controllers\Api\LabWorkspaceController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SampleController;
use App\Http\Controllers\Api\TestCatalogueController;
use Illuminate\Support\Facades\Route;

// Public Authentication & Demo Accounts Discovery
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/demo-accounts', [AuthController::class, 'demoAccounts']);

// Protected Sanctum API Routes
Route::middleware('auth:sanctum')->group(function () {
    // Current Authenticated Staff Profile
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Global Search & Notifications
    Route::get('/search', [DashboardController::class, 'globalSearch']);
    Route::get('/notifications', [AdminController::class, 'notifications']);
    Route::post('/notifications/{notification}/read', [AdminController::class, 'markNotificationRead']);
    Route::post('/notifications/read-all', [AdminController::class, 'markAllNotificationsRead']);

    // Operational Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:view_dashboard');

    // Patient Management
    Route::get('/patients', [PatientController::class, 'index'])
        ->middleware('permission:view_patients|manage_patients|place_order');
    Route::get('/patients/check-duplicate', [PatientController::class, 'checkDuplicate'])
        ->middleware('permission:manage_patients|place_order');
    Route::post('/patients', [PatientController::class, 'store'])
        ->middleware('permission:manage_patients');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])
        ->middleware('permission:view_patients|manage_patients|place_order|enter_results|approve_result');
    Route::put('/patients/{patient}', [PatientController::class, 'update'])
        ->middleware('permission:manage_patients');

    // Test Catalogue (Read for ordering/lab, Write for catalogue managers)
    Route::get('/catalogue/categories', [TestCatalogueController::class, 'categories']);
    Route::get('/catalogue/tests', [TestCatalogueController::class, 'index']);
    Route::post('/catalogue/tests', [TestCatalogueController::class, 'store'])
        ->middleware('permission:manage_catalogue');
    Route::put('/catalogue/tests/{labTest}', [TestCatalogueController::class, 'update'])
        ->middleware('permission:manage_catalogue');

    // Laboratory Test Orders
    Route::get('/orders', [LabOrderController::class, 'index'])
        ->middleware('permission:view_orders|place_order|collect_sample|enter_results|approve_result|manage_payments');
    Route::post('/orders', [LabOrderController::class, 'store'])
        ->middleware('permission:place_order');
    Route::get('/orders/{labOrder}', [LabOrderController::class, 'show'])
        ->middleware('permission:view_orders|place_order|collect_sample|enter_results|approve_result|manage_payments');

    // Sample Collection & Specimen Lifecycle Management
    Route::get('/samples', [SampleController::class, 'index'])
        ->middleware('permission:collect_sample|receive_sample|manage_samples|enter_results');
    Route::get('/samples/lookup', [SampleController::class, 'lookupByBarcode'])
        ->middleware('permission:collect_sample|receive_sample|manage_samples|enter_results');
    Route::post('/samples/{sample}/status', [SampleController::class, 'updateStatus'])
        ->middleware('permission:collect_sample|receive_sample|manage_samples');

    // Laboratory Workspace, Result Entry & Result Approvals
    Route::get('/lab/items', [LabWorkspaceController::class, 'index'])
        ->middleware('permission:view_lab_workspace|enter_results|approve_result');
    Route::get('/lab/items/{labOrderItem}', [LabWorkspaceController::class, 'showItem'])
        ->middleware('permission:view_lab_workspace|enter_results|approve_result');
    Route::post('/lab/items/{labOrderItem}/results', [LabWorkspaceController::class, 'saveResults'])
        ->middleware('permission:enter_results');
    Route::post('/lab/items/{labOrderItem}/review', [LabWorkspaceController::class, 'reviewResult'])
        ->middleware('permission:approve_result');

    // Medical Laboratory Reports
    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:view_reports|release_report|approve_result');
    Route::get('/reports/{labReport}', [ReportController::class, 'show'])
        ->middleware('permission:view_reports|release_report|approve_result');
    Route::post('/orders/{labOrder}/report', [ReportController::class, 'generateFromOrder'])
        ->middleware('permission:release_report|approve_result');
    Route::post('/reports/{labReport}/notify', [ReportController::class, 'notifyPatient'])
        ->middleware('permission:view_reports|release_report|approve_result');

    // Financial & Payment Management
    Route::get('/payments', [PaymentController::class, 'index'])
        ->middleware('permission:view_payments|manage_payments');
    Route::post('/payments', [PaymentController::class, 'store'])
        ->middleware('permission:manage_payments');
    Route::get('/payments/{payment}', [PaymentController::class, 'showReceipt'])
        ->middleware('permission:view_payments|manage_payments');
    Route::post('/payments/{payment}/notify', [PaymentController::class, 'notifyReceipt'])
        ->middleware('permission:view_payments|manage_payments');

    // Inventory & Stock Management
    Route::get('/inventory', [InventoryController::class, 'index'])
        ->middleware('permission:view_inventory|manage_inventory');
    Route::post('/inventory', [InventoryController::class, 'store'])
        ->middleware('permission:manage_inventory');
    Route::post('/inventory/{inventoryItem}/adjust', [InventoryController::class, 'adjustStock'])
        ->middleware('permission:manage_inventory');

    // Multi-Branch Management
    Route::get('/branches', [AdminController::class, 'branches']);
    Route::post('/branches', [AdminController::class, 'storeBranch'])
        ->middleware('permission:manage_branches');
    Route::put('/branches/{branch}', [AdminController::class, 'updateBranch'])
        ->middleware('permission:manage_branches');

    // Staff & Custom RBAC Administration
    Route::get('/staff', [AdminController::class, 'staff'])
        ->middleware('permission:manage_users|manage_roles');
    Route::post('/staff', [AdminController::class, 'storeStaff'])
        ->middleware('permission:manage_users');
    Route::put('/staff/{user}', [AdminController::class, 'updateStaff'])
        ->middleware('permission:manage_users');
    Route::post('/roles', [AdminController::class, 'storeRole'])
        ->middleware('permission:manage_roles|manage_users');
    Route::put('/roles/{role}/permissions', [AdminController::class, 'updateRolePermissions'])
        ->middleware('permission:manage_roles|manage_users');

    // Audit Logs, System Settings & SMTP/SMS Communications
    Route::get('/audit-logs', [AdminController::class, 'auditLogs'])
        ->middleware('permission:manage_users|manage_settings');
    Route::get('/settings', [AdminController::class, 'settings']);
    Route::put('/settings', [AdminController::class, 'updateSettings'])
        ->middleware('permission:manage_settings');

    Route::get('/communications/logs', [AdminController::class, 'communicationLogs'])
        ->middleware('permission:manage_settings|manage_users|view_reports');
    Route::post('/communications/test-email', [AdminController::class, 'sendTestEmail'])
        ->middleware('permission:manage_settings');
    Route::post('/communications/test-sms', [AdminController::class, 'sendTestSms'])
        ->middleware('permission:manage_settings');
    Route::post('/communications/logs/{communicationLog}/retry', [AdminController::class, 'retryCommunication'])
        ->middleware('permission:manage_settings|view_reports');
});
