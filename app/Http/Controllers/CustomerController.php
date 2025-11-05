<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;

class CustomerController extends Controller
{
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

    // Create customer
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            // 'initials' => 'required|max:5',
            'contact' => 'required|email',
            'phone' => 'required',
            'type_id' => 'required|exists:types,id',
            'status' => 'required|in:Active,Inactive,Pending',
            'address' => 'nullable|string|max:500',    // ✅ formatted address
            'gst_number' => 'nullable|string|max:20',  // ✅ GST validation
        ]);

        $customer = Customer::create($request->all());
        $customer->load(['type', 'orders']); // naya customer ke sath type + orders bhejna

        return response()->json($customer, 201);
    }

    // Update customer
    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $request->validate([
            'name' => 'required',
            // 'initials' => 'required|max:5',
            'contact' => 'required|email',
            'phone' => 'required',
            'type_id' => 'required|exists:types,id',
            'status' => 'required|in:Active,Inactive,Pending',
            'address' => 'nullable|string|max:500',
            'gst_number' => 'nullable|string|max:20',
        ]);


        $customer->update($request->all());
        $customer->load(['type', 'orders']); // update ke baad bhi relation bhejna

        return response()->json($customer);
    }

    // Delete customer
    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();
        return response()->json(['message' => 'Customer deleted successfully']);
    }
}
