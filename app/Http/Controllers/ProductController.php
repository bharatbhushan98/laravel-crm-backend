<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // List products with prices
    public function index()
    {
        return Product::with(['category', 'supplier', 'suppliers', 'batches', 'currentPrice'])
            ->whereHas('prices')
            ->get();
    }

    // List all products (with or without prices)
    public function allProducts()
    {
        return Product::with(['category', 'supplier', 'suppliers', 'batches', 'currentPrice'])
            ->get();
    }

    // Create product without price
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products',
            'product_code' => 'required|string|max:100|unique:products',
            'max_stock' => 'required|integer',
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_ids' => 'sometimes|array',
            'supplier_ids.*' => 'exists:suppliers,id',
            'hsn_code' => 'required|string|max:20',
            'gst_rate' => 'required|numeric|min:0|max:28',

            // Batches
            'batches' => 'sometimes|array',
            'batches.*.batch_number' => 'required|string|max:100',
            'batches.*.stock_level'  => 'required|numeric|min:0',
            'batches.*.expiry_date'  => 'required|date',
        ]);

        // ✅ Product data alag nikalo
        $productData = collect($validated)->except(['batches', 'supplier_ids'])->toArray();

        // Product create
        $product = Product::create($productData);

        // Attach suppliers
        if (!empty($validated['supplier_ids'])) {
            $product->suppliers()->attach($validated['supplier_ids']);
        } else {
            $product->suppliers()->attach($validated['supplier_id']);
        }

        // Save batches if provided
        if (!empty($validated['batches'])) {
            $product->syncBatches($validated['batches']);
        }

        return response()->json([
            'message' => 'Product created successfully. Please set buy & sell price.',
            'product' => $product->load(['batches'])
        ], 201);
    }

    // ✅ Set Price with Discount
    public function setPrice(Request $request, $id)
    {
        $validated = $request->validate([
            'buy_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
        ]); 

        $product = Product::findOrFail($id);    

        $product->prices()->create(array_merge($validated, [
            'effective_date' => now(),
        ]));    

        return response()->json([
            'message' => 'Price set successfully.',
            'product' => $product->load('currentPrice')
        ]);
    }   

    // ✅ Update Price
    public function updatePrice(Request $request, $id)
    {
        $validated = $request->validate([
            'buy_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
        ]); 

        $product = Product::findOrFail($id);
        $price = $product->currentPrice;    

        if (!$price) {
            return response()->json(['message' => 'No price record found.'], 404);
        }   

        $price->update(array_merge($validated, ['effective_date' => now()]));   

        return response()->json([
            'message' => 'Price updated successfully.',
            'product' => $product->load('currentPrice')
        ]);
    }

    // Update product details and batches
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'supplier_ids' => 'sometimes|array',
            'supplier_ids.*' => 'exists:suppliers,id',
            'hsn_code' => 'sometimes|string|max:20',
            'gst_rate' => 'sometimes|numeric|min:0|max:28',
            'max_stock' => 'sometimes|integer',
            'sku' => 'sometimes|string|max:100|unique:products,sku,' . $id,
            'product_code' => 'sometimes|string|max:100|unique:products,product_code,' . $id,
            'batches' => 'sometimes|array',
            'batches.*.batch_number' => 'required|string|max:100',
            'batches.*.stock_level' => 'required|numeric|min:0',
            'batches.*.expiry_date' => 'required|date',
            'batches.*.id' => 'sometimes|exists:batches,id',
        ]);

        $product->update($validated);

        if (isset($validated['supplier_ids'])) {
            $product->suppliers()->sync($validated['supplier_ids']);
        }

        if (isset($validated['batches'])) {
            $product->syncBatches($validated['batches']);
        }

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->load(['batches', 'currentPrice'])
        ]);
    }

    // Delete product
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}