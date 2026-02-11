<?php

namespace App\Notifications;

use App\Models\GetReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AiReviewRepliedNotification extends Notification
{
    use Queueable;

    public function __construct(public GetReview $review) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'ai_reply_sent',
            'title' => 'AI Reply Sent',
            'message' => "{$this->review->reply_text}",
            'provider' => $this->review->provider,
            'page_id' => $this->review->page_id,
            'review_id' => $this->review->provider_review_id,
            'reviewed_at' => $this->review->reviewed_at,
        ];
    }
}
