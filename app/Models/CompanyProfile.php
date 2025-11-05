<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $fillable = [
        'name', 'gst_number', 'address', 'email', 'phone', 'bank_details', 'logo'
    ];

    protected $casts = [
        'bank_details' => 'array',
    ];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
