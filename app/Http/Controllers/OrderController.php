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
use App\Models\Notification;
use App\Events\NewNotification;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OrderController extends Controller
{
    /**
     * 🔔 Send Notification (with user info from header or token)
     */
    private function notify(Request $request, string $type, string $title, string $message, array $replacements = [], ?int $userId = null)
    {
        // ✅ Get user if authenticated (e.g., Sanctum / JWT)
        $user = $request->user();

        // ✅ Fallback to frontend-sent headers
        $performerName = $user->name ?? $request->header('X-User-Name', 'Unknown User');
        $performerId   = $user->id ?? $request->header('X-User-ID', 1);
        $userId        = $userId ?? $performerId;

        // ✅ Prepare default placeholders
        $default = [
            'performer_name' => $performerName,
            'performer_id'   => $performerId,
            'timestamp'      => Carbon::now()->format('d M Y, h:i A'),
        ];

        $replacements = array_merge($default, $replacements);

        // ✅ Replace placeholders like {{performer_name}}
        foreach ($replacements as $key => $value) {
            $message = str_replace("{{{$key}}}", $value, $message);
        }

        // ✅ Create Notification
        $notification = Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'data'    => [
                'title'   => $title,
                'message' => $message,
            ],
            'is_read' => false,
        ]);

        // ✅ Real-time broadcast
        broadcast(new NewNotification($notification));
    }

    /* ====================== STORE ====================== */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'date'        => 'required|date',
            'payment'     => 'required|string',
            'status'      => 'required|string',
            'items'       => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.batch_id'   => 'nullable|exists:batches,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.hsn_code'   => 'required|string|max:20',
            'items.*.gst_rate'   => 'required|numeric|min:0|max:28',
            'billing'  => 'required|array',
            'billing.name'          => 'required|string|max:255',
            'billing.email'         => 'nullable|email',
            'billing.phone'         => 'nullable|string|max:20',
            'billing.address_line1' => 'required|string',
            'billing.city'          => 'required|string',
            'billing.state'         => 'required|string',
            'billing.postal_code'   => 'required|string',
            'billing.country'       => 'required|string',
            'shipping' => 'required|array',
            'shipping.name'          => 'required|string|max:255',
            'shipping.email'         => 'nullable|email',
            'shipping.phone'         => 'nullable|string|max:20',
            'shipping.address_line1' => 'required|string',
            'shipping.city'          => 'required|string',
            'shipping.state'         => 'required|string',
            'shipping.postal_code'   => 'required|string',
            'shipping.country'       => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $subTotal = $totalTax = 0;

            // ✅ Create Order
            $order = Order::create([
                'customer_id' => $request->customer_id,
                'date'        => $request->date,
                'amount'      => 0,
                'payment'     => $request->payment,
                'status'      => $request->status,
            ]);

            // ✅ Billing + Shipping
            $order->addresses()->create(array_merge($request->billing,  ['type' => 'billing']));
            $order->addresses()->create(array_merge($request->shipping, ['type' => 'shipping']));

            // ✅ Items loop
            foreach ($request->items as $item) {
                $lineSubtotal = $item['quantity'] * $item['unit_price'];
                $lineTax      = ($lineSubtotal * $item['gst_rate']) / 100;
                $lineTotal    = $lineSubtotal + $lineTax;
                $subTotal    += $lineSubtotal;
                $totalTax    += $lineTax;

                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $item['product_id'],
                    'batch_id'     => $item['batch_id'] ?? null,
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $item['unit_price'],
                    'subtotal'     => $lineSubtotal,
                    'hsn_code'     => $item['hsn_code'],
                    'gst_rate'     => $item['gst_rate'],
                    'igst_rate'    => $item['gst_rate'],
                    'igst_amount'  => $lineTax,
                    'tax_amount'   => $lineTax,
                    'total'        => $lineTotal,
                ]);

                // ✅ Update batch stock
                if (!empty($item['batch_id'])) {
                    $batch = Batch::find($item['batch_id']);
                    if ($batch) {
                        $newQty = $batch->stock_level - $item['quantity'];
                        $batch->update([
                            'stock_level' => max(0, $newQty),
                            'status'      => $newQty <= 0 ? 'Out of Stock'
                                            : ($newQty < ($batch->product->max_stock * 0.2) ? 'Low Stock' : 'In Stock'),
                        ]);
                    }
                }
            }

            $order->update(['amount' => $subTotal + $totalTax]);

            // ✅ Generate Invoice
            $companyProfile = CompanyProfile::find(1);
            if (!$companyProfile) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Company profile not found'], 404);
            }

            $invoice = Invoice::create([
                'invoice_number'      => 'INV-' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
                'customer_id'         => $order->customer_id,
                'order_id'            => $order->id,
                'company_profile_id'  => 1,
                'issue_date'          => now(),
                'due_date'            => now()->addDays(7),
                'sub_total'           => $subTotal,
                'tax_amount'          => $totalTax,
                'total_amount'        => $subTotal + $totalTax,
                'status'              => 'Draft',
                'currency'            => 'INR',
                'notes'               => 'Auto-generated invoice from order',
                'company_name'        => $companyProfile->name ?? 'N/A',
                'company_address'     => $companyProfile->address ?? 'N/A',
            ]);

            foreach ($order->items()->get() as $item) {
                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'product_id'  => $item->product_id,
                    'batch_id'    => $item->batch_id,
                    'description' => $item->product->name ?? '',
                    'hsn_code'    => $item->hsn_code,
                    'quantity'    => $item->quantity,
                    'unit_price'  => $item->unit_price,
                    'tax_rate'    => $item->gst_rate,
                    'line_total'  => $item->total,
                ]);
            }

            DB::commit();

            // ✅ Notification: New Order
            $this->notify(
                $request,
                'order_created',
                'New Order Created',
                '{{performer_name}} created order #{{order_id}} at {{timestamp}}.',
                ['order_id' => $order->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Order & Invoice created successfully.',
                'order'   => $order->load('customer', 'items.product', 'items.batch', 'addresses'),
                'invoice' => $invoice->load('items.product', 'items.batch'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    /* ====================== UPDATE ====================== */
    public function update(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $request->validate([
            'status'  => 'sometimes|required|string|in:Pending,Processing,Shipped,Delivered,Cancelled',
            'payment' => 'sometimes|required|string',
        ]);

        DB::beginTransaction();
        try {
            $oldStatus = $order->status;

            $order->update([
                'status'  => $request->status ?? $order->status,
                'payment' => $request->payment ?? $order->payment,
            ]);

            DB::commit();

            $this->notify(
                $request,
                'order_updated',
                'Order Updated',
                '{{performer_name}} changed order #{{order_id}} status from "{{old_status}}" to "{{new_status}}" at {{timestamp}}.',
                [
                    'order_id'    => $order->id,
                    'old_status'  => $oldStatus,
                    'new_status'  => $order->status,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully.',
                'order'   => $order->fresh(['customer', 'items.product', 'items.batch']),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /* ====================== DELETE ====================== */
    public function destroy(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        DB::beginTransaction();
        try {
            $orderId = $order->id;
            $order->delete();

            DB::commit();

            $this->notify(
                $request,
                'order_deleted',
                'Order Deleted',
                '{{performer_name}} deleted order #{{order_id}} at {{timestamp}}.',
                ['order_id' => $orderId]
            );

            return response()->json(['success' => true, 'message' => 'Order deleted successfully.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /* ====================== GET METHODS ====================== */
    public function index()
    {
        $orders = Order::with(['customer', 'items.product', 'items.batch'])->latest()->get();
        return response()->json(['success' => true, 'orders' => $orders], 200);
    }

    public function show($id)
    {
        $order = Order::with(['customer', 'items.product', 'items.batch'])->find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }
        return response()->json(['success' => true, 'order' => $order], 200);
    }

    public function getByCustomer($customerId)
    {
        $orders = Order::with(['customer', 'items.product', 'items.batch'])
            ->where('customer_id', $customerId)
            ->latest()
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No orders found'], 404);
        }
        return response()->json(['success' => true, 'orders' => $orders], 200);
    }

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

        return response()->json(['success' => true, 'groups' => $result], 200);
    }
}