<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Notification;
use App\Events\NewNotification;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SupplierController extends Controller
{
    /**
     * Send Notification – Same as OrderController (with icons)
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
                    'supplier_created' => 'Plus',
                    'supplier_updated' => 'Edit',
                    'supplier_deleted' => 'Trash',
                    default            => 'Bell'
                },
            ],
            'is_read' => false,
        ]);

        broadcast(new NewNotification($notification));
    }

    public function index()
    {
        $suppliers = Supplier::all();
        return response()->json([
            'success' => true,
            'data'    => $suppliers
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'nullable|email|unique:suppliers,email',
            'contact'  => 'nullable|string|max:20',
            'address'  => 'nullable|string|max:500',
            'priority' => 'nullable|in:primary,normal,low',
        ]);

        $validated['priority'] = $validated['priority'] ?? 'normal';
        $supplier = Supplier::create($validated);

        $this->notify(
            $request,
            'supplier_created',
            'New Supplier Added',
            '{{performer_name}} added supplier "{{supplier_name}}" ({{status}}) at {{timestamp}}.',
            [
                'supplier_name' => $supplier->name,
                'status'        => $supplier->priority
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Supplier created successfully',
            'data'    => $supplier
        ], 201);
    }

    public function show($id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['success' => false, 'message' => 'Supplier not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $supplier]);
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['success' => false, 'message' => 'Supplier not found'], 404);
        }

        $validated = $request->validate([
            'name'     => 'sometimes|required|string|max:255',
            'email'    => 'sometimes|nullable|email|unique:suppliers,email,' . $id,
            'contact'  => 'sometimes|nullable|string|max:20',
            'address'  => 'sometimes|nullable|string|max:500',
            'priority' => 'sometimes|nullable|in:primary,normal,low',
        ]);

        $changes = [];

        if (isset($validated['name']) && $validated['name'] !== $supplier->name) {
            $changes[] = "name: '{$supplier->name}' to '{$validated['name']}'";
            $supplier->name = $validated['name'];
        }
        if (isset($validated['email']) && $validated['email'] !== $supplier->email) {
            $old = $supplier->email ?? 'empty';
            $new = $validated['email'] ?? 'empty';
            $changes[] = "email: '{$old}' to '{$new}'";
            $supplier->email = $validated['email'];
        }
        if (isset($validated['contact']) && $validated['contact'] !== $supplier->contact) {
            $old = $supplier->contact ?? 'empty';
            $new = $validated['contact'] ?? 'empty';
            $changes[] = "contact: '{$old}' to '{$new}'";
            $supplier->contact = $validated['contact'];
        }
        if (isset($validated['address']) && $validated['address'] !== $supplier->address) {
            $changes[] = "address updated";
            $supplier->address = $validated['address'];
        }
        if (isset($validated['priority']) && $validated['priority'] !== $supplier->priority) {
            $changes[] = "priority: '{$supplier->priority}' to '{$validated['priority']}'";
            $supplier->priority = $validated['priority'];
        }

        if (empty($changes)) {
            return response()->json(['success' => true, 'message' => 'No changes'], 200);
        }

        $supplier->save();

        $this->notify(
            $request,
            'supplier_updated',
            'Supplier Updated',
            '{{performer_name}} updated supplier "{{supplier_name}}": ' . implode(' | ', $changes) . ' at {{timestamp}}.',
            ['supplier_name' => $supplier->name]
        );

        return response()->json([
            'success' => true,
            'message' => 'Supplier updated successfully',
            'data'    => $supplier
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['success' => false, 'message' => 'Supplier not found'], 404);
        }

        $name = $supplier->name;
        $priority = $supplier->priority ?? 'Unknown';

        $supplier->delete();

        $this->notify(
            $request,
            'supplier_deleted',
            'Supplier Deleted',
            '{{performer_name}} deleted supplier "{{supplier_name}}" ({{priority}}) at {{timestamp}}.',
            [
                'supplier_name' => $name,
                'priority'        => $priority
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Supplier deleted successfully'
        ]);
    }
}