<?php

namespace App\Jobs;

use App\Models\GetReview;
use App\Models\AiAgent;
use App\Models\BasicSetting;
use App\Models\UserBusinessAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            ->where('is_active', 1)
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


        // $response = Http::withToken($account->access_token)
        //     ->post("https://graph.facebook.com/v24.0/{$this->review->facebook_review_id}/comments", [
        //         'message' => $replyText,
        //     ]);


        if (!empty($review->review_reply_id)) {
            Http::withToken($account->access_token)
                ->delete("https://graph.facebook.com/v24.0/{$review->review_reply_id}");
        }

        $response = Http::withToken($account->access_token)
            ->post("https://graph.facebook.com/v24.0/{$this->review->provider_review_id}/comments", [
                'message' => $replyText,
            ]);




        if ($response->failed()) {

            Log::error('Facebook reply failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return;
        }

        $aiAgent->increment('review_count');

        $this->review->update([
            'status' => 'ai_replied',
            'review_reply_text' => $replyText,
            'replied_at'        => now(),
        ]);

        //notification
        $notifyEnabled = BasicSetting::where('user_id', $this->review->user_id)
            ->where('ai_reply', 1)
            ->exists();
        if ($notifyEnabled) {
            $this->review->user->notify(new \App\Notifications\AiReviewRepliedNotification($this->review));
        }
    }

    protected function generateReply(string $reviewText, string $aiAgentContent = ''): string
    {
        try {

            $response = Groq::chat()->completions()->create([
                'model' => 'llama-3.3-70b-versatile',
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

            $replyText = $response['choices'][0]['message']['content']
                ?? 'Thank you for your review! We appreciate your feedback.';


            return trim($replyText);
        } catch (\Exception $e) {
            return 'Thank you for your review! We appreciate your feedback.';
        }
    }
}
