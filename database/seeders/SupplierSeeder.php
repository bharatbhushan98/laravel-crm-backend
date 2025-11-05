<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SupplierSeeder extends Seeder {
    public function run(): void {
        $suppliers = [
            ['name' => 'PharmaCorp Ltd', 'contact' => '1234567890', 'email' => 'info@pharmacorp.com'],
            ['name' => 'MediSupply Co', 'contact' => '9876543210', 'email' => 'support@medisupply.com'],
            ['name' => 'HealthSource Inc', 'contact' => '1122334455', 'email' => 'contact@healthsource.com'],
            ['name' => 'DiabetesCare Ltd', 'contact' => '5566778899', 'email' => 'care@diabetescare.com'],
        ];

        foreach ($suppliers as $sup) {
            Supplier::firstOrCreate(['name' => $sup['name']], $sup);
        }
    }
}
