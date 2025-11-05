<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = [
        'supplier_id',
        'party_name',
        'invoice_number',
        'po_number',
        'receiving_date',
        'vehicle_number',
        'product_description',
        'goods_category',
        'rate',
        'igst',
        'cgst',
        'sgst',
        'round_off',
    ];

    public function supplier() {
        return $this->belongsTo(Supplier::class);
    }

    // ✅ A purchase can have many items
    public function items() {
        return $this->hasMany(PurchaseItem::class);
    }
}
