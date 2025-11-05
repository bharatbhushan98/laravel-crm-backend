<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountRule extends Model
{
    protected $fillable = ['min_order_amount', 'discount_type', 'discount_value'];
}
