<?php

namespace App\Models;

use Laravel\Cashier\Subscription as CashierSubscription;
use Carbon\Carbon;

class Subscription extends CashierSubscription
{
     protected $casts = [
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'quantity' => 'integer',
        'current_period_end' => 'datetime',
    ];

    public function renewOn(): ?Carbon
    {
        if ($this->onTrial() && $this->trial_ends_at) {
            return $this->trial_ends_at;
        }
        if ($this->canceled()) {
            return null;
        }
        return $this->currentPeriodEnd();
    }

    public function displayStartDate(): ?Carbon
    {
        if ($this->onTrial()) {
            return $this->created_at;
        }
        if ($this->active() || $this->onGracePeriod()) {
            return $this->trial_ends_at ?? $this->created_at;
        }
        return null;
    }

    public function displayEndDate(): ?Carbon
    {
        if ($this->onTrial()) {
            return $this->trial_ends_at;
        }
        if ($this->onGracePeriod()) {
            return $this->ends_at;
        }
        if ($this->active()) {
            return $this->currentPeriodEnd();
        }
        return null;
    }

    public function getPlan()
    {
        $priceId = $this->items->first()->stripe_price;
        $plan = Plan::where('stripe_price_id', $priceId)->first();
        $planName = $plan ? $plan->name : $this->type;
    
        if ($this->onTrial()) {
            $trialType = $this->trial_type ?? 'free';
            return ucfirst($trialType) . ' Trial - ' . $planName;
        }

        if ($this->active()) {
           return $planName;
        }
    }

   public function isActiveLike(): bool
   {
        return in_array($this->stripe_status, [
            'active',
            'trialing',
            'past_due',
        ]);
    }

    public function scopeActiveLike($query)
    {
        return $query->whereIn('stripe_status', [
            'active',
            'trialing',
            'past_due',
        ]);
    }

    public function plan()
    {
        return $this->belongsTo(
            Plan::class,
            'stripe_price',        
            'stripe_price_id'     
        );
    }


}
