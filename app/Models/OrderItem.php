<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'batch_id',
        'quantity',
        'unit_price',
        'subtotal',
        'hsn_code',
        'gst_rate',
        'cgst_rate',
        'sgst_rate',
        'igst_rate',
    ];

    /**
     * Accessor: Calculate amounts dynamically
     */
    public function getCgstAmountAttribute()
    {
        return round(($this->subtotal * $this->cgst_rate) / 100, 2);
    }

    public function getSgstAmountAttribute()
    {
        return round(($this->subtotal * $this->sgst_rate) / 100, 2);
    }

    public function getIgstAmountAttribute()
    {
        return round(($this->subtotal * $this->igst_rate) / 100, 2);
    }

    public function getTaxAmountAttribute()
    {
        return $this->cgst_amount + $this->sgst_amount + $this->igst_amount;
    }

    public function getTotalAttribute()
    {
        return $this->subtotal + $this->tax_amount;
    }

    /**
     * Relationships
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
