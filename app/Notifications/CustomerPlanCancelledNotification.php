<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

class CustomerPlanCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;
 
    public function __construct(
        protected User $user, 
        protected $oldPlan,
    ){}

    public function via($notifiable): array
    {
        return ['database'];     
    }
    
    public function toDatabase($notifiable): array
    {
        return
        [
            'type'        => 'customer_plan_cancelled',
            'title'       => 'Customer Plan cancelled',
            'message'     => "{$this->user->name} cancelled their plan {$this->oldPlan}.",
            'user_id'     => $this->user->id,
            'old_plan'    => $this->oldPlan,
            'email'       => $this->user->email,
        ];
    }
}
