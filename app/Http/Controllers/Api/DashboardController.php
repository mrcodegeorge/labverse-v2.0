<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\EnforcesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabReport;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Sample;
use App\Models\SystemNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use EnforcesBranchAccess;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $this->resolveEffectiveBranchId($request);
        $dateFilter = $request->input('date', Carbon::today()->toDateString());
        $rangeDays = (int) $request->input('range_days', 7);
        $targetDate = Carbon::parse($dateFilter);

        $patientQuery = Patient::query();
        $orderQuery = LabOrder::query();
        $sampleQuery = Sample::query();
        $reportQuery = LabReport::query();
        $paymentQuery = Payment::query();

        if ($branchId) {
            $patientQuery->where('branch_id', $branchId);
            $orderQuery->where('branch_id', $branchId);
            $sampleQuery->where('branch_id', $branchId);
            $reportQuery->where('branch_id', $branchId);
            $paymentQuery->where('branch_id', $branchId);
        }

        // Core KPIs
        $totalPatientsToday = (clone $patientQuery)->whereDate('created_at', $targetDate)->count();
        $totalPatientsAll = (clone $patientQuery)->count();

        $totalOrdersToday = (clone $orderQuery)->whereDate('created_at', $targetDate)->count();
        $totalOrdersAll = (clone $orderQuery)->count();

        $pendingSamples = (clone $sampleQuery)->whereIn('status', ['awaiting_collection', 'collected', 'received'])->count();
        $samplesProcessing = (clone $sampleQuery)->whereIn('status', ['accepted', 'processing'])->count();

        $orderIdsInScope = (clone $orderQuery)->pluck('id');

        $pendingResults = LabOrderItem::whereIn('lab_order_id', $orderIdsInScope)
            ->whereIn('status', ['awaiting_processing', 'in_progress', 'draft_saved', 'correction_requested'])
            ->count();

        $awaitingApproval = LabOrderItem::whereIn('lab_order_id', $orderIdsInScope)
            ->where('status', 'submitted')
            ->count();

        $releasedReports = (clone $reportQuery)->where('status', 'released')->count();

        $outstandingOrders = (clone $orderQuery)
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0);

        $outstandingPaymentsTotal = (float) (clone $outstandingOrders)->sum('balance_due');
        $outstandingPaymentsCount = (clone $outstandingOrders)->count();

        $revenueToday = (float) (clone $paymentQuery)
            ->where('status', 'completed')
            ->whereDate('paid_at', $targetDate)
            ->sum('amount');

        // A. Laboratory Workload by Category
        $workloadByCategory = LabOrderItem::whereIn('lab_order_id', $orderIdsInScope)
            ->with('test.category')
            ->get()
            ->groupBy(fn ($item) => $item->test?->category?->name ?? 'General')
            ->map(function ($items, $categoryName) {
                return [
                    'category' => $categoryName,
                    'total' => $items->count(),
                    'completed' => $items->where('status', 'approved')->count(),
                    'in_progress' => $items->whereIn('status', ['awaiting_processing', 'in_progress', 'draft_saved', 'submitted'])->count(),
                    'pending_sample' => $items->where('status', 'pending_sample')->count(),
                ];
            })
            ->values();

        // B. Patient Registration & Order Trends (last N days)
        $trends = [];
        for ($i = $rangeDays - 1; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $dayStr = $day->toDateString();

            $pCount = Patient::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->whereDate('created_at', $dayStr)
                ->count();

            $oCount = LabOrder::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->whereDate('created_at', $dayStr)
                ->count();

            $trends[] = [
                'date' => $dayStr,
                'label' => $day->format('D, d M'),
                'patients' => $pCount,
                'orders' => $oCount,
            ];
        }

        // C. Recent Patient Registrations
        $recentPatients = (clone $patientQuery)
            ->with('branch:id,code,name')
            ->latest()
            ->limit(6)
            ->get();

        // D. Pending Laboratory Tasks (Priority sorted)
        $pendingLabTasks = LabOrderItem::whereIn('lab_order_id', $orderIdsInScope)
            ->whereIn('status', ['awaiting_processing', 'in_progress', 'submitted', 'correction_requested'])
            ->with([
                'order:id,order_number,priority,patient_id,branch_id,created_at',
                'order.patient:id,patient_code,first_name,last_name,other_names,gender,date_of_birth',
                'test:id,code,name,category_id,turnaround_hours',
                'test.category:id,name',
                'sample:id,sample_code,barcode,status',
            ])
            ->latest()
            ->limit(8)
            ->get();

        // E. Outstanding Payments List
        $outstandingOrdersList = (clone $outstandingOrders)
            ->with(['patient:id,patient_code,first_name,last_name,phone', 'branch:id,code,name'])
            ->latest()
            ->limit(6)
            ->get();

        // F. Recent Audit Activities
        $recentActivities = AuditLog::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['user:id,name,staff_id,job_title', 'branch:id,code,name'])
            ->latest()
            ->limit(8)
            ->get();

        // G. Notifications & Critical Alerts
        $criticalAlerts = SystemNotification::when($branchId, fn ($q) => $q->where(function ($sub) use ($branchId) {
                $sub->where('branch_id', $branchId)->orWhereNull('branch_id');
            }))
            ->latest()
            ->limit(8)
            ->get();

        // Low stock count for inventory alert
        $lowStockCount = InventoryItem::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->count();

        // H. Branch Performance (for Platform Admin or users with manage_branches)
        $branchPerformance = [];
        if ($user->is_platform_admin || $user->hasPermission('manage_branches')) {
            $branchPerformance = Branch::where('is_active', true)
                ->get()
                ->map(function (Branch $branch) {
                    $bOrderIds = LabOrder::where('branch_id', $branch->id)->pluck('id');
                    return [
                        'id' => $branch->id,
                        'code' => $branch->code,
                        'name' => $branch->name,
                        'city' => $branch->city,
                        'patients_count' => Patient::where('branch_id', $branch->id)->count(),
                        'orders_count' => $bOrderIds->count(),
                        'pending_samples' => Sample::where('branch_id', $branch->id)->whereIn('status', ['awaiting_collection', 'collected', 'received'])->count(),
                        'awaiting_approval' => LabOrderItem::whereIn('lab_order_id', $bOrderIds)->where('status', 'submitted')->count(),
                        'released_reports' => LabReport::where('branch_id', $branch->id)->where('status', 'released')->count(),
                        'revenue_collected' => (float) Payment::where('branch_id', $branch->id)->where('status', 'completed')->sum('amount'),
                        'outstanding_balance' => (float) LabOrder::where('branch_id', $branch->id)->where('status', '!=', 'cancelled')->sum('balance_due'),
                    ];
                });
        }

        return response()->json([
            'filters' => [
                'branch_id' => $branchId,
                'date' => $targetDate->toDateString(),
                'range_days' => $rangeDays,
            ],
            'kpis' => [
                'patients_today' => $totalPatientsToday,
                'patients_total' => $totalPatientsAll,
                'orders_today' => $totalOrdersToday,
                'orders_total' => $totalOrdersAll,
                'pending_samples' => $pendingSamples,
                'samples_processing' => $samplesProcessing,
                'pending_results' => $pendingResults,
                'awaiting_approval' => $awaitingApproval,
                'released_reports' => $releasedReports,
                'outstanding_payments_amount' => $outstandingPaymentsTotal,
                'outstanding_payments_count' => $outstandingPaymentsCount,
                'revenue_today' => $revenueToday,
                'low_stock_items' => $lowStockCount,
            ],
            'workload_by_category' => $workloadByCategory,
            'registration_trends' => $trends,
            'recent_patients' => $recentPatients,
            'pending_lab_tasks' => $pendingLabTasks,
            'outstanding_orders' => $outstandingOrdersList,
            'recent_activities' => $recentActivities,
            'notifications' => $criticalAlerts,
            'branch_performance' => $branchPerformance,
        ]);
    }

    public function globalSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([
                'patients' => [],
                'orders' => [],
                'samples' => [],
                'reports' => [],
            ]);
        }

        $branchId = $this->resolveEffectiveBranchId($request);

        $patients = Patient::when($branchId, fn ($b) => $b->where('branch_id', $branchId))
            ->where(function ($sub) use ($q) {
                $sub->where('patient_code', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get(['id', 'patient_code', 'first_name', 'last_name', 'other_names', 'gender', 'date_of_birth', 'phone']);

        $orders = LabOrder::when($branchId, fn ($b) => $b->where('branch_id', $branchId))
            ->with('patient:id,patient_code,first_name,last_name')
            ->where('order_number', 'like', "%{$q}%")
            ->limit(5)
            ->get(['id', 'order_number', 'patient_id', 'status', 'payment_status', 'total_amount']);

        $samples = Sample::when($branchId, fn ($b) => $b->where('branch_id', $branchId))
            ->with('patient:id,patient_code,first_name,last_name')
            ->where(function ($sub) use ($q) {
                $sub->where('sample_code', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get(['id', 'sample_code', 'barcode', 'sample_type', 'status', 'patient_id']);

        $reports = LabReport::when($branchId, fn ($b) => $b->where('branch_id', $branchId))
            ->with('patient:id,patient_code,first_name,last_name')
            ->where('report_number', 'like', "%{$q}%")
            ->limit(5)
            ->get(['id', 'report_number', 'lab_order_id', 'patient_id', 'status', 'released_at']);

        return response()->json([
            'patients' => $patients,
            'orders' => $orders,
            'samples' => $samples,
            'reports' => $reports,
        ]);
    }
}

