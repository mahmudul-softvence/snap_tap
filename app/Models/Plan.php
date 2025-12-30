<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'stripe_price_id',
        'stripe_product_id',
        'price',
        'currency',
        'interval',
        'interval_count',
        'trial_days',
        'description',
        'features',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }

    public function getIntervalTextAttribute()
    {
        if ($this->interval_count > 1) {
            return "Every {$this->interval_count} {$this->interval}s";
        }
        return ucfirst($this->interval) . 'ly';
    }

}
