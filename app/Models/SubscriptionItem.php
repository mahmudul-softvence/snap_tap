<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\SubscriptionItem  as CashierSubscriptionItem;

class SubscriptionItem extends CashierSubscriptionItem
{
      public function plan()
      {
            return $this->belongsTo(
                  Plan::class,
                  'stripe_price',
                  'stripe_price_id'
            );
      }
}
