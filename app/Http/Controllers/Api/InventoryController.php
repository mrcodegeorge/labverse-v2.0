<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\EnforcesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    use EnforcesBranchAccess;

    public function index(Request $request): JsonResponse
    {
        $branchId = $this->resolveEffectiveBranchId($request);

        $query = InventoryItem::with('branch:id,code,name');
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($sub) use ($search) {
                $sub->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('batch_number', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($statusFilter = $request->input('stock_status')) {
            if ($statusFilter === 'low_stock') {
                $query->whereColumn('quantity_on_hand', '<=', 'reorder_level')->where('quantity_on_hand', '>', 0);
            } elseif ($statusFilter === 'out_of_stock') {
                $query->where('quantity_on_hand', '<=', 0);
            } elseif ($statusFilter === 'expiring_soon') {
                $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', Carbon::now()->addDays(45));
            }
        }

        $items = $query->orderBy('name')->get();

        $movements = StockMovement::with([
            'item:id,sku,name,unit',
            'branch:id,code,name',
            'performedByUser:id,name,staff_id',
        ])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest()
            ->limit(25)
            ->get();

        $allScoped = InventoryItem::when($branchId, fn ($q) => $q->where('branch_id', $branchId))->get();

        return response()->json([
            'items' => $items,
            'movements' => $movements,
            'summary' => [
                'total_items' => $allScoped->count(),
                'reagents_count' => $allScoped->where('category', 'reagent')->count(),
                'consumables_count' => $allScoped->whereIn('category', ['consumable', 'collection_tube', 'kit'])->count(),
                'low_stock_count' => $allScoped->filter(fn ($i) => $i->quantity_on_hand <= $i->reorder_level && $i->quantity_on_hand > 0)->count(),
                'out_of_stock_count' => $allScoped->filter(fn ($i) => $i->quantity_on_hand <= 0)->count(),
                'expiring_soon_count' => $allScoped->filter(fn ($i) => $i->is_expiring_soon)->count(),
                'total_valuation' => round($allScoped->sum(fn ($i) => $i->quantity_on_hand * $i->unit_cost), 2),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:35', 'unique:inventory_items,sku'],
            'name' => ['required', 'string', 'max:180'],
            'category' => ['required', 'in:reagent,consumable,calibrator,control,collection_tube,kit'],
            'unit' => ['required', 'string', 'max:30'],
            'quantity_on_hand' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'supplier_contact' => ['nullable', 'string', 'max:100'],
            'batch_number' => ['nullable', 'string', 'max:60'],
            'expiry_date' => ['nullable', 'date'],
            'storage_condition' => ['nullable', 'string', 'max:60'],
            'location_bin' => ['nullable', 'string', 'max:60'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $user = $request->user();
        $branchId = $this->resolveEffectiveBranchId($request, isset($validated['branch_id']) ? (int) $validated['branch_id'] : null)
            ?? $user->branch_id
            ?? 1;

        $item = InventoryItem::create([
            ...$validated,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        if ($item->quantity_on_hand > 0) {
            StockMovement::create([
                'inventory_item_id' => $item->id,
                'branch_id' => $branchId,
                'movement_type' => 'stock_in',
                'quantity' => $item->quantity_on_hand,
                'previous_quantity' => 0,
                'new_quantity' => $item->quantity_on_hand,
                'reference_number' => 'INIT-' . $item->sku,
                'reason' => 'Initial stock registration',
                'performed_by' => $user->id,
            ]);
        }

        AuditService::log(
            $request,
            'inventory.item_created',
            'Inventory',
            "Added inventory item {$item->name} ({$item->sku}) with opening stock {$item->quantity_on_hand} {$item->unit}.",
            'InventoryItem',
            $item->id,
            $item->sku,
            null,
            $branchId
        );

        return response()->json([
            'message' => 'Inventory reagent/consumable registered successfully.',
            'item' => $item->load('branch:id,code,name'),
        ], 201);
    }

    public function adjustStock(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        $this->authorizeBranchRecord($request, $inventoryItem->branch_id);

        $validated = $request->validate([
            'movement_type' => ['required', 'in:stock_in,consumption,adjustment,expired_writeoff'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:200'],
            'reference_number' => ['nullable', 'string', 'max:60'],
            'batch_number' => ['nullable', 'string', 'max:60'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $prevQty = (float) $inventoryItem->quantity_on_hand;
        $qty = (float) $validated['quantity'];
        $type = $validated['movement_type'];

        if (in_array($type, ['consumption', 'expired_writeoff']) && $qty > $prevQty) {
            return response()->json([
                'message' => "Cannot deduct {$qty} {$inventoryItem->unit}: only {$prevQty} {$inventoryItem->unit} available in stock.",
            ], 422);
        }

        $newQty = match ($type) {
            'stock_in' => $prevQty + $qty,
            'consumption', 'expired_writeoff' => max($prevQty - $qty, 0),
            'adjustment' => $qty, // Direct count adjustment
        };

        return DB::transaction(function () use ($request, $inventoryItem, $validated, $user, $prevQty, $qty, $newQty, $type) {
            $inventoryItem->update([
                'quantity_on_hand' => $newQty,
                'batch_number' => $validated['batch_number'] ?? $inventoryItem->batch_number,
                'expiry_date' => $validated['expiry_date'] ?? $inventoryItem->expiry_date,
            ]);

            $movement = StockMovement::create([
                'inventory_item_id' => $inventoryItem->id,
                'branch_id' => $inventoryItem->branch_id,
                'movement_type' => $type,
                'quantity' => $qty,
                'previous_quantity' => $prevQty,
                'new_quantity' => $newQty,
                'reference_number' => $validated['reference_number'] ?? ('MOV-' . date('YmdHis')),
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'performed_by' => $user->id,
            ]);

            if ($newQty <= $inventoryItem->reorder_level) {
                AuditService::notify(
                    "Low Stock Alert: {$inventoryItem->name}",
                    "{$inventoryItem->name} ({$inventoryItem->sku}) is at {$newQty} {$inventoryItem->unit} (Reorder level: {$inventoryItem->reorder_level}).",
                    'warning',
                    'Inventory',
                    $inventoryItem->branch_id,
                    'inventory',
                    $inventoryItem->sku
                );
            }

            AuditService::log(
                $request,
                "inventory.{$type}",
                'Inventory',
                "Recorded {$type} for {$inventoryItem->name} ({$inventoryItem->sku}): {$prevQty} -> {$newQty} {$inventoryItem->unit}. Reason: {$validated['reason']}.",
                'InventoryItem',
                $inventoryItem->id,
                $inventoryItem->sku,
                null,
                $inventoryItem->branch_id
            );

            return response()->json([
                'message' => 'Stock movement recorded and inventory updated.',
                'item' => $inventoryItem->load('branch:id,code,name'),
                'movement' => $movement,
            ]);
        });
    }
}

