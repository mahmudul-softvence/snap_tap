<?php

namespace App\Jobs;

use App\Models\GetReview;
use App\Models\AiAgent;
use App\Models\UserBusinessAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use LucianoTonet\GroqLaravel\Facades\Groq;
use OpenAI\Laravel\Facades\OpenAI;

class ReplyToReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public GetReview $review) {}

    public function handle()
    {
        if ($this->review->status !== 'pending') {
            return;
        }


        $aiAgent = AiAgent::where('user_id', $this->review->user_id)
            ->where('is_active', true)
            ->where('review_type', '>=', $this->review->rating)
            ->first();

        if (!$aiAgent) {
            return;
        }

        $replyText = $this->generateReply($this->review->review_text);

        $account = UserBusinessAccount::where('user_id', $this->review->user_id)
            ->where('provider_account_id', $this->review->page_id)
            ->first();

        if (!$account) return;

        Http::withToken($account->access_token)
            ->post("https://graph.facebook.com/v17.0/{$this->review->facebook_review_id}/comments", [
                'message' => $replyText,
            ]);

        $this->review->update([
            'status' => 'ai_replied',
            'reply_text' => $replyText,
        ]);
    }

    protected function generateReply(string $reviewText): string
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-5.2',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Based on the following review, generate a short, easy-to-read, and polite reply that is between 20 to 40 words. Avoid personal information like the user's name, and keep the tone simple and professional.\n\nReview: $reviewText\n\nPlease generate one of the following types of responses: \n1. A positive reply if the review is praising our service or product. \n2. A negative reply if the review is critical or complaining about the service or product. \n3. A help request reply if the review is asking for support or assistance."
                ],
            ]
        ]);

        if (isset($response['choices'][0]['message']['content'])) {
            $replyText = $response['choices'][0]['message']['content'];
        } else {
            $replyText = 'Thank you for your review! We appreciate your feedback.';
        }

        return $replyText;
    }
}
