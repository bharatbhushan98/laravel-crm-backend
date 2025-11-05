<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    /**
     * List all employees with their roles
     */
    public function index()
    {
        $users = User::with('roles')->get();

        return response()->json([
            'message' => 'Employee list fetched successfully',
            'data' => $users
        ]);
    }

    /**
     * Create new employee
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'theme' => 'light',
        ]);

        $user->assignRole($request->role);

        return response()->json([
            'message' => 'Employee created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * Update employee details
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|required|exists:roles,name',
        ]);

        // Update basic fields
        $user->update([
            'name' => $request->name ?? $user->name,
            'email' => $request->email ?? $user->email,
            'password' => $request->filled('password') ? Hash::make($request->password) : $user->password,
        ]);

        // Update role if provided
        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
        }

        return response()->json([
            'message' => 'Employee updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Delete employee
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'Employee deleted successfully'
        ]);
    }
}