<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\HsnCode;

class CategoryHsnSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Data for medical categories with HSN codes and GST rates
        // Based on GST council notifications and HSN classifications for India
        $data = [
            ['name' => 'Antibiotics', 'hsn_code' => '3004', 'gst_rate' => 12],
            ['name' => 'Analgesics', 'hsn_code' => '3004', 'gst_rate' => 12],
            ['name' => 'Gastroenterology', 'hsn_code' => '3004', 'gst_rate' => 12],
            ['name' => 'Diabetes', 'hsn_code' => '3004', 'gst_rate' => 12],
            ['name' => 'Vitamins & Supplements', 'hsn_code' => '3004', 'gst_rate' => 12],
            ['name' => 'Vaccines', 'hsn_code' => '3002', 'gst_rate' => 5],
            ['name' => 'Blood Products', 'hsn_code' => '3002', 'gst_rate' => 5],
            ['name' => 'Hormones', 'hsn_code' => '3004', 'gst_rate' => 12],
            ['name' => 'Oncology (Cancer Drugs)', 'hsn_code' => '3004', 'gst_rate' => 5],
            ['name' => 'Cardiology (Heart Medicines)', 'hsn_code' => '3004', 'gst_rate' => 12],
            ['name' => 'Dermatology (Skin Medicines)', 'hsn_code' => '3004', 'gst_rate' => 12],
            ['name' => 'Neurology (Brain Medicines)', 'hsn_code' => '3004', 'gst_rate' => 12],
            ['name' => 'Psychiatry', 'hsn_code' => '3004', 'gst_rate' => 12],
            ['name' => 'Ophthalmic (Eye Care)', 'hsn_code' => '3004', 'gst_rate' => 12],
            ['name' => 'ENT (Ear, Nose, Throat)', 'hsn_code' => '3004', 'gst_rate' => 12],
            ['name' => 'Respiratory (Inhalers, Cough Syrups)', 'hsn_code' => '3004', 'gst_rate' => 12],
            ['name' => 'Ayurvedic Medicines', 'hsn_code' => '3004', 'gst_rate' => 5],
            ['name' => 'Homeopathy Medicines', 'hsn_code' => '3004', 'gst_rate' => 5],
            ['name' => 'Surgical Dressings & Bandages', 'hsn_code' => '3005', 'gst_rate' => 12],
            ['name' => 'First Aid Kits & Other Goods', 'hsn_code' => '3006', 'gst_rate' => 12],
        ];

        // Loop through the data and insert or update records in the database
        foreach ($data as $item) {
            // Find or create the Category
            $category = Category::firstOrCreate(['name' => $item['name']]);

            // Find or create the HsnCode associated with the Category
            HsnCode::firstOrCreate([
                'category_id' => $category->id,
                'hsn_code'    => $item['hsn_code'],
                'gst_rate'    => $item['gst_rate'],
            ]);
        }
    }
}
