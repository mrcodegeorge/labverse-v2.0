<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\EnforcesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\LabOrder;
use App\Models\Payment;
use App\Services\AuditService;
use App\Services\CommunicationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    use EnforcesBranchAccess;

    public function index(Request $request): JsonResponse
    {
        $branchId = $this->resolveEffectiveBranchId($request);

        $query = Payment::with([
            'patient:id,patient_code,first_name,last_name,other_names,phone',
            'order:id,order_number,total_amount,amount_paid,balance_due,payment_status',
            'branch:id,code,name,city',
            'receivedByUser:id,name,staff_id',
        ]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($sub) use ($search) {
                $sub->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhere('transaction_reference', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('patient', function ($p) use ($search) {
                        $p->where('patient_code', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($method = $request->input('payment_method')) {
            $query->where('payment_method', $method);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);
        $payments = $query->latest('paid_at')->paginate($perPage);

        // Financial summaries
        $payBase = Payment::where('status', 'completed')->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $orderBase = LabOrder::where('status', '!=', 'cancelled')->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $outstandingInvoices = (clone $orderBase)
            ->where('balance_due', '>', 0)
            ->with(['patient:id,patient_code,first_name,last_name,phone', 'branch:id,code,name'])
            ->latest()
            ->limit(25)
            ->get();

        $byMethod = (clone $payBase)
            ->select('payment_method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'payments' => $payments,
            'outstanding_orders' => $outstandingInvoices,
            'summary' => [
                'collected_today' => (float) (clone $payBase)->whereDate('paid_at', Carbon::today())->sum('amount'),
                'collected_total' => (float) (clone $payBase)->sum('amount'),
                'total_invoiced' => (float) (clone $orderBase)->sum('total_amount'),
                'total_outstanding' => (float) (clone $orderBase)->sum('balance_due'),
                'outstanding_orders_count' => (clone $orderBase)->where('balance_due', '>', 0)->count(),
                'by_method' => $byMethod,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lab_order_id' => ['required', 'exists:lab_orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,mtn_momo,telecel_cash,at_money,card,bank_transfer,nhis'],
            'transaction_reference' => ['nullable', 'string', 'max:80'],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'payer_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order = LabOrder::with('patient')->findOrFail($validated['lab_order_id']);
        $this->authorizeBranchRecord($request, $order->branch_id);

        if ($order->balance_due <= 0) {
            return response()->json([
                'message' => "Order {$order->order_number} is already paid in full.",
            ], 422);
        }

        $payAmount = min((float) $validated['amount'], (float) $order->balance_due);
        $user = $request->user();

        return DB::transaction(function () use ($request, $validated, $order, $payAmount, $user) {
            $nextSeq = (Payment::max('id') ?? 0) + 1;
            $payment = Payment::create([
                'receipt_number' => sprintf('RCP-%s-%04d', date('Ymd'), $nextSeq),
                'invoice_number' => sprintf('INV-%s-%04d', date('Ymd'), $order->id),
                'lab_order_id' => $order->id,
                'patient_id' => $order->patient_id,
                'branch_id' => $order->branch_id,
                'amount' => $payAmount,
                'payment_method' => $validated['payment_method'],
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'payer_name' => $validated['payer_name'] ?? $order->patient?->full_name,
                'payer_phone' => $validated['payer_phone'] ?? $order->patient?->phone,
                'status' => 'completed',
                'received_by' => $user->id,
                'paid_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            $newPaid = round($order->amount_paid + $payAmount, 2);
            $newBalance = max(round($order->total_amount - $newPaid, 2), 0);
            $newStatus = $newBalance <= 0 ? 'paid' : 'partial';

            $order->update([
                'amount_paid' => $newPaid,
                'balance_due' => $newBalance,
                'payment_status' => $newStatus,
            ]);

            AuditService::log(
                $request,
                'payment.recorded',
                'Payments',
                "Recorded payment {$payment->receipt_number} of GHS " . number_format($payAmount, 2) . " via {$payment->payment_method} for order {$order->order_number}.",
                'Payment',
                $payment->id,
                $payment->receipt_number,
                ['order_number' => $order->order_number, 'method' => $payment->payment_method],
                $order->branch_id
            );

            $payment->load(['patient', 'order.items.test', 'branch', 'receivedByUser:id,name,staff_id']);

            CommunicationService::notifyPaymentReceipt($payment, $user->id);

            return response()->json([
                'message' => 'Payment recorded and receipt generated successfully.',
                'payment' => $payment,
                'order' => $order,
            ], 201);
        });
    }

    public function notifyReceipt(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeBranchRecord($request, $payment->branch_id);

        $validated = $request->validate([
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:sms,email'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $logs = CommunicationService::notifyPaymentReceipt(
            $payment,
            $request->user()?->id,
            $validated['channels'],
            $validated['email'] ?? null,
            $validated['phone'] ?? null
        );

        return response()->json([
            'message' => count($logs) > 0
                ? 'Payment receipt dispatched (' . collect($logs)->map(fn ($l) => strtoupper($l->channel) . ': ' . $l->status)->implode(', ') . ').'
                : 'No recipient contact details found for the selected channel(s).',
            'logs' => $logs,
        ]);
    }

    public function showReceipt(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeBranchRecord($request, $payment->branch_id);

        $payment->load([
            'patient',
            'order.items.test.category',
            'branch',
            'receivedByUser:id,name,staff_id,job_title',
        ]);

        return response()->json([
            'payment' => $payment,
        ]);
    }
}

