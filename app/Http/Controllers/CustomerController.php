<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Notification;
use App\Events\NewNotification;
use Carbon\Carbon;

class CustomerController extends Controller
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
                    'customer_created' => 'UserPlus',
                    'customer_updated' => 'UserEdit',
                    'customer_deleted' => 'UserMinus',
                    default            => 'Bell'
                },
            ],
            'is_read' => false,
        ]);

        broadcast(new NewNotification($notification));
    }

    // Get all customers with type and orders
    public function index()
    {
        $customers = Customer::with(['type', 'orders'])->get();
        return response()->json($customers);
    }

    // Get single customer with type and orders
    public function show($id)
    {
        $customer = Customer::with([
            'type',
            'orders.items.product',
            'orders.items.batch'
        ])->findOrFail($id);

        return response()->json($customer);
    }

    // Create customer + NOTIFICATION
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'contact' => 'required|email',
            'phone' => 'required|string|max:20',
            'type_id' => 'required|exists:types,id',
            'status' => 'required|in:Active,Inactive,Pending',
            'address' => 'nullable|string|max:500',
            'gst_number' => 'nullable|string|max:20',
        ]);

        $customer = Customer::create($request->all());
        $customer->load(['type', 'orders']);

        // NOTIFICATION: Customer Created
        $this->notify(
            $request,
            'customer_created',
            'New Customer Added',
            '{{performer_name}} added customer "{{customer_name}}" ({{status}}) at {{timestamp}}.',
            [
                'customer_name' => $customer->name,
                'status'        => $customer->status
            ]
        );

        return response()->json($customer, 201);
    }

    // Update customer + NOTIFICATION
    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'contact' => 'sometimes|required|email',
            'phone' => 'sometimes|required|string|max:20',
            'type_id' => 'sometimes|required|exists:types,id',
            'status' => 'sometimes|required|in:Active,Inactive,Pending',
            'address' => 'nullable|string|max:500',
            'gst_number' => 'nullable|string|max:20',
        ]);

        $oldName = $customer->name;
        $oldStatus = $customer->status;

        $customer->update($request->all());
        $customer->load(['type', 'orders']);

        $changes = [];
        if ($request->filled('name') && $request->name !== $oldName) {
            $changes[] = "name: '$oldName' to '{$request->name}'";
        }
        if ($request->filled('status') && $request->status !== $oldStatus) {
            $changes[] = "status: '$oldStatus' to '{$request->status}'";
        }
        if ($request->filled('contact')) {
            $changes[] = "email updated";
        }
        if ($request->filled('phone')) {
            $changes[] = "phone updated";
        }

        if (!empty($changes)) {
            // NOTIFICATION: Customer Updated
            $this->notify(
                $request,
                'customer_updated',
                'Customer Updated',
                '{{performer_name}} updated customer "{{customer_name}}": ' . implode(' | ', $changes) . ' at {{timestamp}}.',
                [
                    'customer_name' => $customer->name
                ]
            );
        }

        return response()->json($customer);
    }

    // Delete customer + NOTIFICATION
    public function destroy(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $name = $customer->name;
        $status = $customer->status;

        $customer->delete();

        // NOTIFICATION: Customer Deleted
        $this->notify(
            $request,
            'customer_deleted',
            'Customer Deleted',
            '{{performer_name}} deleted customer "{{customer_name}}" ({{status}}) at {{timestamp}}.',
            [
                'customer_name' => $name,
                'status'        => $status
            ]
        );

        return response()->json(['message' => 'Customer deleted successfully']);
    }
}