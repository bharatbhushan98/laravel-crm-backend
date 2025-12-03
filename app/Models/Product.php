<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'product_code',
        'max_stock',
        'category_id',
        'supplier_id',
        'hsn_code',
        'gst_rate',
    ];

    public function category() {
        return $this->belongsTo(Category::class);
    }

    public function supplier() {
        return $this->belongsTo(Supplier::class);
    }

    public function suppliers() {
        return $this->belongsToMany(Supplier::class, 'product_supplier');
    }

    public function batches() {
        return $this->hasMany(Batch::class);
    }

    public function prices() {
        return $this->hasMany(ProductPrice::class);
    }

    public function currentPrice() {
        return $this->hasOne(ProductPrice::class)->latestOfMany('effective_date');
    }

    // 🔹 Sync Batches (Create/Update/Delete)
public function syncBatches(array $batches)
{
    $existing = $this->batches()->pluck('id')->toArray();

    foreach ($batches as $batch) {

        // UPDATE existing batch
        if (!empty($batch['id'])) {
            $this->batches()->where('id', $batch['id'])->update([
                'batch_number' => $batch['batch_number'],
                'stock_level'  => $batch['stock_level'],
                'has_expiry'   => $batch['has_expiry'],
                'expiry_date'  => $batch['has_expiry'] ? $batch['expiry_date'] : null,
            ]);

            // Remove this ID from existing so remaining are deleted later
            $existing = array_diff($existing, [$batch['id']]);
        }

        // INSERT new batch
        else {
            $this->batches()->create([
                'batch_number' => $batch['batch_number'],
                'stock_level'  => $batch['stock_level'],
                'has_expiry'   => $batch['has_expiry'],
                'expiry_date'  => $batch['has_expiry'] ? $batch['expiry_date'] : null,
            ]);
        }
    }

    // DELETE removed batches
    if (!empty($existing)) {
        $this->batches()->whereIn('id', $existing)->delete();
    }
}

}