<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

class CustomerPlanUpgradedNotification extends Notification implements ShouldQueue
{
    use Queueable;
 
    public function __construct(
        protected User $user, 
        protected $oldPlan,
        protected string $newPlan
    ){}

    public function via($notifiable): array
    {
        return ['database'];     
    }
    
    public function toDatabase($notifiable): array
    {
        return
        [
            'type'        => 'customer_plan_upgraded',
            'title'       => 'Customer Plan Upgraded',
            'message'     => "{$this->user->name} upgraded their plan from {$this->oldPlan} to {$this->newPlan}.",
            'user_id'     => $this->user->id,
            'old_plan'    => $this->oldPlan,
            'new_plan'    => $this->newPlan,
            'email'       => $this->user->email,
        ];
    }
}
