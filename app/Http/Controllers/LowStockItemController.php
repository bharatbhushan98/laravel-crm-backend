<?php

namespace App\Http\Controllers;

use App\Models\LowStockItem;
use App\Services\LowStockItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\LowStockItemNotification;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use Illuminate\Support\Str;

class LowStockItemController extends Controller
{
    // GET all low stock items with supplier + product
    public function index()
    {
        try {
            $lowStockItems = LowStockItem::with(['supplier', 'product'])->get();
            return response()->json($lowStockItems, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch low stock items: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to fetch low stock items'], 500);
        }
    }

    // Manually create low stock item
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'supplier_id'   => 'required|exists:suppliers,id',
                'product_id'    => 'required|exists:products,id',
                'requested_qty' => 'required|integer|min:1',
                'buy_price'     => 'required|numeric|min:0',
            ]);

            $item = LowStockItem::create(array_merge($data, ['status' => 'Pending']));

            return response()->json([
                'message' => 'Low Stock Item created successfully',
                'item'    => $item->load(['supplier', 'product'])
            ], 201);
        } catch (\Exception $e) {
            Log::error("Failed to create low stock item: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to create low stock item'], 500);
        }
    }

    // Auto generate low stock items
    public function autoGenerateLowStock(LowStockItemService $service)
    {
        try {
            $items = $service->generateLowStockOrders();
            return response()->json([
                'message'   => 'Low stock items generated successfully',
                'item_ids'  => $items
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to generate low stock items: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to generate low stock items'], 500);
        }
    }

    // ✅ Send low stock order emails to multiple suppliers
    public function sendLowStockItem(Request $request)
    {
        try {
            $data = $request->validate([
                'suppliers' => 'required|array|min:1',
                'suppliers.*.supplier_id'       => 'required|exists:suppliers,id',
                'suppliers.*.delivery_deadline' => 'required|date|after:today',
                'suppliers.*.items'             => 'required|array|min:1',
                'suppliers.*.items.*.product_id'    => 'required|exists:products,id',
                'suppliers.*.items.*.requested_qty' => 'required|integer|min:1',
                'suppliers.*.items.*.buy_price'     => 'required|numeric|min:0',
            ]);

            $responseData = [];

            foreach ($data['suppliers'] as $supplierData) {
                $supplierId = $supplierData['supplier_id'];
                $deadline   = $supplierData['delivery_deadline'];

                // 1️⃣ Create PO
                $po = PurchaseOrder::create([
                    'po_number'         => 'PO-' . strtoupper(Str::random(6)),
                    'supplier_id'       => $supplierId,
                    'delivery_deadline' => $deadline,
                    'status'            => 'Order Created',
                ]);

                $itemsForEmail = [];

                foreach ($supplierData['items'] as $itemData) {
                    // 🔹 Get LowStockItem for this product & supplier
                    $lowStockItems = LowStockItem::where('product_id', $itemData['product_id'])
                        ->where('supplier_id', $supplierId)
                        ->get();

                    if ($lowStockItems->isEmpty()) {
                        // Agar secondary supplier ke liye LowStockItem nahi hai → create one
                        $lowStock = LowStockItem::create([
                            'product_id' => $itemData['product_id'],
                            'supplier_id' => $supplierId,
                            'requested_qty' => $itemData['requested_qty'],
                            'status' => 'Sent'
                        ]);
                    } else {
                        // 🔹 Update status for all matching low stock items to 'Sent'
                        foreach ($lowStockItems as $lowStock) {
                            $lowStock->update(['status' => 'Sent']);
                        }
                    }

                    // 2️⃣ Create PO Item
                    $poItem = PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'product_id'        => $itemData['product_id'],
                        'requested_qty'     => $itemData['requested_qty'],
                        'buy_price'         => $itemData['buy_price'],
                    ]);

                    $itemsForEmail[] = $poItem->load('product');
                }

                // 3️⃣ Send email
                if (!empty($itemsForEmail)) {
                    Mail::to($po->supplier->email)
                        ->send(new LowStockItemNotification($po->load(['supplier', 'items.product'])));
                }

                $responseData[] = $po->load(['supplier', 'items.product']);
            }

            return response()->json([
                'message' => "Purchase Orders created, emails sent, and low stock status updated to 'Sent'",
                'orders'  => $responseData,
            ], 200);

        } catch (\Exception $e) {
            Log::error("Failed to send POs: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to send POs: '.$e->getMessage()], 500);
        }
    }
}