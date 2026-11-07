<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\EnforcesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabReport;
use App\Models\TestParameter;
use App\Models\TestResult;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LabWorkspaceController extends Controller
{
    use EnforcesBranchAccess;

    public function index(Request $request): JsonResponse
    {
        $branchId = $this->resolveEffectiveBranchId($request);

        $query = LabOrderItem::with([
            'order:id,order_number,priority,clinical_notes,diagnosis,referring_doctor,patient_id,branch_id,created_at',
            'order.patient:id,patient_code,first_name,last_name,other_names,gender,date_of_birth,phone,blood_group',
            'order.branch:id,code,name',
            'test:id,code,name,category_id,sample_type,turnaround_hours,methodology',
            'test.category:id,code,name',
            'test.parameters',
            'sample:id,sample_code,barcode,sample_type,container_type,status,collected_at,accepted_at,storage_location',
            'assignedToUser:id,name,staff_id',
            'enteredByUser:id,name,staff_id',
            'approvedByUser:id,name,staff_id',
            'results.parameter',
        ]);

        if ($branchId) {
            $query->whereHas('order', fn ($o) => $o->where('branch_id', $branchId));
        }

        if ($status = $request->input('status')) {
            if ($status === 'queue') {
                $query->whereIn('status', ['awaiting_processing', 'in_progress', 'draft_saved', 'correction_requested']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($categoryId = $request->input('category_id')) {
            $query->whereHas('test', fn ($t) => $t->where('category_id', $categoryId));
        }

        if ($priority = $request->input('priority')) {
            $query->whereHas('order', fn ($o) => $o->where('priority', $priority));
        }

        if ($assignedTo = $request->input('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($sub) use ($search) {
                $sub->whereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('sample', fn ($s) => $s->where('sample_code', 'like', "%{$search}%")->orWhere('barcode', 'like', "%{$search}%"))
                    ->orWhereHas('test', fn ($t) => $t->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('order.patient', function ($p) use ($search) {
                        $p->where('patient_code', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min(max((int) $request->input('per_page', 20), 5), 100);
        $items = $query->latest()->paginate($perPage);

        // Compute queue summary metrics
        $baseScope = LabOrderItem::query();
        if ($branchId) {
            $baseScope->whereHas('order', fn ($o) => $o->where('branch_id', $branchId));
        }

        $allScoped = (clone $baseScope)->with(['order:id,priority,created_at', 'test:id,turnaround_hours'])->get();
        $delayedCount = $allScoped->filter(function ($item) {
            if (in_array($item->status, ['approved', 'submitted'])) {
                return false;
            }
            $tatHours = $item->test?->turnaround_hours ?? 4;
            return $item->created_at && Carbon::parse($item->created_at)->addHours($tatHours)->isPast();
        })->count();

        $summary = [
            'pending_sample' => $allScoped->where('status', 'pending_sample')->count(),
            'awaiting_processing' => $allScoped->where('status', 'awaiting_processing')->count(),
            'in_progress' => $allScoped->whereIn('status', ['in_progress', 'draft_saved'])->count(),
            'correction_requested' => $allScoped->where('status', 'correction_requested')->count(),
            'submitted' => $allScoped->where('status', 'submitted')->count(),
            'approved' => $allScoped->where('status', 'approved')->count(),
            'urgent_or_stat' => $allScoped->filter(fn ($i) => in_array($i->order?->priority, ['urgent', 'stat']) && $i->status !== 'approved')->count(),
            'delayed' => $delayedCount,
        ];

        return response()->json([
            'items' => $items,
            'summary' => $summary,
        ]);
    }

    public function showItem(Request $request, LabOrderItem $labOrderItem): JsonResponse
    {
        $labOrderItem->load([
            'order.patient',
            'order.branch',
            'order.orderedByUser:id,name,staff_id',
            'test.category',
            'test.parameters',
            'sample.collectedByUser:id,name,staff_id',
            'sample.acceptedByUser:id,name,staff_id',
            'enteredByUser:id,name,staff_id,job_title',
            'approvedByUser:id,name,staff_id,job_title',
            'rejectedByUser:id,name,staff_id',
            'results.parameter',
        ]);

        $this->authorizeBranchRecord($request, $labOrderItem->order?->branch_id);

        // Fetch previous historical results for this patient and test if available
        $previousItems = LabOrderItem::where('lab_test_id', $labOrderItem->lab_test_id)
            ->where('id', '!=', $labOrderItem->id)
            ->where('status', 'approved')
            ->whereHas('order', fn ($o) => $o->where('patient_id', $labOrderItem->order->patient_id))
            ->with(['order:id,order_number,created_at', 'results.parameter'])
            ->latest()
            ->limit(3)
            ->get();

        return response()->json([
            'item' => $labOrderItem,
            'previous_results' => $previousItems,
        ]);
    }

    public function saveResults(Request $request, LabOrderItem $labOrderItem): JsonResponse
    {
        $labOrderItem->load(['order.patient', 'test.parameters', 'sample']);
        $this->authorizeBranchRecord($request, $labOrderItem->order?->branch_id);

        if ($labOrderItem->status === 'approved') {
            return response()->json([
                'message' => 'Approved results are locked and cannot be modified without an amended report workflow.',
            ], 422);
        }

        if ($labOrderItem->sample && !in_array($labOrderItem->sample->status, ['accepted', 'processing', 'completed'])) {
            return response()->json([
                'message' => 'Sample must be accepted by the laboratory before entering or submitting test results.',
            ], 422);
        }

        $validated = $request->validate([
            'mode' => ['required', 'in:draft,submit,repeat'],
            'technician_notes' => ['nullable', 'string', 'max:1000'],
            'results' => ['required', 'array', 'min:1'],
            'results.*.test_parameter_id' => ['required', 'exists:test_parameters,id'],
            'results.*.result_value' => ['nullable', 'string', 'max:500'],
            'results.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $mode = $validated['mode'];
        $isDraft = $mode === 'draft';
        $gender = strtolower($labOrderItem->order?->patient?->gender ?? 'male');

        return DB::transaction(function () use ($request, $labOrderItem, $validated, $user, $mode, $isDraft, $gender) {
            $hasCritical = false;
            $hasAbnormal = false;

            foreach ($validated['results'] as $entry) {
                $param = TestParameter::findOrFail($entry['test_parameter_id']);
                $rawVal = isset($entry['result_value']) ? trim((string) $entry['result_value']) : '';

                if (!$isDraft && $param->is_mandatory && $rawVal === '') {
                    abort(422, "Parameter '{$param->name}' is required before submitting for review.");
                }

                $numericVal = is_numeric($rawVal) ? (float) $rawVal : null;
                $flag = $this->evaluateParameterFlag($param, $rawVal, $numericVal, $gender);
                $refString = $this->formatReferenceInterval($param, $gender);

                if (in_array($flag, ['critical_low', 'critical_high'])) {
                    $hasCritical = true;
                    $hasAbnormal = true;
                } elseif (in_array($flag, ['low', 'high', 'abnormal'])) {
                    $hasAbnormal = true;
                }

                TestResult::updateOrCreate(
                    [
                        'lab_order_item_id' => $labOrderItem->id,
                        'test_parameter_id' => $param->id,
                    ],
                    [
                        'result_value' => $rawVal,
                        'numeric_value' => $numericVal,
                        'unit' => $param->unit,
                        'reference_range' => $refString,
                        'flag' => $rawVal === '' ? 'pending' : $flag,
                        'is_draft' => $isDraft,
                        'remarks' => $entry['remarks'] ?? null,
                        'entered_by' => $user->id,
                        'updated_by' => $user->id,
                    ]
                );
            }

            // Never automatically approve a result when saved or submitted!
            $newStatus = $isDraft ? 'draft_saved' : 'submitted';

            $labOrderItem->update([
                'status' => $newStatus,
                'entered_by' => $user->id,
                'assigned_to' => $labOrderItem->assigned_to ?? $user->id,
                'started_at' => $labOrderItem->started_at ?? now(),
                'submitted_at' => $isDraft ? $labOrderItem->submitted_at : now(),
                'technician_notes' => $validated['technician_notes'] ?? $labOrderItem->technician_notes,
                'has_critical_values' => $hasCritical,
                'has_abnormal_values' => $hasAbnormal,
                'repeat_count' => $mode === 'repeat' ? $labOrderItem->repeat_count + 1 : $labOrderItem->repeat_count,
            ]);

            if ($labOrderItem->sample && $labOrderItem->sample->status === 'accepted') {
                $labOrderItem->sample->update(['status' => 'processing']);
            }

            $this->syncOrderState($labOrderItem->order);

            if (!$isDraft && $hasCritical) {
                AuditService::notify(
                    "CRITICAL VALUE ALERT: {$labOrderItem->test?->name}",
                    "Critical laboratory result recorded for patient {$labOrderItem->order?->patient?->full_name} ({$labOrderItem->order?->order_number}). Immediate supervisor verification required.",
                    'critical',
                    'Critical Result',
                    $labOrderItem->order?->branch_id,
                    'approvals',
                    $labOrderItem->order?->order_number
                );
            }

            AuditService::log(
                $request,
                $isDraft ? 'result.draft_saved' : 'result.submitted',
                'Results',
                ($isDraft ? 'Saved draft results' : 'Submitted results for supervisor approval') .
                " for {$labOrderItem->test?->name} ({$labOrderItem->order?->order_number}).",
                'LabOrderItem',
                $labOrderItem->id,
                $labOrderItem->order?->order_number,
                ['has_critical' => $hasCritical, 'has_abnormal' => $hasAbnormal],
                $labOrderItem->order?->branch_id
            );

            $labOrderItem->load([
                'order.patient',
                'test.category',
                'test.parameters',
                'sample',
                'results.parameter',
                'enteredByUser:id,name,staff_id',
            ]);

            return response()->json([
                'message' => $isDraft
                    ? 'Draft results saved successfully.'
                    : 'Results submitted for supervisor verification and approval.',
                'item' => $labOrderItem,
            ]);
        });
    }

    public function reviewResult(Request $request, LabOrderItem $labOrderItem): JsonResponse
    {
        $labOrderItem->load(['order.patient', 'test', 'sample', 'results']);
        $this->authorizeBranchRecord($request, $labOrderItem->order?->branch_id);

        if ($labOrderItem->status !== 'submitted') {
            return response()->json([
                'message' => 'Only submitted results awaiting review can be approved or rejected.',
            ], 422);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject,request_correction'],
            'reviewer_comments' => ['nullable', 'required_if:decision,reject,request_correction', 'string', 'max:1000'],
            'pathologist_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $decision = $validated['decision'];

        // Independent verification check for non-platform-admin staff if same user entered critical result
        if (
            $decision === 'approve' &&
            !$user->is_platform_admin &&
            $labOrderItem->has_critical_values &&
            $labOrderItem->entered_by === $user->id &&
            $request->boolean('enforce_dual_signoff', false)
        ) {
            return response()->json([
                'message' => 'Clinical Policy: Critical results require independent verification by a second authorized supervisor.',
            ], 422);
        }

        return DB::transaction(function () use ($request, $labOrderItem, $validated, $user, $decision) {
            if ($decision === 'approve') {
                $labOrderItem->update([
                    'status' => 'approved',
                    'review_decision' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'reviewer_comments' => $validated['reviewer_comments'] ?? 'Verified and approved for clinical release.',
                ]);

                $labOrderItem->results()->update(['is_draft' => false]);

                // If all items on this sample are approved, mark sample completed
                if ($labOrderItem->sample) {
                    $remainingOnSample = $labOrderItem->sample->orderItems()
                        ->where('status', '!=', 'approved')
                        ->count();
                    if ($remainingOnSample === 0) {
                        $labOrderItem->sample->update(['status' => 'completed']);
                    }
                }
            } elseif ($decision === 'reject') {
                $labOrderItem->update([
                    'status' => 'awaiting_processing',
                    'review_decision' => 'rejected',
                    'rejected_by' => $user->id,
                    'rejected_at' => now(),
                    'reviewer_comments' => $validated['reviewer_comments'],
                ]);
                $labOrderItem->results()->update(['is_draft' => true]);
            } else {
                // request_correction
                $labOrderItem->update([
                    'status' => 'correction_requested',
                    'review_decision' => 'correction_requested',
                    'rejected_by' => $user->id,
                    'rejected_at' => now(),
                    'reviewer_comments' => $validated['reviewer_comments'],
                ]);
                $labOrderItem->results()->update(['is_draft' => true]);
            }

            $this->syncOrderState($labOrderItem->order, $user->id, $validated['pathologist_comment'] ?? null);

            AuditService::log(
                $request,
                "result.{$decision}",
                'Approvals',
                "Supervisor {$user->name} recorded decision '{$decision}' on {$labOrderItem->test?->name} ({$labOrderItem->order?->order_number}).",
                'LabOrderItem',
                $labOrderItem->id,
                $labOrderItem->order?->order_number,
                ['decision' => $decision, 'comments' => $validated['reviewer_comments'] ?? null],
                $labOrderItem->order?->branch_id
            );

            $labOrderItem->load([
                'order.patient',
                'order.report',
                'test.category',
                'sample',
                'results.parameter',
                'enteredByUser:id,name,staff_id',
                'approvedByUser:id,name,staff_id',
            ]);

            return response()->json([
                'message' => match ($decision) {
                    'approve' => 'Test results approved and verified.',
                    'reject' => 'Test results rejected and returned to laboratory queue.',
                    default => 'Correction requested from laboratory scientist.',
                },
                'item' => $labOrderItem,
            ]);
        });
    }

    protected function evaluateParameterFlag(TestParameter $param, string $rawVal, ?float $numericVal, string $gender): string
    {
        if ($rawVal === '') {
            return 'pending';
        }

        if ($param->input_type === 'numeric' && $numericVal !== null) {
            if ($param->critical_low !== null && $numericVal <= $param->critical_low) {
                return 'critical_low';
            }
            if ($param->critical_high !== null && $numericVal >= $param->critical_high) {
                return 'critical_high';
            }

            $min = $gender === 'female'
                ? ($param->ref_min_female ?? $param->ref_min_male)
                : ($param->ref_min_male ?? $param->ref_min_female);

            $max = $gender === 'female'
                ? ($param->ref_max_female ?? $param->ref_max_male)
                : ($param->ref_max_male ?? $param->ref_max_female);

            if ($min !== null && $numericVal < $min) {
                return 'low';
            }
            if ($max !== null && $numericVal > $max) {
                return 'high';
            }
            return 'normal';
        }

        // Qualitative / select evaluation
        $lower = strtolower($rawVal);
        if (
            str_contains($lower, 'positive') ||
            str_contains($lower, 'reactive') && !str_contains($lower, 'non-reactive') ||
            str_contains($lower, 'detected') && !str_contains($lower, 'not detected') ||
            str_contains($lower, 'seen') && !str_contains($lower, 'none seen') && !str_contains($lower, 'not seen') ||
            str_contains($lower, 'resistant')
        ) {
            return 'abnormal';
        }

        return 'normal';
    }

    protected function formatReferenceInterval(TestParameter $param, string $gender): string
    {
        if ($param->reference_text) {
            return $param->reference_text;
        }

        $min = $gender === 'female'
            ? ($param->ref_min_female ?? $param->ref_min_male)
            : ($param->ref_min_male ?? $param->ref_min_female);

        $max = $gender === 'female'
            ? ($param->ref_max_female ?? $param->ref_max_male)
            : ($param->ref_max_male ?? $param->ref_max_female);

        if ($min !== null && $max !== null) {
            return rtrim(rtrim(number_format($min, 2), '0'), '.') . ' - ' . rtrim(rtrim(number_format($max, 2), '0'), '.');
        }

        return 'N/A';
    }

    protected function syncOrderState(?LabOrder $order, ?int $approverId = null, ?string $pathologistComment = null): void
    {
        if (!$order) {
            return;
        }

        $items = $order->items()->get();

        if ($items->isNotEmpty() && $items->every(fn ($i) => $i->status === 'approved')) {
            $order->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Automatically generate or update the authorized LabReport once ALL order items are approved
            $nextRepSeq = (LabReport::max('id') ?? 0) + 1;
            LabReport::updateOrCreate(
                ['lab_order_id' => $order->id],
                [
                    'report_number' => $order->report?->report_number ?? sprintf('RPT-%s-%04d', date('Ymd'), $nextRepSeq),
                    'patient_id' => $order->patient_id,
                    'branch_id' => $order->branch_id,
                    'status' => 'released',
                    'verification_code' => $order->report?->verification_code ?? strtoupper('LV-VER-' . Str::random(10)),
                    'pathologist_comment' => $pathologistComment ?? 'All diagnostic parameters verified according to clinical laboratory quality standards.',
                    'generated_by' => $approverId,
                    'approved_by' => $approverId,
                    'released_at' => now(),
                ]
            );
        } elseif ($items->contains(fn ($i) => $i->status === 'submitted')) {
            $order->update(['status' => 'awaiting_approval']);
        } elseif ($items->contains(fn ($i) => in_array($i->status, ['in_progress', 'draft_saved', 'correction_requested', 'awaiting_processing']))) {
            $order->update(['status' => 'processing']);
        }
    }
}

