<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'batch_id',
        'description',
        'hsn_code',
        'quantity',
        'unit_price',
        'discount',
        'cgst_rate',
        'sgst_rate',
        'igst_rate',
        'tax_rate',
        'line_total',
    ];

    protected $casts = [
        'quantity'    => 'float',
        'unit_price'  => 'float',
        'discount'    => 'float',
        'cgst_rate'   => 'float',
        'sgst_rate'   => 'float',
        'igst_rate'   => 'float',
        'tax_rate'    => 'float',
        'line_total'  => 'float',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withDefault(['name' => 'Unknown Product']);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class)->withDefault(['batch_number' => 'N/A']);
    }
}
