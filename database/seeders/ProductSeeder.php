<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Batch;

class ProductSeeder extends Seeder {
    public function run(): void {
        $products = [
            [
                'product_code' => "AMX2024001",
                'name' => "Amoxicillin 500mg",
                'sku' => "AMX-500-100",
                'category' => "Antibiotics",
                'max_stock' => 1000,
                'supplier' => "PharmaCorp Ltd",
                'stockLevel' => 50,
                'expiry' => "2025-06-15",
                'status' => "Low Stock",
            ],
            [
                'product_code' => "PAR2024002",
                'name' => "Paracetamol 650mg",
                'sku' => "PAR-650-200",
                'category' => "Analgesics",
                'max_stock' => 2000,
                'supplier' => "MediSupply Co",
                'stockLevel' => 850,
                'expiry' => "2025-12-20",
                'status' => "In Stock",
            ],
            [
                'product_code' => "OME2024003",
                'name' => "Omeprazole 20mg",
                'sku' => "OME-20-150",
                'category' => "Gastroenterology",
                'max_stock' => 1500,
                'supplier' => "HealthSource Inc",
                'stockLevel' => 320,
                'expiry' => "2025-09-10",
                'status' => "In Stock",
            ],
            [
                'product_code' => "INS2024004",
                'name' => "Insulin Glargine 100IU",
                'sku' => "INS-100-50",
                'category' => "Diabetes",
                'max_stock' => 500,
                'supplier' => "DiabetesCare Ltd",
                'stockLevel' => 0,
                'expiry' => "2024-12-31",
                'status' => "Out of Stock",
            ],
            [
                'product_code' => "MET2024005",
                'name' => "Metformin 500mg",
                'sku' => "MET-500-180",
                'category' => "Diabetes",
                'max_stock' => 2500,
                'supplier' => "PharmaCorp Ltd",
                'stockLevel' => 1200,
                'expiry' => "2026-03-25",
                'status' => "In Stock",
            ],
        ];

        foreach ($products as $item) {
            $category = Category::where('name', $item['category'])->first();
            $supplier = Supplier::where('name', $item['supplier'])->first();

            $product = Product::firstOrCreate(
                ['product_code' => $item['product_code']],
                [
                    'name' => $item['name'],
                    'sku' => $item['sku'],
                    'category_id' => $category->id,
                    'supplier_id' => $supplier->id,
                    'max_stock' => $item['max_stock'],
                ]
            );

            Batch::firstOrCreate(
                [
                    'batch_number' => $item['product_code'] . "-B1",
                ],
                [
                    'product_id' => $product->id,
                    'stock_level' => $item['stockLevel'],
                    'expiry_date' => $item['expiry'],
                    'status' => $item['status'],
                ]
            );
        }
    }
}
