<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

use App\Models\UserBusinessAccount;
use App\Models\ReviewReply;
use App\Models\GetReview;

class ReviewReplyDeleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $provider, $pageId, $replyId, $userId;

    public $tries = 3;
    public $timeout = 30;

    public function __construct($provider, $pageId, $replyId, $userId)
    {
        $this->provider = $provider;
        $this->pageId   = $pageId;
        $this->replyId  = $replyId;
        $this->userId   = $userId;
    }

    public function handle()
    {
        /* ================= FACEBOOK ================= */

        if ($this->provider === 'facebook') {

            $page = UserBusinessAccount::where('user_id', $this->userId)
                ->where('provider', 'facebook')
                ->where('provider_account_id', $this->pageId)
                ->where('status', 'connected')
                ->first();

            if (!$page) return;

            Http::withToken($page->access_token)
                ->delete("https://graph.facebook.com/v17.0/{$this->replyId}");

            ReviewReply::where([
                'provider' => 'facebook',
                'page_id'  => $this->pageId,
                'reply_id' => $this->replyId,
                'user_id'  => $this->userId,
            ])->delete();

            GetReview::where([
                'provider' => 'facebook',
                'page_id'  => $this->pageId,
                'review_reply_id' => $this->replyId,
            ])->update([
                'review_reply_id'   => null,
                'review_reply_text' => null,
                'replied_at'        => null,
                'status'            => 'pending',
                'ai_agent_id'       => null,
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

            // user for real data-----------------------------------------------
            /*
            Http::withToken($account->access_token)
                ->delete("https://mybusiness.googleapis.com/v4/{$this->replyId}");
            */
            // ------------------------------------------------------------------

            ReviewReply::where([
                'provider' => 'google',
                'page_id'  => $this->pageId,
                'reply_id' => $this->replyId,
                'user_id'  => $this->userId,
            ])->delete();

            GetReview::where([
                'provider' => 'google',
                'page_id'  => $this->pageId,
                'review_reply_id' => $this->replyId,
            ])->update([
                'review_reply_id'   => null,
                'review_reply_text' => null,
                'replied_at'        => null,
                'status'            => 'pending',
                'ai_agent_id'       => null,
            ]);

            \Log::info("Google Reply Deleted (Mock Mode) for Reply ID: {$this->replyId}");
        }

        // if ($this->provider === 'google') {

        //     $account = UserBusinessAccount::where('user_id', $this->userId)
        //         ->where('provider', 'google')
        //         ->where('provider_account_id', $this->pageId)
        //         ->where('status', 'connected')
        //         ->first();

        //     if (!$account) return;

        //     Http::withToken($account->access_token)
        //         ->put("https://mybusiness.googleapis.com/v4/{$this->replyId}/reply", []);

        //     ReviewReply::where([
        //         'provider' => 'google',
        //         'page_id'  => $this->pageId,
        //         'reply_id' => $this->replyId,
        //         'user_id'  => $this->userId,
        //     ])->delete();

        //     GetReview::where([
        //         'provider' => 'google',
        //         'page_id'  => $this->pageId,
        //         'review_reply_id' => $this->replyId,
        //     ])->update([
        //         'review_reply_id'   => null,
        //         'review_reply_text' => null,
        //         'replied_at'        => null,
        //         'status'            => 'pending',
        //         'ai_agent_id'       => null,
        //     ]);
        // }
    }
}
