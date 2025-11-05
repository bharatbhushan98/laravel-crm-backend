<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HsnCode extends Model {
    protected $fillable = ['category_id', 'hsn_code', 'gst_rate'];

    public function category() {
        return $this->belongsTo(Category::class);
    }
}
