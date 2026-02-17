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
use App\Services\FacebookAvatarService;
use App\Notifications\NewReviewNotification;
use App\Models\User;
use App\Models\BasicSetting;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncPageReviewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public UserBusinessAccount $account) {}

    public function handle()
    {
        $avatarService = new FacebookAvatarService('uploads/reviewers');

        // ====================== FACEBOOK ======================

        $facebookReviewIds = [];

        if ($this->account->provider === 'facebook') {

            $nextUrl = "https://graph.facebook.com/v24.0/{$this->account->provider_account_id}/ratings";

            do {
                $response = Http::get($nextUrl, [
                    'fields' => 'reviewer{name,picture},rating,review_text,created_time,recommendation_type,open_graph_story{comments{from{id},id,message,created_time}}',
                    'access_token' => $this->account->access_token,
                ])->json();

                foreach ($response['data'] ?? [] as $item) {

                    $reviewId = $item['open_graph_story']['id'] ?? null;
                    if (!$reviewId) continue;

                    $facebookReviewIds[] = $reviewId;

                    $reviewerName = $item['reviewer']['name'] ?? 'Facebook User';

                    $oldAvatar = null;
                    $existingReview = GetReview::where('provider', 'facebook')
                        ->where('provider_review_id', $reviewId)
                        ->first();

                    if ($existingReview) {
                        $oldAvatar = $existingReview->reviewer_image;
                    }

                    $reviewerAvatar = $avatarService->saveAvatar(
                        $item['reviewer']['picture']['data']['url'] ?? null,
                        $oldAvatar ? str_replace(url('/') . '/', '', $oldAvatar) : null
                    ) ?? "https://ui-avatars.com/api/?name=" . urlencode($reviewerName) . "&background=0d6efd&color=fff";

                    $rating = $item['rating'] ?? (($item['recommendation_type'] ?? 'positive') === 'negative' ? 1 : 5);

                    $replyId = null;
                    $replyText = null;
                    $repliedAt = null;
                    $status = 'pending';

                    if (!empty($item['open_graph_story']['comments']['data'])) {
                        foreach ($item['open_graph_story']['comments']['data'] as $comment) {
                            if (strval($comment['from']['id'] ?? '') === strval($this->account->provider_account_id)) {
                                $replyId = $comment['id'] ?? null;
                                $replyText = $comment['message'] ?? null;
                                $repliedAt = $comment['created_time'] ?? null;
                                $status = 'replied';
                                break;
                            }
                        }
                    }

                    $review = GetReview::updateOrCreate(
                        [
                            'provider' => 'facebook',
                            'provider_review_id' => $reviewId,
                        ],
                        [
                            'user_id' => $this->account->user_id,
                            'user_business_account_id' => $this->account->id,
                            'page_id' => $this->account->provider_account_id,
                            'reviewer_name' => $reviewerName,
                            'reviewer_image' => $reviewerAvatar,
                            'rating' => $rating,
                            'review_text' => $item['review_text'] ?? ($item['open_graph_story']['message'] ?? null),
                            'review_reply_id' => $replyId,
                            'review_reply_text' => $replyText,
                            'replied_at' => $repliedAt,
                            'status' => $status,
                            'reviewed_at' => $item['created_time'],
                        ]
                    );

                    // if ($review->wasRecentlyCreated && $status === 'pending') {
                    //     ReplyToReviewJob::dispatch($review)->delay(now()->addMinutes(1));
                    // }

                    if ($review->wasRecentlyCreated) {

                        $userId = $this->account->user_id;

                        $notifyEnabled = BasicSetting::where('user_id', $userId)
                            ->where('new_customer_review', 1)
                            ->exists();

                        if ($notifyEnabled) {
                            $user = User::find($userId);
                            if ($user) {
                                $user->notify(new NewReviewNotification($review));
                            }
                        }

                        if ($status === 'pending') {
                            ReplyToReviewJob::dispatch($review)->delay(now()->addMinutes(1));
                        }
                    }
                }

                $nextUrl = $response['paging']['next'] ?? null;
            } while ($nextUrl);

            if (!empty($facebookReviewIds)) {
                GetReview::where('provider', 'facebook')
                    ->where('page_id', $this->account->provider_account_id)
                    ->whereNotIn('provider_review_id', $facebookReviewIds)
                    ->delete();
            }
        }

        // ====================== GOOGLE ======================

        // $googleReviewIds = [];

        // if ($this->account->provider === 'google') {

        //     $googleAccounts = [$this->account];

        //     foreach ($googleAccounts as $account) {

        //         $response = Http::withToken($account->access_token)
        //             ->get("https://mybusiness.googleapis.com/v4/{$account->provider_account_id}/reviews")
        //             ->json();

        //         foreach ($response['reviews'] ?? [] as $reviewItem) {

        //             $providerReviewId = $reviewItem['name'] ?? null;
        //             if (!$providerReviewId) continue;

        //             $googleReviewIds[] = $providerReviewId;

        //             $reviewerName = $reviewItem['reviewer']['displayName'] ?? 'Google User';
        //             $reviewerAvatar = $reviewItem['reviewer']['profilePhotoUrl']
        //                 ?? "https://ui-avatars.com/api/?name=" . urlencode($reviewerName) . "&background=dc3545&color=fff";

        //             $rating = $reviewItem['starRating'] ?? null;

        //             $replyText = $reviewItem['reviewReply']['comment'] ?? null;
        //             $repliedAt = $reviewItem['reviewReply']['updateTime'] ?? null;
        //             $status = $replyText ? 'replied' : 'pending';

        //             $review = GetReview::updateOrCreate(
        //                 [
        //                     'provider' => 'google',
        //                     'provider_review_id' => $providerReviewId,
        //                 ],
        //                 [
        //                     'user_id' => $account->user_id,
        //                     'page_id' => $account->provider_account_id,
        //                     'reviewer_name' => $reviewerName,
        //                     'reviewer_image' => $reviewerAvatar,
        //                     'rating' => $rating,
        //                     'review_text' => $reviewItem['comment'] ?? '',
        //                     'review_reply_text' => $replyText,
        //                     'replied_at' => $repliedAt,
        //                     'status' => $status,
        //                     'reviewed_at' => $reviewItem['createTime'] ?? now()->format('Y-m-d H:i:s'),
        //                 ]
        //             );

        //             // if ($review->wasRecentlyCreated && $status === 'pending') {
        //             //     ReplyToReviewJob::dispatch($review)->delay(now()->addMinutes(1));
        //             // }

        //             if ($review->wasRecentlyCreated) {

        //                 $userId = $account->user_id;

        //                 $notifyEnabled = BasicSetting::where('user_id', $userId)
        //                     ->where('new_customer_review', 1)
        //                     ->exists();

        //                 if ($notifyEnabled) {
        //                     $user = User::find($userId);
        //                     if ($user) {
        //                         $user->notify(new NewReviewNotification($review));
        //                     }
        //                 }

        //                 if ($status === 'pending') {
        //                     ReplyToReviewJob::dispatch($review)->delay(now()->addMinutes(1));
        //                 }
        //             }
        //         }

        //         if (!empty($googleReviewIds)) {
        //             GetReview::where('provider', 'google')
        //                 ->where('page_id', $account->provider_account_id)
        //                 ->whereNotIn('provider_review_id', $googleReviewIds)
        //                 ->delete();
        //         }
        //     }
        // }




        // ====================== GOOGLE (MOCKED) ======================
        if ($this->account->provider === 'google') {

            $mockGoogleReviews = [
                [
                    'name' => 'google_rev_001',
                    'reviewer' => ['displayName' => 'Ariful Islam', 'profilePhotoUrl' => null],
                    'starRating' => 5,
                    'comment' => 'Excellent service! I am really happy with their support.',
                    'createTime' => now()->subDays(1)->toDateTimeString(),
                    'reviewReply' => null
                ],
                [
                    'name' => 'google_rev_002',
                    'reviewer' => ['displayName' => 'Sumaiya Akter', 'profilePhotoUrl' => null],
                    'starRating' => 4,
                    'comment' => 'The product quality is good but delivery was a bit late.',
                    'createTime' => now()->subDays(2)->toDateTimeString(),
                    'reviewReply' => [
                        'replyName' => 'google_reply_002',
                        'comment' => 'Thank you for your feedback! We are working on our delivery speed.',
                        'updateTime' => now()->subDay()->toDateTimeString()
                    ]
                ],
                [
                    'name' => 'google_rev_003',
                    'reviewer' => ['displayName' => 'Rakib Hossain', 'profilePhotoUrl' => null],
                    'starRating' => 3,
                    'comment' => 'Average experience. The behavior of the staff was not professional.',
                    'createTime' => now()->subDays(3)->toDateTimeString(),
                    'reviewReply' => [
                        'replyName' => 'google_reply_003',
                        'comment' => 'We are sorry to hear that. We will look into this matter immediately.',
                        'updateTime' => now()->subDays(2)->toDateTimeString()
                    ]
                ],
                [
                    'name' => 'google_rev_004',
                    'reviewer' => ['displayName' => 'Tahmina Jolly', 'profilePhotoUrl' => null],
                    'starRating' => 5,
                    'comment' => 'Amazing place! Definitely coming back again.',
                    'createTime' => now()->subDays(4)->toDateTimeString(),
                    'reviewReply' => null
                ],
                [
                    'name' => 'google_rev_005',
                    'reviewer' => ['displayName' => 'Tanvir Ahmed', 'profilePhotoUrl' => null],
                    'starRating' => 2,
                    'comment' => 'Highly disappointed. The food was cold and tasteless.',
                    'createTime' => now()->subDays(5)->toDateTimeString(),
                    'reviewReply' => [
                        'replyName' => 'google_reply_005',
                        'comment' => 'We apologize for the inconvenience. Please share your contact info so we can make it right.',
                        'updateTime' => now()->subDays(4)->toDateTimeString()
                    ]
                ]
            ];

            $googleReviewIds = [];

            foreach ($mockGoogleReviews as $reviewItem) {
                $providerReviewId = $reviewItem['name'] . '_' . $this->account->user_id;
                $googleReviewIds[] = $providerReviewId;

                $reviewerName = $reviewItem['reviewer']['displayName'];

                $reviewerAvatar = "https://ui-avatars.com/api/?name=" . urlencode($reviewerName) . "&background=dc3545&color=fff";

                $rating = $reviewItem['starRating'];

                // $replyId   = $reviewItem['reviewReply']['replyName'] ?? null;
                $replyId   = isset($reviewItem['reviewReply']) ? $reviewItem['reviewReply']['replyName'] . '_' . $this->account->user_id : null;
                $replyText = $reviewItem['reviewReply']['comment'] ?? null;
                $repliedAt = $reviewItem['reviewReply']['updateTime'] ?? null;
                $status    = $replyText ? 'replied' : 'pending';

                $review = GetReview::updateOrCreate(
                    [
                        'provider' => 'google',
                        'provider_review_id' => $providerReviewId,
                    ],
                    [
                        'user_id'           => $this->account->user_id,
                        'user_business_account_id' => $this->account->id,
                        'page_id'           => $this->account->provider_account_id,
                        'reviewer_name'     => $reviewerName,
                        'reviewer_image'    => $reviewerAvatar,
                        'rating'            => $rating,
                        'review_text'       => $reviewItem['comment'] ?? '',
                        'review_reply_id'   => $replyId,
                        'review_reply_text' => $replyText,
                        'replied_at'        => $repliedAt,
                        'status'            => $status,
                        'reviewed_at'       => $reviewItem['createTime'],
                    ]
                );

                if ($review->wasRecentlyCreated) {

                    $userId = $this->account->user_id;

                    $notifyEnabled = BasicSetting::where('user_id', $userId)
                        ->where('new_customer_review', 1)
                        ->exists();

                    if ($notifyEnabled) {
                        $user = User::find($userId);
                        if ($user) {
                            $user->notify(new NewReviewNotification($review));
                        }
                    }

                    if ($status === 'pending') {
                        ReplyToReviewJob::dispatch($review)->delay(now()->addMinutes(1));
                    }
                }
            }
        }
    }




    // public function handle()
    // {
    //     $avatarService = new FacebookAvatarService('uploads/reviewers');

    //     $nextUrl = "https://graph.facebook.com/v24.0/{$this->account->provider_account_id}/ratings";

    //     do {
    //         $response = Http::get($nextUrl, [
    //             'fields' => 'reviewer{name,picture},rating,review_text,created_time,recommendation_type,open_graph_story',
    //             'access_token' => $this->account->access_token,
    //         ])->json();

    //         foreach ($response['data'] ?? [] as $item) {

    //             $reviewId = $item['open_graph_story']['id'] ?? null;
    //             if (!$reviewId) continue;

    //             $rating = $item['rating'] ?? match ($item['open_graph_story']['data']['recommendation_type'] ?? null) {
    //                 'positive' => 5,
    //                 'negative' => 1,
    //                 default => null,
    //             };

    //             $reviewerAvatar = $avatarService->saveAvatar(
    //                 $item['reviewer']['picture']['data']['url'] ?? null
    //             ) ?? "https://ui-avatars.com/api/?name=" . urlencode($item['reviewer']['name'] ?? 'Unknown') . "&background=0d6efd&color=fff";



    //             $review = GetReview::updateOrCreate(
    //                 ['facebook_review_id' => $reviewId],
    //                 [
    //                     'user_id' => $this->account->user_id,
    //                     'page_id' => $this->account->provider_account_id,
    //                     'reviewer_name' => $item['reviewer']['name'] ?? 'Anonymous',
    //                     'reviewer_image' => $reviewerAvatar,
    //                     'rating' => $rating,
    //                     'review_text' => $item['review_text']
    //                         ?? ($item['open_graph_story']['message'] ?? null),
    //                     'reviewed_at' => $item['created_time'],
    //                 ]
    //             );

    //             if ($review->wasRecentlyCreated) {
    //                 $review->update(['status' => 'pending']);
    //                 ReplyToReviewJob::dispatch($review)->delay(now()->addMinutes(1));
    //             }
    //         }

    //         $nextUrl = $response['paging']['next'] ?? null;
    //     } while ($nextUrl);
    // }
}
