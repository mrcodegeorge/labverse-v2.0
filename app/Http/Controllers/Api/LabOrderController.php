<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\EnforcesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Sample;
use App\Services\AuditService;
use App\Services\CommunicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LabOrderController extends Controller
{
    use EnforcesBranchAccess;

    public function index(Request $request): JsonResponse
    {
        $query = LabOrder::with([
            'patient:id,patient_code,first_name,last_name,other_names,gender,date_of_birth,phone',
            'branch:id,code,name',
            'orderedByUser:id,name,staff_id',
            'items.test:id,code,name,category_id,sample_type',
            'items.test.category:id,name',
            'samples:id,lab_order_id,sample_code,barcode,sample_type,status',
            'report:id,lab_order_id,report_number,status,released_at',
        ]);

        $this->scopeBranch($query, $request);

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($sub) use ($search) {
                $sub->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($p) use ($search) {
                        $p->where('patient_code', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($paymentStatus = $request->input('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        if ($patientId = $request->input('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);
        $orders = $query->latest()->paginate($perPage);

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'priority' => ['required', 'in:routine,urgent,stat'],
            'referring_doctor' => ['nullable', 'string', 'max:150'],
            'referring_facility' => ['nullable', 'string', 'max:150'],
            'clinical_notes' => ['nullable', 'string', 'max:1000'],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'test_ids' => ['required', 'array', 'min:1'],
            'test_ids.*' => ['required', 'exists:lab_tests,id'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:200'],
            'initial_payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:cash,mtn_momo,telecel_cash,at_money,card,bank_transfer,nhis'],
            'transaction_reference' => ['nullable', 'string', 'max:80'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $user = $request->user();
        $patient = Patient::findOrFail($validated['patient_id']);
        $this->authorizeBranchRecord($request, $patient->branch_id);

        $branchId = $this->resolveEffectiveBranchId($request, isset($validated['branch_id']) ? (int) $validated['branch_id'] : null)
            ?? $patient->branch_id
            ?? $user->branch_id
            ?? 1;

        $tests = LabTest::whereIn('id', $validated['test_ids'])->where('is_active', true)->get();
        if ($tests->isEmpty()) {
            return response()->json([
                'message' => 'None of the selected tests are active.',
            ], 422);
        }

        return DB::transaction(function () use ($request, $validated, $user, $patient, $branchId, $tests) {
            $subtotal = (float) $tests->sum('price');
            $discount = min((float) ($validated['discount_amount'] ?? 0), $subtotal);
            $totalAmount = max($subtotal - $discount, 0);
            $initialPaid = min((float) ($validated['initial_payment_amount'] ?? 0), $totalAmount);
            $balanceDue = max($totalAmount - $initialPaid, 0);

            $paymentStatus = 'unpaid';
            if ($initialPaid >= $totalAmount && $totalAmount > 0) {
                $paymentStatus = 'paid';
            } elseif ($initialPaid > 0) {
                $paymentStatus = 'partial';
            } elseif ($totalAmount == 0) {
                $paymentStatus = 'paid';
            }

            $nextOrderSeq = (LabOrder::max('id') ?? 0) + 1;
            $orderNumber = sprintf('ORD-%s-%04d', date('Ymd'), $nextOrderSeq);

            $order = LabOrder::create([
                'order_number' => $orderNumber,
                'patient_id' => $patient->id,
                'branch_id' => $branchId,
                'priority' => $validated['priority'],
                'referring_doctor' => $validated['referring_doctor'] ?? null,
                'referring_facility' => $validated['referring_facility'] ?? null,
                'clinical_notes' => $validated['clinical_notes'] ?? null,
                'diagnosis' => $validated['diagnosis'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'discount_reason' => $validated['discount_reason'] ?? null,
                'total_amount' => $totalAmount,
                'amount_paid' => $initialPaid,
                'balance_due' => $balanceDue,
                'payment_status' => $paymentStatus,
                'status' => 'ordered',
                'ordered_by' => $user->id,
                'ordered_at' => now(),
            ]);

            // Group tests by sample_type so each distinct specimen type gets a dedicated sample container + barcode
            $testsBySampleType = $tests->groupBy('sample_type');
            foreach ($testsBySampleType as $sampleType => $groupedTests) {
                $nextSampleSeq = (Sample::max('id') ?? 0) + 1;
                $sampleCode = sprintf('SMP-%s-%04d', date('Ymd'), $nextSampleSeq);
                $barcode = sprintf('LV%s%05d', date('ymd'), $nextSampleSeq);

                $firstTest = $groupedTests->first();

                $sample = Sample::create([
                    'sample_code' => $sampleCode,
                    'barcode' => $barcode,
                    'lab_order_id' => $order->id,
                    'patient_id' => $patient->id,
                    'branch_id' => $branchId,
                    'sample_type' => $sampleType,
                    'container_type' => $firstTest->container_type ?? 'Standard Specimen Tube',
                    'status' => 'awaiting_collection',
                ]);

                foreach ($groupedTests as $test) {
                    LabOrderItem::create([
                        'lab_order_id' => $order->id,
                        'lab_test_id' => $test->id,
                        'sample_id' => $sample->id,
                        'price' => $test->price,
                        'status' => 'pending_sample',
                    ]);
                }
            }

            // Record initial payment if provided
            if ($initialPaid > 0) {
                $nextPaySeq = (Payment::max('id') ?? 0) + 1;
                Payment::create([
                    'receipt_number' => sprintf('RCP-%s-%04d', date('Ymd'), $nextPaySeq),
                    'invoice_number' => sprintf('INV-%s-%04d', date('Ymd'), $order->id),
                    'lab_order_id' => $order->id,
                    'patient_id' => $patient->id,
                    'branch_id' => $branchId,
                    'amount' => $initialPaid,
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                    'payer_name' => $patient->full_name,
                    'payer_phone' => $patient->phone,
                    'status' => 'completed',
                    'received_by' => $user->id,
                    'paid_at' => now(),
                    'notes' => 'Payment recorded at order registration.',
                ]);
            }

            AuditService::log(
                $request,
                'order.created',
                'Orders',
                "Placed laboratory test order {$order->order_number} for {$patient->full_name} ({$tests->count()} tests, GHS " . number_format($totalAmount, 2) . ").",
                'LabOrder',
                $order->id,
                $order->order_number,
                ['test_codes' => $tests->pluck('code')->values()],
                $branchId
            );

            $order->load([
                'patient',
                'branch',
                'items.test.category',
                'samples',
                'payments',
            ]);

            CommunicationService::notifyOrderPlaced($order, $user->id);

            return response()->json([
                'message' => 'Laboratory test order placed successfully.',
                'order' => $order,
            ], 201);
        });
    }

    public function show(Request $request, LabOrder $labOrder): JsonResponse
    {
        $this->authorizeBranchRecord($request, $labOrder->branch_id);

        $labOrder->load([
            'patient',
            'branch',
            'orderedByUser:id,name,staff_id,job_title',
            'items.test.category',
            'items.test.parameters',
            'items.sample',
            'items.results.parameter',
            'items.enteredByUser:id,name,staff_id',
            'items.approvedByUser:id,name,staff_id',
            'samples.collectedByUser:id,name,staff_id',
            'samples.receivedByUser:id,name,staff_id',
            'samples.acceptedByUser:id,name,staff_id',
            'payments.receivedByUser:id,name,staff_id',
            'report.approvedByUser:id,name,staff_id',
        ]);

        return response()->json([
            'order' => $labOrder,
        ]);
    }
}

