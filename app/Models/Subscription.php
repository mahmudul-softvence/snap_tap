<?php

namespace App\Models;

use Laravel\Cashier\Subscription as CashierSubscription;
use Carbon\Carbon;

class Subscription extends CashierSubscription
{
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
            return $this->ends_at;
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
    }
}
