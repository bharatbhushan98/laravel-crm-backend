<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Notification;
use App\Events\NewNotification;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ProductController extends Controller
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
                    'product_created'        => 'Plus',
                    'product_updated'        => 'Edit',
                    'product_deleted'        => 'Trash',
                    'product_price_set'      => 'DollarSign',
                    'product_price_updated'  => 'DollarSign',
                    default                  => 'Bell'
                },
            ],
            'is_read' => false,
        ]);

        broadcast(new NewNotification($notification));
    }

    public function index()
    {
        return Product::with(['category', 'supplier', 'suppliers', 'batches', 'currentPrice'])
            ->whereHas('prices')
            ->get();
    }

    public function allProducts()
    {
        return Product::with(['category', 'supplier', 'suppliers', 'batches', 'currentPrice'])
            ->get();
    }

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
            // 'status'   => 'required|in:Active,Inactive',
            'batches' => 'sometimes|array',
            'batches.*.batch_number' => 'required|string|max:100',
            'batches.*.stock_level'  => 'required|numeric|min:0',
            'batches.*.expiry_date'  => 'required|date',
        ]);

        $productData = collect($validated)->except(['batches', 'supplier_ids'])->toArray();
        $product = Product::create($productData);

        if (!empty($validated['supplier_ids'])) {
            $product->suppliers()->attach($validated['supplier_ids']);
        } else {
            $product->suppliers()->attach($validated['supplier_id']);
        }

        if (!empty($validated['batches'])) {
            $product->syncBatches($validated['batches']);
        }

        $this->notify(
            $request,
            'product_created',
            'New Product Added',
            '{{performer_name}} added product "{{product_name}}" ({{status}}) at {{timestamp}}.',
            [
                'product_name' => $product->name,
                'status'       => $product->status
            ]
        );

        return response()->json([
            'message' => 'Product created successfully. Please set buy & sell price.',
            'product' => $product->load(['batches'])
        ], 201);
    }

    /**
     * SET PRICE – WITH NOTIFICATION
     */
    public function setPrice(Request $request, $id)
    {
        $validated = $request->validate([
            'buy_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
        ]);

        $product = Product::findOrFail($id);

        $price = $product->prices()->create(array_merge($validated, ['effective_date' => now()]));

        // Build discount string
        $discount = '';
        if ($validated['discount_type'] && $validated['discount_value']) {
            $discount = " with {$validated['discount_value']}" . ($validated['discount_type'] === 'percentage' ? '%' : ' fixed');
        }

        $this->notify(
            $request,
            'product_price_set',
            'Price Set for Product',
            '{{performer_name}} set price for "{{product_name}}": Buy ₹{{buy_price}}, Sell ₹{{sell_price}}{{discount}} at {{timestamp}}.',
            [
                'product_name' => $product->name,
                'buy_price'    => number_format($validated['buy_price'], 2),
                'sell_price'   => number_format($validated['sell_price'], 2),
                'discount'     => $discount,
            ]
        );

        return response()->json([
            'message' => 'Price set successfully.',
            'product' => $product->load('currentPrice')
        ]);
    }

    /**
     * UPDATE PRICE – WITH NOTIFICATION
     */
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

        $old = $price->only(['buy_price', 'sell_price', 'discount_type', 'discount_value']);
        $price->update(array_merge($validated, ['effective_date' => now()]));
        $new = $price->only(['buy_price', 'sell_price', 'discount_type', 'discount_value']);

        $changes = [];
        foreach ($old as $key => $value) {
            if ($value != $new[$key]) {
                $formattedOld = $key === 'buy_price' || $key === 'sell_price' ? '₹' . number_format($value, 2) : $value;
                $formattedNew = $key === 'buy_price' || $key === 'sell_price' ? '₹' . number_format($new[$key], 2) : $new[$key];
                $changes[] = "$key: '$formattedOld' to '$formattedNew'";
            }
        }

        if (!empty($changes)) {
            $this->notify(
                $request,
                'product_price_updated',
                'Product Price Updated',
                '{{performer_name}} updated price for "{{product_name}}": ' . implode(' | ', $changes) . ' at {{timestamp}}.',
                ['product_name' => $product->name]
            );
        }

        return response()->json([
            'message' => 'Price updated successfully.',
            'product' => $product->load('currentPrice')
        ]);
    }

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

        $changes = [];

        // Track scalar changes
        if (isset($validated['name']) && $validated['name'] !== $product->name) {
            $changes[] = "name: '{$product->name}' to '{$validated['name']}'";
            $product->name = $validated['name'];
        }
        if (isset($validated['status']) && $validated['status'] !== $product->status) {
            $changes[] = "status: '{$product->status}' to '{$validated['status']}'";
            $product->status = $validated['status'];
        }
        if (isset($validated['max_stock']) && $validated['max_stock'] != $product->max_stock) {
            $changes[] = "max stock: '{$product->max_stock}' to '{$validated['max_stock']}'";
            $product->max_stock = $validated['max_stock'];
        }
        if (isset($validated['hsn_code']) && $validated['hsn_code'] !== $product->hsn_code) {
            $changes[] = "HSN: '{$product->hsn_code}' to '{$validated['hsn_code']}'";
            $product->hsn_code = $validated['hsn_code'];
        }
        if (isset($validated['gst_rate']) && $validated['gst_rate'] != $product->gst_rate) {
            $changes[] = "GST: '{$product->gst_rate}'% to '{$validated['gst_rate']}%'";
            $product->gst_rate = $validated['gst_rate'];
        }
        if (isset($validated['sku']) && $validated['sku'] !== $product->sku) {
            $changes[] = "SKU: '{$product->sku}' to '{$validated['sku']}'";
            $product->sku = $validated['sku'];
        }
        if (isset($validated['product_code']) && $validated['product_code'] !== $product->product_code) {
            $changes[] = "Code: '{$product->product_code}' to '{$validated['product_code']}'";
            $product->product_code = $validated['product_code'];
        }
        if (isset($validated['category_id']) && $validated['category_id'] != $product->category_id) {
            $oldCat = $product->category?->name ?? 'None';
            $newCat = \App\Models\Category::find($validated['category_id'])?->name ?? 'None';
            $changes[] = "category: '$oldCat' to '$newCat'";
            $product->category_id = $validated['category_id'];
        }

        // Save scalar fields
        $product->update(collect($validated)->only([
            'name', 'category_id', 'hsn_code', 'gst_rate', 'max_stock', 'sku', 'product_code', 'status'
        ])->toArray());

        // Track supplier changes
        if (isset($validated['supplier_ids'])) {
            $oldIds = $product->suppliers()->pluck('suppliers.id')->toArray();
            $newIds = $validated['supplier_ids'];

            $added = array_diff($newIds, $oldIds);
            $removed = array_diff($oldIds, $newIds);

            $addedNames = Supplier::whereIn('id', $added)->pluck('name')->implode(', ');
            $removedNames = Supplier::whereIn('id', $removed)->pluck('name')->implode(', ');

            if (!empty($added)) {
                $changes[] = "added suppliers: $addedNames";
            }
            if (!empty($removed)) {
                $changes[] = "removed suppliers: $removedNames";
            }

            $product->suppliers()->sync($newIds);
        }

        // Track batch changes
        if (isset($validated['batches'])) {
            $oldBatches = $product->batches()->get()->keyBy('id');
            $newBatches = collect($validated['batches'])->keyBy('id');

            $added = $newBatches->whereNotIn('id', $oldBatches->keys());
            $updated = $newBatches->whereIn('id', $oldBatches->keys());
            $removed = $oldBatches->whereNotIn('id', $newBatches->keys());

            if ($added->isNotEmpty()) {
                $names = $added->pluck('batch_number')->implode(', ');
                $changes[] = "added batches: $names";
            }
            if ($updated->isNotEmpty()) {
                $changes[] = "updated " . $updated->count() . " batch(es)";
            }
            if ($removed->isNotEmpty()) {
                $names = $removed->pluck('batch_number')->implode(', ');
                $changes[] = "removed batches: $names";
            }

            $product->syncBatches($validated['batches']);
        }

        if (empty($changes)) {
            return response()->json(['message' => 'No changes'], 200);
        }

        $this->notify(
            $request,
            'product_updated',
            'Product Updated',
            '{{performer_name}} updated product "{{product_name}}": ' . implode(' | ', $changes) . ' at {{timestamp}}.',
            ['product_name' => $product->name]
        );

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->load(['batches', 'currentPrice', 'suppliers', 'category'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $name = $product->name;
        $status = $product->status ?? 'Unknown';

        $product->delete();

        $this->notify(
            $request,
            'product_deleted',
            'Product Deleted',
            '{{performer_name}} deleted product "{{product_name}}" at {{timestamp}}.',
            [
                'product_name' => $name,
                'status'       => $status
            ]
        );

        return response()->json(['message' => 'Product deleted successfully']);
    }
}