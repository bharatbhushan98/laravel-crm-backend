<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    protected $fillable = [
        'product_id',
        'buy_price',
        'sell_price',
        'discount_type',
        'discount_value',
        'effective_date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // ✅ Calculate Final Discounted Price (Only single discount)
    public function getFinalPrice()
    {
        $price = $this->sell_price;

        if ($this->discount_type === 'percentage' && $this->discount_value > 0) {
            $price -= ($price * $this->discount_value / 100);
        } elseif ($this->discount_type === 'fixed' && $this->discount_value > 0) {
            $price -= $this->discount_value;
        }

        return max($price, 0);
    }
}