<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number', 'customer_id', 'order_id', 'company_profile_id', 'issue_date', 'due_date',
        'sub_total', 'discount_amount', 'tax_amount', 'shipping_amount',
        'total_amount', 'amount_paid', 'status', 'currency', 'notes', 'terms', 'meta'
    ];

    protected $casts = [
        'meta' => 'array',
        'issue_date' => 'date',
        'due_date' => 'date',
        'sub_total' => 'float',
        'discount_amount' => 'float',
        'tax_amount' => 'float',
        'shipping_amount' => 'float',
        'total_amount' => 'float',
        'amount_paid' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withDefault(['name' => 'Unknown Customer']);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function companyProfile()
    {
        return $this->belongsTo(CompanyProfile::class)->withDefault(['name' => 'Default Company']);
    }

    // Accessor for balance due
    public function getBalanceDueAttribute(): float
    {
        return round((float) $this->total_amount - (float) $this->amount_paid, 2);
    }
}