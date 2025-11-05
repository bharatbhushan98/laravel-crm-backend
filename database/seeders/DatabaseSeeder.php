<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {
    public function run(): void {
        $this->call([
            CategorySeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
            CategoryHsnSeeder::class,
            TypeSeeder::class,
            CustomerSeeder::class,
            CompanyProfileSeeder::class,
            PermissionSeeder::class,
        ]);
    }
}
