<?php

namespace App\Jobs;

use App\Models\UserBusinessAccount;
use App\Models\GetReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Jobs\ReplyToReviewJob;
use Illuminate\Support\Facades\Log;

class SyncPageReviewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public UserBusinessAccount $account) {}

    public function handle()
    {
        $nextUrl = "https://graph.facebook.com/v24.0/{$this->account->provider_account_id}/ratings";

        do {
            $response = Http::get($nextUrl, [
                'fields' => 'reviewer,rating,review_text,created_time,recommendation_type,open_graph_story',
                'access_token' => $this->account->access_token,
            ])->json();

            foreach ($response['data'] ?? [] as $item) {

                $reviewId = $item['open_graph_story']['id'] ?? null;
                if (!$reviewId) continue;

                $rating = $item['rating'] ?? match ($item['open_graph_story']['data']['recommendation_type'] ?? null) {
                    'positive' => 5,
                    'negative' => 1,
                    default => null,
                };

                $review = GetReview::updateOrCreate(
                    ['facebook_review_id' => $reviewId],
                    [
                        'user_id' => $this->account->user_id,
                        'page_id' => $this->account->provider_account_id,
                        'rating' => $rating,
                        'review_text' => $item['review_text']
                            ?? ($item['open_graph_story']['message'] ?? null),
                        'reviewed_at' => $item['created_time'],
                    ]
                );

                if ($review->wasRecentlyCreated) {
                    $review->update(['status' => 'pending']);
                    ReplyToReviewJob::dispatch($review)->delay(now()->addMinutes(1));
                }
            }

            $nextUrl = $response['paging']['next'] ?? null;
        } while ($nextUrl);
    }
}
