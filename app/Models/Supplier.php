<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['name', 'contact', 'email', 'address', 'priority'];

    // Legacy one-to-many relation
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Many-to-many product relation
    public function manyProducts()
    {
        return $this->belongsToMany(Product::class, 'product_supplier');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}