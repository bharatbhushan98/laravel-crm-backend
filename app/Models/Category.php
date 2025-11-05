<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model {
    protected $fillable = ['name'];

    public function products() {
        return $this->hasMany(Product::class);
    }

    public function hsnCode() {
        return $this->hasOne(HsnCode::class); // 1 Category -> 1 HSN code
    }
}
