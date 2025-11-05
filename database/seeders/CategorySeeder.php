<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder {
    public function run(): void {
        $categories = [
            'Antibiotics',
            'Analgesics',
            'Gastroenterology',
            'Diabetes',
        ];

        foreach($categories as $cat) {
            Category::firstOrCreate(['name' => $cat]);
        }
    }
}
