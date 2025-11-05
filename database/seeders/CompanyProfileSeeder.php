<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CompanyProfile;

class CompanyProfileSeeder extends Seeder
{
    public function run(): void
    {
        CompanyProfile::create([
            'name'         => 'PharmaGrow CRM',
            'gst_number'   => '22AAAAA0000A1Z5',
            'address'      => 'Office No. 266, 2nd Floor, Block - B, Motia Plaza, Baddi, Distt. Solan (H.P.) India - 173205',
            'email'        => 'info@starreify.com',
            'phone'        => '91-1795-292032',
            'bank_details' => [
                'bank_name'      => 'Demo Bank',
                'account_number' => '1234567890',
                'branch_ifsc'    => 'DEMO000111',
            ],
            'logo'         => 'demo-logo.png',
        ]);
    }
}
