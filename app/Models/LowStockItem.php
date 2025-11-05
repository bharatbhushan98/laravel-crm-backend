<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LowStockItem extends Model
{
    protected $fillable = [
        'product_id',
        'supplier_id',
        'requested_qty',
        'buy_price',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}