<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\EnforcesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\LabOrder;
use App\Models\LabReport;
use App\Models\SystemSetting;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    use EnforcesBranchAccess;

    public function index(Request $request): JsonResponse
    {
        $query = LabReport::with([
            'patient:id,patient_code,first_name,last_name,other_names,gender,date_of_birth,phone',
            'order:id,order_number,priority,payment_status,balance_due,referring_doctor,created_at',
            'order.items.test:id,code,name,category_id',
            'order.items.test.category:id,name',
            'branch:id,code,name,city,phone,email,address',
            'approvedByUser:id,name,staff_id,job_title,license_number',
        ]);

        $this->scopeBranch($query, $request);

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($sub) use ($search) {
                $sub->where('report_number', 'like', "%{$search}%")
                    ->orWhere('verification_code', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('patient', function ($p) use ($search) {
                        $p->where('patient_code', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);
        $reports = $query->latest('released_at')->paginate($perPage);

        return response()->json($reports);
    }

    public function show(Request $request, LabReport $labReport): JsonResponse
    {
        $this->authorizeBranchRecord($request, $labReport->branch_id);

        $labReport->load([
            'patient',
            'branch',
            'order.orderedByUser:id,name,staff_id,job_title',
            'order.samples.collectedByUser:id,name,staff_id',
            'order.samples.acceptedByUser:id,name,staff_id',
            'order.items' => fn ($q) => $q->with([
                'test.category',
                'test.parameters',
                'sample',
                'results.parameter',
                'enteredByUser:id,name,staff_id,job_title,license_number',
                'approvedByUser:id,name,staff_id,job_title,license_number',
            ]),
            'generatedByUser:id,name,staff_id,job_title',
            'approvedByUser:id,name,staff_id,job_title,license_number',
        ]);

        // Enforce that a report cannot be released if any test item is not approved
        $unapprovedCount = $labReport->order->items->where('status', '!=', 'approved')->count();
        if ($unapprovedCount > 0) {
            return response()->json([
                'message' => 'This report cannot be released because one or more ordered tests have not yet received supervisor approval.',
            ], 422);
        }

        $labReport->increment('download_count');

        $facilitySettings = SystemSetting::pluck('value', 'key');

        AuditService::log(
            $request,
            'report.viewed',
            'Reports',
            "Accessed medical laboratory report {$labReport->report_number} for {$labReport->patient?->full_name}.",
            'LabReport',
            $labReport->id,
            $labReport->report_number,
            null,
            $labReport->branch_id
        );

        return response()->json([
            'report' => $labReport,
            'facility' => $facilitySettings,
        ]);
    }

    public function generateFromOrder(Request $request, LabOrder $labOrder): JsonResponse
    {
        $this->authorizeBranchRecord($request, $labOrder->branch_id);

        $labOrder->load(['items', 'patient', 'branch']);

        $unapproved = $labOrder->items->where('status', '!=', 'approved')->count();
        if ($unapproved > 0) {
            return response()->json([
                'message' => "Cannot generate or release report: {$unapproved} test(s) on order {$labOrder->order_number} are still awaiting approval.",
            ], 422);
        }

        $validated = $request->validate([
            'pathologist_comment' => ['nullable', 'string', 'max:1000'],
            'clinical_interpretation' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $nextSeq = (LabReport::max('id') ?? 0) + 1;

        $report = LabReport::updateOrCreate(
            ['lab_order_id' => $labOrder->id],
            [
                'report_number' => $labOrder->report?->report_number ?? sprintf('RPT-%s-%04d', date('Ymd'), $nextSeq),
                'patient_id' => $labOrder->patient_id,
                'branch_id' => $labOrder->branch_id,
                'status' => 'released',
                'verification_code' => $labOrder->report?->verification_code ?? strtoupper('LV-VER-' . Str::random(10)),
                'pathologist_comment' => $validated['pathologist_comment'] ?? 'Verified and released in accordance with ISO 15189 clinical laboratory standards.',
                'clinical_interpretation' => $validated['clinical_interpretation'] ?? null,
                'generated_by' => $user->id,
                'approved_by' => $user->id,
                'released_at' => now(),
            ]
        );

        AuditService::log(
            $request,
            'report.released',
            'Reports',
            "Generated and released report {$report->report_number} for order {$labOrder->order_number}.",
            'LabReport',
            $report->id,
            $report->report_number,
            null,
            $labOrder->branch_id
        );

        return $this->show($request, $report);
    }
}

