<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;
use App\Models\User;

class NewUserRegisteredNotification extends Notification
{
    use Queueable;

    public function __construct(public User $newUser) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'new_user',
            'title' => 'New User Registered',
            'message' => "{$this->newUser->name} has registered.",
            'user_id' => $this->newUser->id,
            'email' => $this->newUser->email,
        ];
    }
}
