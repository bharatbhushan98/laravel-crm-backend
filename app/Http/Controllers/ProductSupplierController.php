<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;

class ProductSupplierController extends Controller
{
    /**
     * Assign suppliers to a product
     */
    public function store(Request $request, $productId)
    {
        $validated = $request->validate([
            'supplier_ids' => 'required|array|min:1',
            'supplier_ids.*' => 'integer|exists:suppliers,id',
        ]);

        $product = Product::find($productId);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Save suppliers in pivot table (product_supplier)
        $product->suppliers()->sync($validated['supplier_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Suppliers assigned to product successfully',
            'data' => $product->suppliers()->get()
        ]);
    }

    /**
     * Get all suppliers for a product
     */
    public function show($productId)
    {
        $product = Product::with('suppliers')->find($productId);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product->suppliers->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'email' => $s->email,
                'priority' => $s->priority
            ])
        ]);
    }
}