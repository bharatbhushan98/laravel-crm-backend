<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\CompanyProfile;
use App\Models\Batch;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Create a new order with items and auto-generate invoice
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id'        => 'required|exists:customers,id',
            'date'               => 'required|date',
            'payment'            => 'required|string',
            'status'             => 'required|string',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.batch_id'   => 'nullable|exists:batches,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.hsn_code'   => 'required|string|max:20',
            'items.*.gst_rate'   => 'required|numeric|min:0|max:28',

            // ✅ Address validation
            'billing'            => 'required|array',
            'billing.name'       => 'required|string|max:255',
            'billing.email'      => 'nullable|email',
            'billing.phone'      => 'nullable|string|max:20',
            'billing.address_line1' => 'required|string',
            'billing.city'       => 'required|string',
            'billing.state'      => 'required|string',
            'billing.postal_code'=> 'required|string',
            'billing.country'    => 'required|string',

            'shipping'            => 'required|array',
            'shipping.name'       => 'required|string|max:255',
            'shipping.email'      => 'nullable|email',
            'shipping.phone'      => 'nullable|string|max:20',
            'shipping.address_line1' => 'required|string',
            'shipping.city'       => 'required|string',
            'shipping.state'      => 'required|string',
            'shipping.postal_code'=> 'required|string',
            'shipping.country'    => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $subTotal = 0;
            $totalTax = 0;

            // ✅ Create order
            $order = Order::create([
                'customer_id' => $request->customer_id,
                'date'        => $request->date,
                'amount'      => 0,
                'payment'     => $request->payment,
                'status'      => $request->status,
            ]);

            // ✅ Save Billing & Shipping Address
            $order->addresses()->create(array_merge($request->billing, ['type' => 'billing']));
            $order->addresses()->create(array_merge($request->shipping, ['type' => 'shipping']));

            // ✅ Create Order Items & Deduct Stock
            foreach ($request->items as $item) {
                $lineSubtotal = $item['quantity'] * $item['unit_price'];
                $lineTax = ($lineSubtotal * $item['gst_rate']) / 100;
                $lineTotal = $lineSubtotal + $lineTax;

                $subTotal += $lineSubtotal;
                $totalTax += $lineTax;

                $orderItem = OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $item['product_id'],
                    'batch_id'     => $item['batch_id'] ?? null,
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $item['unit_price'],
                    'subtotal'     => $lineSubtotal,
                    'hsn_code'     => $item['hsn_code'],
                    'gst_rate'     => $item['gst_rate'],
                    'cgst_rate'    => 0,
                    'sgst_rate'    => 0,
                    'igst_rate'    => $item['gst_rate'],
                    'cgst_amount'  => 0,
                    'sgst_amount'  => 0,
                    'igst_amount'  => $lineTax,
                    'tax_amount'   => $lineTax,
                    'total'        => $lineTotal,
                ]);

                // 🔹 Deduct stock from batch
                if (!empty($item['batch_id'])) {
                    $batch = Batch::find($item['batch_id']);
                    if ($batch) {
                        $newQty = $batch->stock_level - $item['quantity'];
                        $batch->update([
                            'stock_level' => max(0, $newQty),
                            'status' => $newQty <= 0 ? 'Out of Stock' :
                                        ($newQty < ($batch->product->max_stock * 0.2) ? 'Low Stock' : 'In Stock'),
                        ]);
                    }
                }
            }

            // ✅ Update order total
            $order->update(['amount' => $subTotal + $totalTax]);

            // ✅ Get Company Profile
            $companyProfile = CompanyProfile::find(1);
            if (!$companyProfile) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Company profile not found'], 404);
            }

            // ✅ Create Invoice
            $invoice = Invoice::create([
                'invoice_number'  => 'INV-' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
                'customer_id'     => $order->customer_id,
                'order_id'        => $order->id,
                'company_profile_id' => 1,
                'issue_date'      => now(),
                'due_date'        => now()->addDays(7),
                'sub_total'       => $subTotal,
                'discount_amount' => 0,
                'tax_amount'      => $totalTax,
                'shipping_amount' => 0,
                'total_amount'    => $subTotal + $totalTax,
                'amount_paid'     => 0,
                'status'          => 'Draft',
                'currency'        => 'INR',
                'notes'           => 'Auto-generated invoice from order',

                // 👇 Snapshot of company profile
                'company_name'    => $companyProfile->name ?? 'N/A',
                'company_address' => $companyProfile->address ?? 'N/A',
                'company_gst_number' => $companyProfile->gst_number ?? 'N/A',
                'company_email'   => $companyProfile->email ?? 'N/A',
                'company_phone'   => $companyProfile->phone ?? 'N/A',
                'company_bank_details' => $companyProfile->bank_details ?? 'N/A',
            ]);

            // ✅ Create Invoice Items
            foreach ($order->items()->get() as $item) {
                InvoiceItem::create([
                    'invoice_id'   => $invoice->id,
                    'product_id'   => $item->product_id,
                    'batch_id'     => $item->batch_id,
                    'description'  => $item->product->name ?? '',
                    'hsn_code'     => $item->hsn_code,
                    'quantity'     => $item->quantity,
                    'unit_price'   => $item->unit_price,
                    'discount'     => 0,
                    'cgst_rate'    => 0,
                    'sgst_rate'    => 0,
                    'igst_rate'    => $item->gst_rate,
                    'tax_rate'     => $item->gst_rate,
                    'line_total'   => $item->total,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order & Invoice created successfully. Stock updated in batches.',
                'order'   => $order->load('customer', 'items.product', 'items.batch', 'addresses'),
                'invoice' => $invoice->load('items.product', 'items.batch'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order & invoice: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all orders with items
     */
    public function index()
    {
        $orders = Order::with(['customer', 'items.product', 'items.batch'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'orders'  => $orders,
        ], 200);
    }

    /**
     * Get single order by ID with items
     */
    public function show($id)
    {
        $order = Order::with(['customer', 'items.product', 'items.batch'])->find($id);

        if(!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order'   => $order,
        ], 200);
    }

    /**
     * Get orders by customer_id with items
     */
    public function getByCustomer($customerId)
    {
        $orders = Order::with(['customer', 'items.product', 'items.batch'])
            ->where('customer_id', $customerId)
            ->latest()
            ->get();

        if($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found for this customer',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'orders'  => $orders,
        ], 200);
    }

    /**
     * Get orders grouped by customer and date
     */
    public function getOrdersGrouped()
    {
        $groups = Order::select('customer_id', 'date')
            ->with('customer')
            ->selectRaw('COUNT(*) as total_orders, SUM(amount) as total_amount')
            ->groupBy('customer_id', 'date')
            ->orderBy('date', 'desc')
            ->get();

        $result = [];
        foreach ($groups as $group) {
            $records = Order::with(['customer', 'items.product', 'items.batch'])
                ->where('customer_id', $group->customer_id)
                ->whereDate('date', $group->date)
                ->get();

            $result[] = [
                'customer_id'   => $group->customer_id,
                'customer_name' => $group->customer->name ?? null,
                'date'          => $group->date,
                'total_orders'  => $group->total_orders,
                'total_amount'  => $group->total_amount,
                'records'       => $records,
            ];
        }

        return response()->json([
            'success' => true,
            'groups'  => $result,
        ], 200);
    }

    /**
     * Delete an order
     */
    public function destroy($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully',
        ], 200);
    }
}