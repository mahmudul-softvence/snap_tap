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
            ->where('method', $this->review->provider)
            ->first();

        if (!$aiAgent) {
            return;
        }

        $replyText = $this->generateReply($this->review->review_text, $aiAgent->content);

        $account = UserBusinessAccount::where('user_id', $this->review->user_id)
            ->where('provider_account_id', $this->review->page_id)
            ->where('status', 'connected')
            ->first();

        if (!$account) return;

        Http::withToken($account->access_token)
            ->post("https://graph.facebook.com/v17.0/{$this->review->facebook_review_id}/comments", [
                'message' => $replyText,
            ]);

        $aiAgent->increment('review_count');

        $this->review->update([
            'status' => 'ai_replied',
            'reply_text' => $replyText,
        ]);

        
    }

    protected function generateReply(string $reviewText, string $aiAgentContent = ''): string
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-5.2',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Instructions: {$aiAgentContent}
                        Review: {$reviewText}

                        Note:
                        - Do NOT include any personal information such as names, addresses, emails, or phone numbers.
                        - Only generate polite, professional feedback based on the review.
                        - Make sure the reply is in the SAME language as the review.
                        - Keep the reply between 20 to 40 words.
                        - Output ONLY the reply."
                ],
            ],
        ]);

        if (isset($response['choices'][0]['message']['content'])) {
            $replyText = $response['choices'][0]['message']['content'];
        } else {
            $replyText = 'Thank you for your review! We appreciate your feedback.';
        }

        return $replyText;
    }
}
