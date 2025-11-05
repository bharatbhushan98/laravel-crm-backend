<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Batch extends Model {
    protected $fillable = [
        'product_id', 'batch_number', 'stock_level', 'expiry_date', 'status'
    ];

    public function product() {
        return $this->belongsTo(Product::class);
    }
}
