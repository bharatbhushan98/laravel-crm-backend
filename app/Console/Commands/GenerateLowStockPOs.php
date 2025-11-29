<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LowStockItemService;

class GenerateLowStockPOs extends Command
{
    protected $signature = 'purchase-orders:generate-low-stock';
    protected $description = 'Generate purchase orders for products with stock less than 20';

    public function handle(LowStockItemService $service)
    {
        $orders = $service->generateLowStockOrders();

        if (count($orders) > 0) {
            $this->info("✅ Generated Purchase Orders: " . implode(', ', $orders));
        } else {
            $this->info("⚠️ No low stock products found (all products have >= 20 stock).");
        }
    }
}
