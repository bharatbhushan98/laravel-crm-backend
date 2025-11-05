<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'initials',
        'contact',
        'phone',
        'type_id',
        'status',
        'address',
        'gst_number',  
    ];

    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // 🔥 Automatically generate initials
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            $customer->initials = self::generateInitials($customer->name);
        });

        static::updating(function ($customer) {
            if (empty($customer->initials)) {
                $customer->initials = self::generateInitials($customer->name);
            }
        });
    }

    private static function generateInitials($name)
    {
        if (!$name) return 'NA';

        $parts = preg_split('/\s+/', trim($name));
        $first = $parts[0] ?? '';
        $last = $parts[count($parts) - 1] ?? '';

        if ($first && $last && $first !== $last) {
            return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
        } elseif ($first) {
            return strtoupper(substr($first, 0, 2));
        } elseif ($last) {
            return strtoupper(substr($last, 0, 2));
        }

        return 'NA';
    }
}
