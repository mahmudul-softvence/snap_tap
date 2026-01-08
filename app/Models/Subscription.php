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
}
