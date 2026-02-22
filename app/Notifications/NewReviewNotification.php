<?php

namespace App\Notifications;

use App\Models\GetReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class NewReviewNotification extends Notification implements ShouldQueue
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
            'type' => 'new_review',
            'title' => 'New Review Received',
            'message' => "{$this->review->reviewer_name} left a {$this->review->rating}★ review",
            'provider' => $this->review->provider,
            'page_id' => $this->review->page_id,
            'review_id' => $this->review->provider_review_id,
            'reviewed_at' => $this->review->reviewed_at,
        ];
    }
}
