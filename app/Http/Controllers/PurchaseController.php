<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Product;
use App\Models\Batch;
use App\Models\PurchaseOrder;
use App\Models\LowStockItem;
use App\Models\Notification;
use App\Events\NewNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PurchaseController extends Controller
{
    /**
     * Send Notification – SAME AS ALL OTHER CONTROLLERS
     */
    private function notify(Request $request, string $type, string $title, string $message, array $replacements = [])
    {
        $user = $request->user();

        $performerName = $user?->name ?? $request->header('X-User-Name', 'Unknown User');
        $performerId   = $user?->id ?? $request->header('X-User-ID', 1);

        $default = [
            'performer_name' => $performerName,
            'performer_id'   => $performerId,
            'timestamp'      => Carbon::now()->format('d M Y, h:i A'),
        ];

        $replacements = array_merge($default, $replacements);

        foreach ($replacements as $key => $value) {
            $message = str_replace("{{{$key}}}", $value, $message);
        }

        $notification = Notification::create([
            'user_id' => $performerId,
            'type'    => $type,
            'data'    => [
                'title'   => $title,
                'message' => $message,
                'icon'    => match ($type) {
                    'purchase_created' => 'Plus',
                    'purchase_updated' => 'Edit',
                    'purchase_deleted' => 'Trash',
                    'purchase_viewed'  => 'Eye',
                    'purchase_list'    => 'List',
                    default            => 'Bell'
                },
            ],
            'is_read' => false,
        ]);

        broadcast(new NewNotification($notification));
    }

    // Get all purchases
    public function index(Request $request)
    {
        $purchases = Purchase::with(['supplier', 'items.product'])->get();

        return $purchases;
    }

    // Get purchase by ID
    public function show($id, Request $request)
    {
        $purchase = Purchase::with(['supplier', 'items.product'])->find($id);
        if (!$purchase) {
            return response()->json(['message' => 'Purchase not found'], 404);
        }

        return $purchase;
    }

    // Create purchase with multiple items
    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'party_name' => 'required|string',
            'po_number' => 'required|string',
            'invoice_number' => 'required|string',
            'receiving_date' => 'nullable|date',
            'vehicle_number' => 'nullable|string',
            'product_description' => 'nullable|string',
            'goods_category' => 'nullable|string',
            'rate' => 'required|numeric',
            'cgst' => 'required|numeric|min:0',
            'sgst' => 'required|numeric|min:0',
            'igst' => 'required|numeric|min:0',
            'round_off' => 'nullable|numeric',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.buy_price' => 'required|numeric',
            'items.*.description' => 'nullable|string',
        ]);

        $purchase = Purchase::create(collect($data)->except('items')->toArray());
        $itemCount = count($data['items']);

        foreach ($data['items'] as $item) {
            $purchase->items()->create($item);

            $product = Product::with('suppliers')->find($item['product_id']);

            if (!$product->suppliers->contains($data['supplier_id'])) {
                $product->suppliers()->attach($data['supplier_id']);
            }

            if (empty($product->supplier_id) || $product->supplier_id != $data['supplier_id']) {
                $product->update(['supplier_id' => $data['supplier_id']]);
            }

            $batch = Batch::where('product_id', $item['product_id'])
                ->orderBy('created_at', 'desc')
                ->first();

            if ($batch) {
                $batch->increment('stock_level', $item['quantity']);
                $batch->update([
                    'status' => $batch->stock_level == 0
                        ? 'Out of Stock'
                        : ($batch->stock_level < ($product->max_stock * 0.2) ? 'Low Stock' : 'In Stock'),
                ]);
            } else {
                Batch::create([
                    'product_id' => $item['product_id'],
                    'batch_number' => 'BATCH-' . strtoupper(Str::random(6)),
                    'stock_level' => $item['quantity'],
                    'expiry_date' => now()->addYears(2),
                    'status' => $item['quantity'] < ($product->max_stock * 0.2) ? 'Low Stock' : 'In Stock',
                ]);
            }

            LowStockItem::where('product_id', $item['product_id'])
                ->where('supplier_id', $data['supplier_id'])
                ->delete();
        }

        $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
        if ($purchaseOrder) {
            $purchaseOrder->update(['status' => 'Completed']);
        }

        $this->notify(
            $request,
            'purchase_created',
            'Purchase Created',
            '{{performer_name}} created purchase #{{invoice}} ({{items}} items) from {{supplier}} at {{timestamp}}.',
            [
                'invoice'  => $purchase->invoice_number,
                'items'    => $itemCount,
                'supplier' => $purchase->supplier?->name ?? 'Unknown'
            ]
        );

        return response()->json([
            'message' => 'Purchase created successfully',
            'purchase' => $purchase->load(['supplier', 'items.product']),
        ], 201);
    }

    // Update purchase + items
    public function update(Request $request, $id)
    {
        $purchase = Purchase::find($id);
        if (!$purchase) {
            return response()->json(['message' => 'Purchase not found'], 404);
        }

        $data = $request->validate([
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'party_name' => 'sometimes|string',
            'po_number' => 'sometimes|string',
            'invoice_number' => 'sometimes|string',
            'receiving_date' => 'nullable|date',
            'vehicle_number' => 'nullable|string',
            'product_description' => 'nullable|string',
            'goods_category' => 'nullable|string',
            'rate' => 'sometimes|numeric',
            'cgst' => 'sometimes|numeric|min:0',
            'sgst' => 'sometimes|numeric|min:0',
            'igst' => 'sometimes|numeric|min:0',
            'round_off' => 'nullable|numeric',

            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.buy_price' => 'required_with:items|numeric',
            'items.*.description' => 'nullable|string',
        ]);

        $changes = [];

        $oldInvoice = $purchase->invoice_number;
        $oldSupplier = $purchase->supplier?->name ?? 'Unknown';

        $purchase->update(collect($data)->except('items')->toArray());

        if (!empty($data['items'])) {
            $oldCount = $purchase->items()->count();
            $newCount = count($data['items']);
            $purchase->items()->delete();
            foreach ($data['items'] as $item) {
                $purchase->items()->create($item);
            }
            $changes[] = "items: $oldCount to $newCount";
        }

        if ($purchase->wasChanged('invoice_number')) {
            $changes[] = "invoice: '$oldInvoice' to '{$purchase->invoice_number}'";
        }
        if ($purchase->wasChanged('supplier_id')) {
            $newSupplier = $purchase->supplier?->name ?? 'Unknown';
            $changes[] = "supplier: '$oldSupplier' to '$newSupplier'";
        }

        if (empty($changes)) {
            $changes[] = "minor updates";
        }

        $this->notify(
            $request,
            'purchase_updated',
            'Purchase Updated',
            '{{performer_name}} updated purchase #{{invoice}}: ' . implode(' | ', $changes) . ' at {{timestamp}}.',
            [
                'invoice' => $purchase->invoice_number
            ]
        );

        return response()->json([
            'message' => 'Purchase updated successfully',
            'purchase' => $purchase->load(['supplier', 'items.product']),
        ]);
    }

    // Delete purchase
    public function destroy($id, Request $request)
    {
        $purchase = Purchase::find($id);
        if (!$purchase) {
            return response()->json(['message' => 'Purchase not found'], 404);
        }

        $invoice = $purchase->invoice_number;
        $supplier = $purchase->supplier?->name ?? 'Unknown';

        $purchase->delete();

        $this->notify(
            $request,
            'purchase_deleted',
            'Purchase Deleted',
            '{{performer_name}} deleted purchase #{{invoice}} from {{supplier}} at {{timestamp}}.',
            [
                'invoice'  => $invoice,
                'supplier' => $supplier
            ]
        );

        return response()->json(['message' => 'Purchase deleted successfully']);
    }
}