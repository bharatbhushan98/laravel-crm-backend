<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Type;

class TypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['Clinic', 'Retail', 'Wholesale'];

        foreach ($types as $t) {
            Type::create([
                'type' => $t,
            ]);
        }
    }
}
