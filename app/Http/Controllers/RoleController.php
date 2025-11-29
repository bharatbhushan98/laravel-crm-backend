<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Notification;
use App\Events\NewNotification;
use Carbon\Carbon;

class RoleController extends Controller
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
                    'role_created'           => 'Shield',
                    'role_updated'           => 'Edit',
                    'role_deleted'           => 'Trash',
                    'role_assigned_to_user'  => 'UserCheck',
                    default                  => 'Bell'
                },
            ],
            'is_read' => false,
        ]);

        broadcast(new NewNotification($notification));
    }

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
     * Create a new role + NOTIFICATION
     */
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|unique:roles,name']);

        $role = Role::create(['name' => $request->name]);

        // NOTIFICATION: Role Created
        $this->notify(
            $request,
            'role_created',
            'New Role Created',
            '{{performer_name}} created role "{{role_name}}" at {{timestamp}}.',
            ['role_name' => $role->name]
        );

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role
        ], 201);
    }

    /**
     * Update role name and sync permissions + NOTIFICATION
     */
    public function update(Request $request, $roleId)
    {
        $role = Role::findOrFail($roleId);

        $request->validate([
            'name' => 'required|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array'
        ]);

        $oldName = $role->name;
        $role->update(['name' => $request->name]);

        $changes = ["name: '$oldName' to '{$request->name}'"];

        if ($request->has('permissions')) {
            $oldPermissions = $role->permissions->pluck('name')->toArray();
            $newPermissions = $request->permissions;

            $added = array_diff($newPermissions, $oldPermissions);
            $removed = array_diff($oldPermissions, $newPermissions);

            if (!empty($added)) {
                $changes[] = 'added permissions: ' . implode(', ', $added);
            }
            if (!empty($removed)) {
                $changes[] = 'removed permissions: ' . implode(', ', $removed);
            }

            $role->syncPermissions($request->permissions);
        }

        // NOTIFICATION: Role Updated
        $this->notify(
            $request,
            'role_updated',
            'Role Updated',
            '{{performer_name}} updated role "{{role_name}}": ' . implode(' | ', $changes) . ' at {{timestamp}}.',
            ['role_name' => $role->name]
        );

        return response()->json([
            'message' => 'Role and permissions updated successfully',
            'role' => $role->load('permissions')
        ]);
    }

    /**
     * Delete a role (only if no users are assigned) + NOTIFICATION
     */
    public function destroy(Request $request, $roleId)
    {
        $role = Role::findOrFail($roleId);

        $userCount = User::role($role->name)->count();
        if ($userCount > 0) {
            return response()->json([
                'message' => 'Cannot delete role because it is assigned to users',
                'user_count' => $userCount
            ], 400);
        }

        $roleName = $role->name;
        $role->delete();

        // NOTIFICATION: Role Deleted
        $this->notify(
            $request,
            'role_deleted',
            'Role Deleted',
            '{{performer_name}} deleted role "{{role_name}}" at {{timestamp}}.',
            ['role_name' => $roleName]
        );

        return response()->json([
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * Assign role to a user + NOTIFICATION
     */
    public function assignRoleToUser(Request $request, User $user)
    {
        $request->validate(['role_name' => 'required|exists:roles,name']);

        $oldRoles = $user->roles->pluck('name')->toArray();
        $user->syncRoles([$request->role_name]);

        // NOTIFICATION: Role Assigned
        $this->notify(
            $request,
            'role_assigned_to_user',
            'Role Assigned to User',
            '{{performer_name}} assigned role "{{role_name}}" to user "{{user_name}}" at {{timestamp}}.',
            [
                'role_name'  => $request->role_name,
                'user_name'  => $user->name
            ]
        );

        return response()->json([
            'message' => 'Role assigned to user successfully'
        ]);
    }
}