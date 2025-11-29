<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Models\Notification;
use App\Events\NewNotification;
use Carbon\Carbon;

class EmployeeController extends Controller
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
                    'employee_created' => 'UserPlus',
                    'employee_updated' => 'UserEdit',
                    'employee_deleted' => 'UserMinus',
                    default            => 'Bell'
                },
            ],
            'is_read' => false,
        ]);

        // CORRECT: toOthers()
        broadcast(new NewNotification($notification));
    }

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
     * Create new employee + NOTIFICATION
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

        // NOTIFICATION: Employee Created
        $this->notify(
            $request,
            'employee_created',
            'New Employee Added',
            '{{performer_name}} added employee "{{employee_name}}" with role "{{role}}" at {{timestamp}}.',
            [
                'employee_name' => $user->name,
                'role'          => $request->role
            ]
        );

        return response()->json([
            'message' => 'Employee created successfully',
            'data' => $user->load('roles')
        ], 201);
    }

    /**
     * Update employee details + NOTIFICATION
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

        $changes = [];

        if ($request->filled('name') && $request->name !== $user->name) {
            $changes[] = "name: '{$user->name}' to '{$request->name}'";
            $user->name = $request->name;
        }

        if ($request->filled('email') && $request->email !== $user->email) {
            $changes[] = "email: '{$user->email}' to '{$request->email}'";
            $user->email = $request->email;
        }

        if ($request->filled('password')) {
            $changes[] = "password updated";
            $user->password = Hash::make($request->password);
        }

        if ($request->has('role')) {
            $oldRole = $user->roles->pluck('name')->first() ?? 'None';
            $newRole = $request->role;
            if ($oldRole !== $newRole) {
                $changes[] = "role: '$oldRole' to '$newRole'";
            }
            $user->syncRoles([$request->role]);
        }

        $user->save();

        // NOTIFICATION: Employee Updated
        if (!empty($changes)) {
            $this->notify(
                $request,
                'employee_updated',
                'Employee Updated',
                '{{performer_name}} updated employee "{{employee_name}}": ' . implode(' | ', $changes) . ' at {{timestamp}}.',
                ['employee_name' => $user->name]
            );
        }

        return response()->json([
            'message' => 'Employee updated successfully',
            'data' => $user->load('roles')
        ]);
    }

    /**
     * Delete employee + NOTIFICATION
     */
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $name = $user->name;
        $role = $user->roles->pluck('name')->first() ?? 'None';

        $user->delete();

        // NOTIFICATION: Employee Deleted
        $this->notify(
            $request,
            'employee_deleted',
            'Employee Deleted',
            '{{performer_name}} deleted employee "{{employee_name}}" (Role: {{role}}) at {{timestamp}}.',
            [
                'employee_name' => $name,
                'role'          => $role
            ]
        );

        return response()->json([
            'message' => 'Employee deleted successfully'
        ]);
    }
}