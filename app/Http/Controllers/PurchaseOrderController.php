<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        try {
            $purchaseOrders = PurchaseOrder::with([
                'supplier',
                'items.product'
            ])->get();

            return response()->json($purchaseOrders, 200);

        } catch (\Exception $e) {
            Log::error("Failed to fetch purchase orders: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to fetch purchase orders'], 500);
        }
    }

    public function show($id)
    {
        try {
            $purchaseOrder = PurchaseOrder::with([
                'supplier',
                'items.product'
            ])->find($id);

            if (!$purchaseOrder) {
                return response()->json(['error' => 'Purchase Order not found'], 404);
            }

            return response()->json($purchaseOrder, 200);

        } catch (\Exception $e) {
            Log::error("Failed to fetch purchase order ID {$id}: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to fetch purchase order'], 500);
        }
    }
}
