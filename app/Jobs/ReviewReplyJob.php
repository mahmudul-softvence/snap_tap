<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

use App\Models\UserBusinessAccount;
use App\Models\GetReview;

class ReviewReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $provider, $reviewId, $pageId, $comment, $replyType, $userId;

    public $tries = 3;
    public $timeout = 40;

    public function __construct($provider, $reviewId, $pageId, $comment, $replyType, $userId)
    {
        $this->provider  = $provider;
        $this->reviewId  = $reviewId;
        $this->pageId    = $pageId;
        $this->comment   = $comment;
        $this->replyType = $replyType;
        $this->userId    = $userId;
    }

    public function handle()
    {
        $status = $this->replyType === 'ai_reply' ? 'ai_replied' : 'replied';

        /* ================= FACEBOOK ================= */

        if ($this->provider === 'facebook') {

            $page = UserBusinessAccount::where('user_id', $this->userId)
                ->where('provider', 'facebook')
                ->where('provider_account_id', $this->pageId)
                ->where('status', 'connected')
                ->first();

            if (!$page) return;

            $review = GetReview::where('provider', 'facebook')
                ->where('provider_review_id', $this->reviewId)
                ->where('page_id', $this->pageId)
                ->first();

            if (!$review) return;

            // old reply delete
            if (!empty($review->review_reply_id)) {
                Http::withToken($page->access_token)
                    ->delete("https://graph.facebook.com/v24.0/{$review->review_reply_id}");
            }

            // new reply
            $response = Http::withToken($page->access_token)
                ->post("https://graph.facebook.com/v24.0/{$this->reviewId}/comments", [
                    'message' => $this->comment,
                ]);

            if ($response->failed()) return;

            $review->update([
                // 'review_reply_id'   => $response->json('id'),
                // 'review_reply_text' => $this->comment,
                'replied_at'        => now(),
                'status'            => $status,
                'ai_agent_id'       => $this->replyType === 'ai_reply' ? $this->userId : null,
            ]);
        }

        /* ================= GOOGLE ================= */

        if ($this->provider === 'google') {

            $account = UserBusinessAccount::where('user_id', $this->userId)
                ->where('provider', 'google')
                ->where('provider_account_id', $this->pageId)
                ->where('status', 'connected')
                ->first();

            if (!$account) return;

            ///use for real data---------------------------------------------------------------------
            // $response = Http::withToken($account->access_token)
            //     ->put("https://mybusiness.googleapis.com/v4/{$this->reviewId}/reply", [
            //         'comment' => $this->comment,
            //     ]);

            // if ($response->failed()) return;
            //------------------------------------------------------------------------------------------

            GetReview::where('provider', 'google')
                ->where('provider_review_id', $this->reviewId)
                ->where('page_id', $this->pageId)
                ->update([
                    'review_reply_id'   => $this->reviewId,
                    'review_reply_text' => $this->comment,
                    'replied_at'        => now(),
                    'status'            => $status,
                    'ai_agent_id'       => $this->replyType === 'ai_reply' ? $this->userId : null,
                ]);

            \Log::info("Google Reply Mocked for Review: {$this->reviewId}");
        }
    }
}
