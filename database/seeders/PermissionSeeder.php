<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        // 🔹 Main Modules (Sidebar)
        $modules = [
            'dashboard',
            'suppliers',
            'customers',
            'orders',
            'inventory',
            'invoicing',
            'reports',
            'subscription',
            'help',
            'settings',
            'role-permissions',
            'employee-management',
        ];

        // 🔹 Submodules for Supplier section
        $submodules = [
            'products-suppliers',
            'product-price-list',
            'low-stock-items',
            'purchase-orders',
            'purchase-table',
        ];

        // Combine all modules
        $allModules = array_merge($modules, $submodules);

        // 🔹 Generate CRUD permissions for all modules
        $permissions = [];
        foreach ($allModules as $module) {
            $permissions[] = "{$module}-view";
            $permissions[] = "{$module}-create";
            $permissions[] = "{$module}-edit";
            $permissions[] = "{$module}-delete";
        }

        // 🔹 Add Custom Permissions
        $customPermissions = [
            'inventory-setPrice',
            'invoicing-send-invoice',
            'invoicing-download-invoice',
            'orders-set-auto-discount', // ✅ New permission for Set Auto Discount Rule button
        ];

        $permissions = array_merge($permissions, $customPermissions);

        // 🔹 Create or update each permission
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 🔹 Create or get Admin role
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);

        // 🔹 Assign all permissions to Admin
        $adminRole->syncPermissions($permissions);

        // 🔹 Create or get Admin user
        $adminUser = User::firstOrCreate(
            ['email' => 'bharat@pharmagrow.com'],
            [
                'name' => 'Bharat Bhushan',
                'password' => bcrypt('1234567890'),
            ]
        );

        // 🔹 Assign Admin role to user
        if (!$adminUser->hasRole('Admin')) {
            $adminUser->assignRole($adminRole);
        }

        echo "✅ Permissions created successfully (including custom ones).\n";
        echo "✅ Admin role synced and assigned to {$adminUser->email}\n";
    }
}