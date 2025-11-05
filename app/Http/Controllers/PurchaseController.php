<?php
namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Product;
use App\Models\Batch;
use App\Models\PurchaseOrder;
use App\Models\LowStockItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    // ✅ Get all purchases
    public function index()
    {
        return Purchase::with(['supplier', 'items.product'])->get();
    }

    // ✅ Get purchase by ID
    public function show($id)
    {
        $purchase = Purchase::with(['supplier', 'items.product'])->find($id);
        if (!$purchase) {
            return response()->json(['message' => 'Purchase not found'], 404);
        }
        return $purchase;
    }

    // ✅ Create purchase with multiple items
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

            // Items
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.buy_price' => 'required|numeric',
            'items.*.description' => 'nullable|string',
        ]);

        // ✅ Create purchase
        $purchase = Purchase::create(collect($data)->except('items')->toArray());

        // ✅ Handle each purchase item
        foreach ($data['items'] as $item) {
            $purchase->items()->create($item);

            $product = Product::with('suppliers')->find($item['product_id']);

            // 🔹 Pivot relation - multiple suppliers ke liye
            if (!$product->suppliers->contains($data['supplier_id'])) {
                $product->suppliers()->attach($data['supplier_id']);
            }

            // 🔹 Update main supplier_id in products table
            if (empty($product->supplier_id) || $product->supplier_id != $data['supplier_id']) {
                $product->update(['supplier_id' => $data['supplier_id']]);
            }

            // 🔹 Update product quantity via batch
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

            // 🔹 Remove from LowStockItem
            LowStockItem::where('product_id', $item['product_id'])
                ->where('supplier_id', $data['supplier_id'])
                ->delete();
        }

        // ✅ Update PO status
        $purchaseOrder = PurchaseOrder::where('po_number', $data['po_number'])->first();
        if ($purchaseOrder) {
            $purchaseOrder->update(['status' => 'Completed']);
        }

        return response()->json([
            'message' => 'Purchase created successfully',
            'purchase' => $purchase->load(['supplier', 'items.product']),
        ], 201);
    }

    // ✅ Update purchase + items
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

            // Items
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.buy_price' => 'required_with:items|numeric',
            'items.*.description' => 'nullable|string',
        ]);

        // ✅ Update purchase fields
        $purchase->update(collect($data)->except('items')->toArray());

        // ✅ If items are provided, replace old ones
        if (!empty($data['items'])) {
            $purchase->items()->delete(); // delete old items
            foreach ($data['items'] as $item) {
                $purchase->items()->create($item);
            }
        }

        return response()->json([
            'message' => 'Purchase updated successfully',
            'purchase' => $purchase->load(['supplier', 'items.product']),
        ]);
    }

    // ✅ Delete purchase
    public function destroy($id)
    {
        $purchase = Purchase::find($id);
        if (!$purchase) {
            return response()->json(['message' => 'Purchase not found'], 404);
        }

        $purchase->delete();

        return response()->json(['message' => 'Purchase deleted successfully']);
    }
}
