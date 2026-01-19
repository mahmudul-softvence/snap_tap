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
use Twilio\Rest\Client;

class SendReviewMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $reviewId;
    public bool $sendEmail;
    public bool $sendSms;
    public int $messageCount;
    public int $nextMessageHours;
    public int $maxRetries;

    public function __construct(
        int $reviewId,
        bool $sendEmail = true,
        bool $sendSms = false,
        int $messageCount = 1,
        int $nextMessageHours = 1,
        int $maxRetries = 5
    ) {
        $this->reviewId = $reviewId;
        $this->sendEmail = $sendEmail;
        $this->sendSms = $sendSms;
        $this->messageCount = $messageCount;
        $this->nextMessageHours = $nextMessageHours;
        $this->maxRetries = $maxRetries;
    }

    public function handle(): void
    {
        $review = Review::find($this->reviewId);

        if (!$review || $review->status === 'reviewed') {
            return;
        }

        $sent = false;

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

        if ($this->sendSms && $review->phone) {

            try {

                $account_sid = getenv("TWILIO_SID");
                $auth_token = getenv("TWILIO_TOKEN");
                $twilio_number = getenv("TWILIO_FROM");

                $client = new Client($account_sid, $auth_token);

                $client->messages->create($review->phone, [
                    'from' => $twilio_number,
                    'body' => $review->message
                ]);

                $sent = true;
            } catch (Exception $e) {
                Log::error('SMS failed: ' . $e->getMessage());
            }
        }

        if ($sent) {
            $review->retries += 1;
            $review->save();

            if ($this->messageCount < $this->maxRetries && $this->nextMessageHours > 0) {
                SendReviewMessageJob::dispatch(
                    $review->id,
                    $this->sendEmail,
                    $this->sendSms,
                    $this->messageCount + 1,
                    $this->nextMessageHours,
                    $this->maxRetries
                )->delay(now()->addHour($this->nextMessageHours));
            }
        }
    }
}
