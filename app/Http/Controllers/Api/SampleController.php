<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\EnforcesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\LabOrder;
use App\Models\Sample;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SampleController extends Controller
{
    use EnforcesBranchAccess;

    public function index(Request $request): JsonResponse
    {
        $query = Sample::with([
            'patient:id,patient_code,first_name,last_name,other_names,gender,date_of_birth,phone',
            'order:id,order_number,priority,status,payment_status',
            'branch:id,code,name',
            'collectedByUser:id,name,staff_id',
            'receivedByUser:id,name,staff_id',
            'acceptedByUser:id,name,staff_id',
            'rejectedByUser:id,name,staff_id',
            'orderItems.test:id,code,name,category_id',
            'orderItems.test.category:id,name',
        ]);

        $this->scopeBranch($query, $request);

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($sub) use ($search) {
                $sub->where('sample_code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
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

        if ($sampleType = $request->input('sample_type')) {
            $query->where('sample_type', $sampleType);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);
        $samples = $query->latest()->paginate($perPage);

        // Summary counts by lifecycle stage
        $countQuery = Sample::query();
        $this->scopeBranch($countQuery, $request);

        $counts = [
            'awaiting_collection' => (clone $countQuery)->where('status', 'awaiting_collection')->count(),
            'collected' => (clone $countQuery)->where('status', 'collected')->count(),
            'received' => (clone $countQuery)->where('status', 'received')->count(),
            'accepted' => (clone $countQuery)->where('status', 'accepted')->count(),
            'rejected' => (clone $countQuery)->where('status', 'rejected')->count(),
            'processing' => (clone $countQuery)->where('status', 'processing')->count(),
            'completed' => (clone $countQuery)->where('status', 'completed')->count(),
        ];

        return response()->json([
            'samples' => $samples,
            'status_counts' => $counts,
        ]);
    }

    public function lookupByBarcode(Request $request): JsonResponse
    {
        $code = trim((string) $request->input('barcode', ''));
        if (!$code) {
            return response()->json(['message' => 'Please enter or scan a sample barcode.'], 422);
        }

        $sample = Sample::with([
            'patient',
            'order.items.test.category',
            'branch',
            'collectedByUser:id,name,staff_id',
            'receivedByUser:id,name,staff_id',
            'acceptedByUser:id,name,staff_id',
            'orderItems.test',
        ])
            ->where('barcode', $code)
            ->orWhere('sample_code', $code)
            ->first();

        if (!$sample) {
            return response()->json(['message' => "No sample found matching barcode '{$code}'."], 404);
        }

        $this->authorizeBranchRecord($request, $sample->branch_id);

        return response()->json([
            'sample' => $sample,
        ]);
    }

    public function updateStatus(Request $request, Sample $sample): JsonResponse
    {
        $this->authorizeBranchRecord($request, $sample->branch_id);

        $validated = $request->validate([
            'action' => ['required', 'in:collect,receive,accept,reject,recollect,update_location'],
            'collection_site' => ['nullable', 'string', 'max:80'],
            'container_type' => ['nullable', 'string', 'max:80'],
            'condition_on_arrival' => ['nullable', 'string', 'max:60'],
            'storage_location' => ['nullable', 'string', 'max:80'],
            'rejection_reason' => ['nullable', 'required_if:action,reject', 'string', 'max:180'],
            'rejection_notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $action = $validated['action'];

        return DB::transaction(function () use ($request, $sample, $validated, $user, $action) {
            if ($action === 'collect') {
                $sample->update([
                    'status' => 'collected',
                    'collected_by' => $user->id,
                    'collected_at' => now(),
                    'collection_site' => $validated['collection_site'] ?? $sample->collection_site ?? 'Venipuncture / Standard',
                    'container_type' => $validated['container_type'] ?? $sample->container_type,
                    'notes' => $validated['notes'] ?? $sample->notes,
                ]);
            } elseif ($action === 'receive') {
                if ($sample->status === 'awaiting_collection') {
                    $sample->collected_by = $sample->collected_by ?? $user->id;
                    $sample->collected_at = $sample->collected_at ?? now();
                }
                $sample->update([
                    'status' => 'received',
                    'received_by' => $user->id,
                    'received_at' => now(),
                    'condition_on_arrival' => $validated['condition_on_arrival'] ?? 'Good / Intact',
                    'storage_location' => $validated['storage_location'] ?? $sample->storage_location,
                    'notes' => $validated['notes'] ?? $sample->notes,
                ]);
            } elseif ($action === 'accept') {
                if (!$sample->collected_at) {
                    $sample->collected_by = $user->id;
                    $sample->collected_at = now();
                }
                if (!$sample->received_at) {
                    $sample->received_by = $user->id;
                    $sample->received_at = now();
                }
                $sample->update([
                    'status' => 'accepted',
                    'accepted_by' => $user->id,
                    'accepted_at' => now(),
                    'storage_location' => $validated['storage_location'] ?? $sample->storage_location ?? 'Main Lab Bench 1',
                    'condition_on_arrival' => $validated['condition_on_arrival'] ?? $sample->condition_on_arrival ?? 'Acceptable',
                    'rejection_reason' => null,
                    'rejection_notes' => null,
                    'notes' => $validated['notes'] ?? $sample->notes,
                ]);

                // Move associated order items to awaiting_processing so Lab Scientists see them in the work queue
                $sample->orderItems()
                    ->where('status', 'pending_sample')
                    ->update(['status' => 'awaiting_processing']);
            } elseif ($action === 'reject') {
                $sample->update([
                    'status' => 'rejected',
                    'rejected_by' => $user->id,
                    'rejected_at' => now(),
                    'rejection_reason' => $validated['rejection_reason'],
                    'rejection_notes' => $validated['rejection_notes'] ?? null,
                ]);

                $sample->orderItems()->update(['status' => 'pending_sample']);

                AuditService::notify(
                    "Sample Rejected: {$sample->sample_code}",
                    "Sample {$sample->sample_code} ({$sample->sample_type}) for order {$sample->order?->order_number} was rejected. Reason: {$validated['rejection_reason']}.",
                    'warning',
                    'Sample',
                    $sample->branch_id,
                    'samples',
                    $sample->sample_code
                );
            } elseif ($action === 'recollect') {
                $sample->update([
                    'status' => 'collected',
                    'collected_by' => $user->id,
                    'collected_at' => now(),
                    'rejection_reason' => null,
                    'rejection_notes' => null,
                    'notes' => 'Recollected after previous sample rejection.',
                ]);
            } elseif ($action === 'update_location') {
                $sample->update([
                    'storage_location' => $validated['storage_location'] ?? $sample->storage_location,
                    'notes' => $validated['notes'] ?? $sample->notes,
                ]);
            }

            $this->syncOrderLifecycle($sample->order);

            AuditService::log(
                $request,
                "sample.{$action}",
                'Samples',
                "Sample {$sample->sample_code} ({$sample->barcode}) updated via action '{$action}' -> status '{$sample->status}'.",
                'Sample',
                $sample->id,
                $sample->sample_code,
                ['status' => $sample->status, 'rejection_reason' => $sample->rejection_reason],
                $sample->branch_id
            );

            $sample->load([
                'patient',
                'order',
                'branch',
                'collectedByUser:id,name,staff_id',
                'receivedByUser:id,name,staff_id',
                'acceptedByUser:id,name,staff_id',
                'rejectedByUser:id,name,staff_id',
                'orderItems.test',
            ]);

            return response()->json([
                'message' => "Sample {$sample->sample_code} updated to '" . str_replace('_', ' ', $sample->status) . "'.",
                'sample' => $sample,
            ]);
        });
    }

    protected function syncOrderLifecycle(?LabOrder $order): void
    {
        if (!$order) {
            return;
        }

        $samples = $order->samples()->get();
        $items = $order->items()->get();

        if ($items->every(fn ($i) => $i->status === 'approved')) {
            $order->update(['status' => 'completed', 'completed_at' => now()]);
        } elseif ($items->contains(fn ($i) => $i->status === 'submitted')) {
            $order->update(['status' => 'awaiting_approval']);
        } elseif ($items->contains(fn ($i) => in_array($i->status, ['awaiting_processing', 'in_progress', 'draft_saved']))) {
            $order->update(['status' => 'processing']);
        } elseif ($samples->every(fn ($s) => in_array($s->status, ['collected', 'received', 'accepted', 'processing', 'completed']))) {
            $order->update(['status' => 'sample_collected']);
        }
    }
}

