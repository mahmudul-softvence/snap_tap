<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\SubscriptionItem  as CashierSubscriptionItem;

class SubscriptionItem extends CashierSubscriptionItem
{

     public function subscriptions()
     {
        return $this->belongsTo(Subscription::class);
     }
}
