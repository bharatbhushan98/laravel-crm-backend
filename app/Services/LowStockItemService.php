<?php
namespace App\Services;

use App\Models\Product;
use App\Models\LowStockItem;

class LowStockItemService
{
    public function generateLowStockOrders()
    {
        $lowStockProducts = Product::with(['supplier', 'batches', 'currentPrice'])->get();
        $createdOrders = [];

        foreach ($lowStockProducts as $product) {
            $currentStock = $product->batches->sum('stock_level');

            if ($currentStock < 20 && $product->supplier_id) {
                $buyPrice = $product->currentPrice->buy_price ?? 0;

                $lowStockItem = LowStockItem::firstOrCreate(
                    [
                        'product_id'  => $product->id,
                        'supplier_id' => $product->supplier_id,
                    ],
                    [
                        'requested_qty' => $currentStock,
                        'buy_price'     => $buyPrice,
                        'status'        => 'Pending',
                    ]
                );

                $createdOrders[] = $lowStockItem->id;
            }
        }

        return $createdOrders;
    }
}
