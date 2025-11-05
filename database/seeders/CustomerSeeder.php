<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Type;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $retailType = Type::where('type', 'Retail')->first();

        Customer::create([
            'name' => 'Apollo Pharmacy',
            'initials' => 'AP',
            'contact' => 'apollo@pharmacy.com',
            'phone' => '+91 98765 43210',
            'type_id' => $retailType->id,
            'status' => 'Active',
        ]);
    }
}
