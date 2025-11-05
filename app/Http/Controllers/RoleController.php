<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleController extends Controller
{
    /**
     * Fetch all roles and grouped permissions
     */
    public function index()
    {
        $roles = Role::withCount('users')->with('permissions')->get();

        $permissions = Permission::all()->groupBy(function ($item) {
            $parts = explode('-', $item->name);
            return $parts[0] ?? 'general';
        });

        return response()->json([
            'roles' => $roles,
            'permissions_grouped' => $permissions,
        ]);
    }

    /**
     * Create a new role
     */
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|unique:roles,name']);

        $role = Role::create(['name' => $request->name]);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role
        ], 201);
    }

    /**
     * Update role name and sync permissions
     */
    public function update(Request $request, $roleId)
    {
        $role = Role::findOrFail($roleId);

        $request->validate([
            'name' => 'required|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array'
        ]);

        $role->update(['name' => $request->name]);

        if($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'message' => 'Role and permissions updated successfully',
            'role' => $role->load('permissions')
        ]);
    }

    /**
     * Delete a role (only if no users are assigned)
     */
    public function destroy($roleId)
    {
        $role = Role::findOrFail($roleId);

        // Check if any users are using this role
        $userCount = User::role($role->name)->count();
        if($userCount > 0) {
            return response()->json([
                'message' => 'Cannot delete role because it is assigned to users',
                'user_count' => $userCount
            ], 400);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * Assign role to a user
     */
    public function assignRoleToUser(Request $request, User $user)
    {
        $request->validate(['role_name' => 'required|exists:roles,name']);

        $user->syncRoles([$request->role_name]);

        return response()->json([
            'message' => 'Role assigned to user successfully'
        ]);
    }
}