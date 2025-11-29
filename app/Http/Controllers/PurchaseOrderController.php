<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Notification;
use App\Events\NewNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PurchaseOrderController extends Controller
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
                    'po_list_viewed' => 'List',
                    'po_viewed'      => 'Eye',
                    'po_deleted'     => 'Trash',
                    default          => 'Bell'
                },
            ],
            'is_read' => false,
        ]);

        broadcast(new NewNotification($notification));
    }

    public function index(Request $request)
    {
        try {
            $purchaseOrders = PurchaseOrder::with(['supplier', 'items.product'])->get();

            return response()->json($purchaseOrders, 200);

        } catch (\Exception $e) {
            Log::error("Failed to fetch purchase orders: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to fetch purchase orders'], 500);
        }
    }

    public function show($id, Request $request)
    {
        try {
            $purchaseOrder = PurchaseOrder::with(['supplier', 'items.product'])->find($id);

            if (!$purchaseOrder) {
                return response()->json(['error' => 'Purchase Order not found'], 404);
            }

            return response()->json($purchaseOrder, 200);

        } catch (\Exception $e) {
            Log::error("Failed to fetch purchase order ID {$id}: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to fetch purchase order'], 500);
        }
    }

    // ADD THIS METHOD TO SUPPORT DELETE
    public function destroy($id, Request $request)
    {
        try {
            $purchaseOrder = PurchaseOrder::with(['supplier'])->find($id);

            if (!$purchaseOrder) {
                return response()->json(['error' => 'Purchase Order not found'], 404);
            }

            $poNumber = $purchaseOrder->po_number;
            $supplierName = $purchaseOrder->supplier?->name ?? 'Unknown';

            $purchaseOrder->delete();

            $this->notify(
                $request,
                'po_deleted',
                'Purchase Order Deleted',
                '{{performer_name}} deleted PO #{{po_number}} for {{supplier_name}} at {{timestamp}}.',
                [
                    'po_number'     => $poNumber,
                    'supplier_name' => $supplierName
                ]
            );

            return response()->json(['message' => 'Purchase Order deleted successfully'], 200);

        } catch (\Exception $e) {
            Log::error("Failed to delete purchase order ID {$id}: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to delete purchase order'], 500);
        }
    }
}