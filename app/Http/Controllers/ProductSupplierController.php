<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Notification;
use App\Events\NewNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProductSupplierController extends Controller
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
                    'product_suppliers_assigned' => 'Link',
                    default                      => 'Bell'
                },
            ],
            'is_read' => false,
        ]);

        broadcast(new NewNotification($notification));
    }

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

        // Get supplier names before sync
        $suppliers = Supplier::whereIn('id', $validated['supplier_ids'])->get();
        $supplierNames = $suppliers->pluck('name')->implode(', ');
        $supplierCount = $suppliers->count();

        // Sync pivot
        $product->suppliers()->sync($validated['supplier_ids']);

        // SEND NOTIFICATION
        $this->notify(
            $request,
            'product_suppliers_assigned',
            'Suppliers Assigned to Product',
            '{{performer_name}} assigned {{supplier_count}} supplier(s): {{supplier_names}} to {{product_name}} at {{timestamp}}.',
            [
                'product_name'    => $product->name,
                'supplier_count'  => $supplierCount,
                'supplier_names'  => $supplierNames,
            ]
        );

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
                'id'       => $s->id,
                'name'     => $s->name,
                'email'    => $s->email,
                'priority' => $s->pivot->priority ?? $s->priority // use pivot if exists
            ])
        ]);
    }
}