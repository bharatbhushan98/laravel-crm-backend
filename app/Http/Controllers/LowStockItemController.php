<?php

namespace App\Http\Controllers;

use App\Models\LowStockItem;
use App\Services\LowStockItemService;
use App\Models\Notification;
use App\Events\NewNotification;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\LowStockItemNotification;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LowStockItemController extends Controller
{
    /**
     * Send Notification – SAME AS Order/Supplier/Product
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
                    'lowstock_created'     => 'Plus',
                    'lowstock_status_sent' => 'Mail',
                    'lowstock_generated'   => 'RefreshCw',
                    default                => 'Bell'
                },
            ],
            'is_read' => false,
        ]);

        broadcast(new NewNotification($notification));
    }

    // GET all low stock items
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

            $product = Product::find($data['product_id']);
            $supplier = Supplier::find($data['supplier_id']);

            $this->notify(
                $request,
                'lowstock_created',
                'Low Stock Item Created',
                '{{performer_name}} created low stock request for "{{product_name}}" ({{qty}} units) from {{supplier_name}} at {{timestamp}}.',
                [
                    'product_name'  => $product->name,
                    'qty'           => $data['requested_qty'],
                    'supplier_name' => $supplier->name
                ]
            );

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
    public function autoGenerateLowStock(LowStockItemService $service, Request $request)
    {
        try {
            $items = $service->generateLowStockOrders();

            $this->notify(
                $request,
                'lowstock_generated',
                'Low Stock Auto-Generated',
                '{{performer_name}} auto-generated {{count}} low stock item(s) at {{timestamp}}.',
                ['count' => count($items)]
            );

            return response()->json([
                'message'   => 'Low stock items generated successfully',
                'item_ids'  => $items
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to generate low stock items: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to generate low stock items'], 500);
        }
    }

    // Send low stock order emails to multiple suppliers
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
            $totalPOs = count($data['suppliers']);

            foreach ($data['suppliers'] as $supplierData) {
                $supplierId = $supplierData['supplier_id'];
                $deadline   = $supplierData['delivery_deadline'];
                $supplier   = Supplier::find($supplierId);

                // Create PO
                $po = PurchaseOrder::create([
                    'po_number'         => 'PO-' . strtoupper(Str::random(6)),
                    'supplier_id'       => $supplierId,
                    'delivery_deadline' => $deadline,
                    'status'            => 'Order Created',
                ]);

                $itemsForEmail = [];
                $productNames = [];

                foreach ($supplierData['items'] as $itemData) {
                    $product = Product::find($itemData['product_id']);
                    $productNames[] = $product->name;

                    // Update or create LowStockItem
                    $lowStockItems = LowStockItem::where('product_id', $itemData['product_id'])
                        ->where('supplier_id', $supplierId)
                        ->get();

                    if ($lowStockItems->isEmpty()) {
                        LowStockItem::create([
                            'product_id'    => $itemData['product_id'],
                            'supplier_id'   => $supplierId,
                            'requested_qty' => $itemData['requested_qty'],
                            'buy_price'     => $itemData['buy_price'],
                            'status'        => 'Sent'
                        ]);
                    } else {
                        foreach ($lowStockItems as $lowStock) {
                            $lowStock->update(['status' => 'Sent']);
                        }
                    }

                    // Create PO Item
                    $poItem = PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'product_id'        => $itemData['product_id'],
                        'requested_qty'     => $itemData['requested_qty'],
                        'buy_price'         => $itemData['buy_price'],
                    ]);

                    $itemsForEmail[] = $poItem->load('product');
                }

                // Send email
                if (!empty($itemsForEmail)) {
                    Mail::to($supplier->email)
                        ->send(new LowStockItemNotification($po->load(['supplier', 'items.product'])));
                }

                $responseData[] = $po->load(['supplier', 'items.product']);
            }

            // Notify: POs sent
            $this->notify(
                $request,
                'lowstock_status_sent',
                'Purchase Orders Sent',
                '{{performer_name}} sent {{count}} PO(s) to suppliers at {{timestamp}}.',
                ['count' => $totalPOs]
            );

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