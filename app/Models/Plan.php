<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon; 
use Laravel\Cashier\SubscriptionItem;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

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
        'is_active',
        'allow_trial',
        'setup_fee',
        'trial_type',
        'auto_activate_after_trial',
        'platforms',
        'request_credits',
        'review_reply_credits',
        'total_ai_agent',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
        'trial_days' => 'integer',
        'allow_trial' => 'boolean',
        'auto_activate_after_trial' => 'boolean',
    ];

    public function hasTrial(): bool
    {
        return $this->trial_days > 0 && $this->allow_trial;
    }

    public function isFreeTrial(): bool
    {
        return $this->hasTrial() && $this->trial_type === 'free';
    }

    public function hasSetupFee(): bool
    {
        return $this->hasTrial() && $this->setup_fee > 0;
    }
    
    public function getTrialEndDate($startDate = null)
    {
        $start = $startDate ? Carbon::parse($startDate) : now();
        return $start->addDays($this->trial_days);
    }
  
    public function subscriptionItems()
    {
        return $this->hasMany(
            SubscriptionItem::class,
            'stripe_price',
            'stripe_price_id'
        );
    }

    public function subscriptions()
    {
        return $this->hasManyThrough(
            Subscription::class,
            SubscriptionItem::class,
            'stripe_price',        
            'id',                  
            'stripe_price_id',     
            'subscription_id'      
        );
    }

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
