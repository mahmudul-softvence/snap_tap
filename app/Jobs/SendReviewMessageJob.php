<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Models\Review;
use Exception;
use Illuminate\Support\Facades\Log;



class SendReviewMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $reviewId;
    public bool $sendEmail;
    public bool $sendSms;
    public int $messageCount;
    public int $nextMessageSeconds;
    public int $maxRetries;

    public function __construct(int $reviewId, bool $sendEmail = true, bool $sendSms = false, int $messageCount = 1, int $nextMessageSeconds = 5, int $maxRetries = 5)
    {
        $this->reviewId = $reviewId;
        $this->sendEmail = $sendEmail;
        $this->sendSms = $sendSms;
        $this->messageCount = $messageCount;
        $this->nextMessageSeconds = $nextMessageSeconds;
        $this->maxRetries = $maxRetries;
    }

    public function handle(): void
    {
        $review = Review::find($this->reviewId);

        if (!$review || $review->status === 'reviewed') {
            return;
        }

        $sent = false;

        /**
         * Mail Sent
         */
        if ($this->sendEmail && $review->email) {
            try {
                Mail::html($review->message, function ($message) use ($review) {
                    $message->to($review->email)
                        ->subject('We’d Love Your Review!');
                });
                $sent = true;
            } catch (Exception $e) {
                Log::error('Email failed: ' . $e->getMessage());
            }
        }

        /**
         * SMS Sent
         */
        if ($this->sendSms && $review->phone) {
            try {
                // sms
                $sent = true;
            } catch (Exception $e) {
                Log::error('SMS failed: ' . $e->getMessage());
            }
        }

        if ($sent) {
            $review->retries += 1;
            $review->save();

            $nextMessageSeconds = (int) $this->nextMessageSeconds;

            if ($this->messageCount < $this->maxRetries && $nextMessageSeconds > 0) {
                Log::info("Scheduling message " . ($this->messageCount + 1) . " for review {$review->id} in {$nextMessageSeconds} seconds");

                SendReviewMessageJob::dispatch(
                    $review->id,
                    $this->sendEmail,
                    $this->sendSms,
                    $this->messageCount + 1,
                    $nextMessageSeconds,
                    $this->maxRetries
                )->delay(now()->addSeconds($nextMessageSeconds));
            }
        }
    }
}
