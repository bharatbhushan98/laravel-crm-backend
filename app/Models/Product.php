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
    public function syncBatches($batches)
    {
        $existingBatchIds = $this->batches->pluck('id')->toArray();
        $newBatches = [];

        foreach ($batches as $batchData) {
            if (isset($batchData['id']) && in_array($batchData['id'], $existingBatchIds)) {
                // Update existing batch
                $batch = $this->batches()->find($batchData['id']);
                $batch->update([
                    'batch_number' => $batchData['batch_number'],
                    'stock_level' => $batchData['stock_level'],
                    'expiry_date' => $batchData['expiry_date'],
                ]);
            } else {
                // Create new batch
                $newBatches[] = new Batch([
                    'batch_number' => $batchData['batch_number'],
                    'stock_level'  => $batchData['stock_level'],
                    'expiry_date'  => $batchData['expiry_date'],
                ]);
            }
        }

        if (!empty($newBatches)) {
            $this->batches()->saveMany($newBatches);
        }

        // Delete removed batches
        $batchIds = array_filter(array_column($batches, 'id') ?? []);
        if (!empty($batchIds)) {
            $this->batches()->whereNotIn('id', $batchIds)->delete();
        }
    }
}