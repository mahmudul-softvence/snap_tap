<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\GetReview;
use App\Models\Review;
use App\Models\UserBusinessAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{
    public function dashboard()
    {
        $total_request = Review::where('user_id', Auth::id())->count();

        $reviewed_reviews = Review::where('user_id', Auth::id())
            ->where('status', 'reviewed')
            ->count();

        $response_rate = $total_request > 0
            ? round(($reviewed_reviews / $total_request) * 100, 2)
            : 0;

        $recent_request = Review::latest()->take(10)->get();

        $reviews = GetReview::where('user_id', Auth::id())
            ->orderBy('reviewed_at', 'desc')
            ->get()
            ->map(function ($review) {
                return [
                    'provider' => $review->provider,
                    'review_id' => $review->provider_review_id,
                    'page_id' => $review->page_id,
                    'review_text' => $review->review_text,
                    'rating' => $review->rating,
                    'recommendation_type' => $review->rating >= 4 ? 'positive' : 'negative',
                    'reviewer_name' => $review->reviewer_name,
                    'reviewer_avatar' => $review->reviewer_image,
                    'created_time' => \Carbon\Carbon::parse($review->reviewed_at)->format('Y-m-d H:i:s'),
                    'reply_status' => $review->status,
                    'reply_id' => $review->review_reply_id,
                    'reply_text' => $review->review_reply_text,
                    'replied_at' => $review->replied_at,
                ];
            });

        $recentReviews = $reviews->take(10)->values();
        $totalReview = $reviews->count();
        $avgRating = $totalReview > 0
            ? round($reviews->avg('rating'), 1)
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_request' => $total_request,
                'total_review' => $totalReview,
                'avg_rating' => $avgRating,
                'response_rate' => $response_rate,
                'recent_request' => $recent_request,
                'total' => $recentReviews->count(),
                'reviews' => $recentReviews,
            ],
        ]);
    }

    public function analytics()
    {
        $userId = Auth::id();

        $total_request = Review::where('user_id', $userId)->count();

        $currentMonth = Carbon::now()->month;
        $currentYear  = Carbon::now()->year;

        $reviews = collect([]);

        $facebookReviewCount = 0;
        $googleReviewCount   = 0;

        $dbReviews = GetReview::where('user_id', $userId)->get();

        foreach ($dbReviews as $review) {

            $createdAt = Carbon::parse($review->reviewed_at ?? $review->created_at);

            $reviewMonth = $createdAt->month;
            $reviewYear  = $createdAt->year;

            if ($reviewMonth === $currentMonth && $reviewYear === $currentYear) {
                if ($review->provider === 'facebook') {
                    $facebookReviewCount++;
                }

                if ($review->provider === 'google') {
                    $googleReviewCount++;
                }
            }

            $reviews->push([
                'rating' => (int) $review->rating,
                'provider' => $review->provider,
                'created_time' => $createdAt,
            ]);
        }

        $starBreakdown = collect([1, 2, 3, 4, 5])->mapWithKeys(function ($star) use ($reviews, $currentMonth, $currentYear) {

            $count = $reviews->filter(function ($r) use ($star, $currentMonth, $currentYear) {
                return $r['rating'] == $star
                    && Carbon::parse($r['created_time'])->month === $currentMonth
                    && Carbon::parse($r['created_time'])->year === $currentYear;
            })->count();

            return [$star => $count];
        });

        $total_review = $reviews->count();

        $avg_rating = $total_review > 0
            ? round($reviews->avg('rating'), 1)
            : 0;

        $months = collect();
        $now = Carbon::now()->startOfMonth();

        for ($i = 5; $i >= 0; $i--) {

            $month = $now->copy()->subMonths($i);

            $total = Review::where('user_id', $userId)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $reviewed = Review::where('user_id', $userId)
                ->where('status', 'reviewed')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $months->push([
                'month' => $month->format('M'),
                'year'  => $month->year,
                'total_requests' => $total,
                'reviewed_requests' => $reviewed,
                'response_rate' => $total > 0
                    ? round(($reviewed / $total) * 100, 2)
                    : 0,
            ]);
        }

        $recent_request = Review::latest()->take(10)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_request' => $total_request,
                'total_review'  => $total_review,
                'avg_rating'    => $avg_rating,

                'facebook_review_count_current_month' => $facebookReviewCount,
                'google_review_count_current_month'   => $googleReviewCount,

                'star_breakdown_current_month' => $starBreakdown,
                'last_6_months' => $months,

                'recent_request' => $recent_request,
            ],
        ]);
    }





// -------------------Live data from fb and google start here------------------
    // public function dashboard()
    // {

    //     $total_request = Review::where('user_id', Auth::id())->count();

    //     $reviewed_reviews = Review::where('user_id', Auth::id())
    //         ->where('status', 'reviewed')
    //         ->count();

    //     $response_rate = $total_request > 0
    //         ? round(($reviewed_reviews / $total_request) * 100, 2)
    //         : 0;

    //     $recent_request = Review::latest()->take(10)->get();


    //     $reviews = collect([]);

    //     $facebookPages = UserBusinessAccount::where('user_id', auth()->id())
    //         ->where('provider', 'facebook')
    //         ->where('status', 'connected')
    //         ->get();

    //     foreach ($facebookPages as $page) {

    //         $response = Http::get(
    //             "https://graph.facebook.com/v24.0/{$page->provider_account_id}/ratings",
    //             [
    //                 'fields' => 'reviewer,review_text,created_time,recommendation_type,
    //                          open_graph_story{comments.limit(10){from,message}}',
    //                 'access_token' => $page->access_token,
    //             ]
    //         )->json();

    //         foreach ($response['data'] ?? [] as $review) {

    //             $hasReply  = false;
    //             $replyText = null;

    //             if (!empty($review['open_graph_story']['comments']['data'])) {
    //                 foreach ($review['open_graph_story']['comments']['data'] as $comment) {
    //                     if (($comment['from']['id'] ?? null) == $page->provider_account_id) {
    //                         $hasReply  = true;
    //                         $replyText = $comment['message'] ?? null;
    //                         break;
    //                     }
    //                 }
    //             }

    //             $reviewerName = $review['reviewer']['name'] ?? 'Facebook User';

    //             $reviews->push([
    //                 'provider' => 'facebook',
    //                 'review_id' => $review['open_graph_story']['id'] ?? null,
    //                 'page_id' => $page->provider_account_id,
    //                 'review_text' =>
    //                 $review['review_text']
    //                     ?? ($review['open_graph_story']['data']['review_text'] ?? ''),
    //                 'rating' => $review['recommendation_type'] === 'positive' ? 5 : 1,
    //                 'recommendation_type' => $review['recommendation_type'] ?? 'positive',
    //                 'reviewer_name' => $reviewerName,
    //                 'reviewer_avatar' =>
    //                 'https://ui-avatars.com/api/?name=' .
    //                     urlencode($reviewerName) .
    //                     '&background=0d6efd&color=fff',
    //                 'created_time' =>
    //                 \Carbon\Carbon::parse($review['created_time'])->format('Y-m-d H:i:s'),
    //                 'reply_status' => $hasReply ? 'replied' : 'pending',
    //                 'reply_text' => $replyText,
    //             ]);
    //         }
    //     }

    //     $googleAccounts = UserBusinessAccount::where('user_id', auth()->id())
    //         ->where('provider', 'google')
    //         ->where('status', 'connected')
    //         ->get();

    //     foreach ($googleAccounts as $account) {

    //         $locations = Http::withToken($account->access_token)
    //             ->get("https://mybusinessbusinessinformation.googleapis.com/v1/{$account->provider_account_id}/locations")
    //             ->json();

    //         foreach ($locations['locations'] ?? [] as $location) {

    //             $response = Http::withToken($account->access_token)
    //                 ->get("https://mybusiness.googleapis.com/v4/{$location['name']}/reviews")
    //                 ->json();

    //             foreach ($response['reviews'] ?? [] as $review) {

    //                 $reviewerName = $review['reviewer']['displayName'] ?? 'Google User';
    //                 $replyText   = $review['reviewReply']['comment'] ?? null;

    //                 $reviews->push([
    //                     'provider' => 'google',
    //                     'review_id' => $review['name'] ?? null,
    //                     'page_id' => $account->provider_account_id,
    //                     'review_text' => $review['comment'] ?? '',
    //                     'rating' => $this->mapGoogleRating($review['starRating'] ?? null),
    //                     'recommendation_type' => ($review['starRating'] ?? 0) >= 4 ? 'positive' : 'negative',
    //                     'reviewer_name' => $reviewerName,
    //                     'reviewer_avatar' =>
    //                     $review['reviewer']['profilePhotoUrl']
    //                         ?? 'https://ui-avatars.com/api/?name=' .
    //                         urlencode($reviewerName) .
    //                         '&background=dc3545&color=fff',
    //                     'created_time' =>
    //                     isset($review['createTime'])
    //                         ? \Carbon\Carbon::parse($review['createTime'])->format('Y-m-d H:i:s')
    //                         : now()->format('Y-m-d H:i:s'),
    //                     'reply_status' => $replyText ? 'replied' : 'pending',
    //                     'reply_text' => $replyText,
    //                 ]);
    //             }
    //         }
    //     }

    //     $recentReviews = $reviews
    //         ->sortByDesc('created_time')
    //         ->take(10)
    //         ->values();

    //     $totalReview = $reviews->count();
    //     $avgRating = $totalReview > 0
    //         ? round($reviews->avg('rating'), 1)
    //         : 0;

    //     return response()->json([
    //         'success' => true,
    //         'data' => [
    //             'total_request' => $total_request,
    //             'total_review' => $totalReview,
    //             'avg_rating'   => $avgRating,
    //             'response_rate' => $response_rate,
    //             'recent_request' => $recent_request,
    //             'total' => $recentReviews->count(),
    //             'reviews' => $recentReviews,
    //         ],
    //     ]);
    // }

    // public function analytics()
    // {
    //     $total_request = Review::where('user_id', Auth::id())->count();

    //     $reviews = collect([]);

    //     $facebookReviewCount = 0;
    //     $googleReviewCount = 0;

    //     $currentMonth = Carbon::now()->month;
    //     $currentYear = Carbon::now()->year;

    //     $facebookPages = UserBusinessAccount::where('user_id', Auth::id())
    //         ->where('provider', 'facebook')
    //         ->where('status', 'connected')
    //         ->get();

    //     foreach ($facebookPages as $page) {
    //         $response = Http::get(
    //             "https://graph.facebook.com/v24.0/{$page->provider_account_id}/ratings",
    //             [
    //                 'fields' => 'reviewer,rating,review_text,recommendation_type,created_time',
    //                 'access_token' => $page->access_token,
    //             ]
    //         )->json();

    //         foreach ($response['data'] ?? [] as $review) {

    //             $star = $review['recommendation_type'] === 'positive' ? 5 : 1;

    //             $reviewMonth = Carbon::parse($review['created_time'])->month;
    //             $reviewYear  = Carbon::parse($review['created_time'])->year;

    //             if ($reviewMonth === $currentMonth && $reviewYear === $currentYear) {
    //                 $facebookReviewCount++;
    //             }

    //             $reviews->push([
    //                 'rating' => $star,
    //                 'provider' => 'facebook',
    //                 'created_time' => $review['created_time'],
    //             ]);
    //         }
    //     }

    //     $googleAccounts = UserBusinessAccount::where('user_id', Auth::id())
    //         ->where('provider', 'google')
    //         ->where('status', 'connected')
    //         ->get();

    //     foreach ($googleAccounts as $account) {
    //         $locations = Http::withToken($account->access_token)
    //             ->get("https://mybusinessbusinessinformation.googleapis.com/v1/{$account->provider_account_id}/locations")
    //             ->json();

    //         foreach ($locations['locations'] ?? [] as $location) {
    //             $response = Http::withToken($account->access_token)
    //                 ->get("https://mybusiness.googleapis.com/v4/{$location['name']}/reviews")
    //                 ->json();

    //             foreach ($response['reviews'] ?? [] as $review) {
    //                 $star = $this->mapGoogleRating($review['starRating'] ?? null);

    //                 $reviewMonth = isset($review['createTime'])
    //                     ? Carbon::parse($review['createTime'])->month
    //                     : null;
    //                 $reviewYear = isset($review['createTime'])
    //                     ? Carbon::parse($review['createTime'])->year
    //                     : null;

    //                 if ($reviewMonth === $currentMonth && $reviewYear === $currentYear) {
    //                     $googleReviewCount++;
    //                 }

    //                 $reviews->push([
    //                     'rating' => $star,
    //                     'provider' => 'google',
    //                     'created_time' => $review['createTime'] ?? now(),
    //                 ]);
    //             }
    //         }
    //     }

    //     $starBreakdown = collect([1, 2, 3, 4, 5])->mapWithKeys(function ($star) use ($reviews, $currentMonth, $currentYear) {
    //         $count = $reviews->filter(function ($r) use ($star, $currentMonth, $currentYear) {
    //             $rMonth = Carbon::parse($r['created_time'])->month;
    //             $rYear  = Carbon::parse($r['created_time'])->year;
    //             return $r['rating'] == $star && $rMonth === $currentMonth && $rYear === $currentYear;
    //         })->count();
    //         return [$star => $count];
    //     });

    //     $total_review = $reviews->count();
    //     $avg_rating = $total_review > 0
    //         ? round($reviews->avg('rating'), 1)
    //         : 0;

    //     $months = collect();
    //     $now = Carbon::now()->startOfMonth();

    //     for ($i = 5; $i >= 0; $i--) {
    //         $month = $now->copy()->subMonths($i);

    //         $total = Review::where('user_id', Auth::id())
    //             ->whereYear('created_at', $month->year)
    //             ->whereMonth('created_at', $month->month)
    //             ->count();

    //         $reviewed = Review::where('user_id', Auth::id())
    //             ->where('status', 'reviewed')
    //             ->whereYear('created_at', $month->year)
    //             ->whereMonth('created_at', $month->month)
    //             ->count();

    //         $months->push([
    //             'month' => $month->format('M'),
    //             'year'  => $month->year,
    //             'total_requests' => $total,
    //             'reviewed_requests' => $reviewed,
    //             'response_rate' => $total > 0
    //                 ? round(($reviewed / $total) * 100, 2)
    //                 : 0,
    //         ]);
    //     }

    //     $recent_request = Review::latest()->take(10)->get();


    //     return response()->json([
    //         'success' => true,
    //         'data' => [
    //             'total_request' => $total_request,
    //             'total_review' => $total_review,
    //             'avg_rating' => $avg_rating,
    //             'facebook_review_count_current_month' => $facebookReviewCount,
    //             'google_review_count_current_month' => $googleReviewCount,
    //             'star_breakdown_current_month' => $starBreakdown,
    //             'last_6_months' => $months,
    //             'recent_request' => $recent_request
    //         ],
    //     ]);
    // }

    // private function mapGoogleRating(?string $rating): int
    // {
    //     return match ($rating) {
    //         'ONE'   => 1,
    //         'TWO'   => 2,
    //         'THREE' => 3,
    //         'FOUR'  => 4,
    //         'FIVE'  => 5,
    //         default => 0,
    //     };
    // }
// -------------------Live data from fb and google end here-------------------


    // public function analytics()
    // {
    //     $total_request = Review::where('user_id', Auth::id())->count();

    //     $months = collect();
    //     $now = Carbon::now()->startOfMonth();

    //     for ($i = 5; $i >= 0; $i--) {
    //         $month = $now->copy()->subMonths($i);

    //         $total = Review::where('user_id', Auth::id())
    //             ->whereYear('created_at', $month->year)
    //             ->whereMonth('created_at', $month->month)
    //             ->count();

    //         $reviewed = Review::where('user_id', Auth::id())
    //             ->where('status', 'reviewed')
    //             ->whereYear('created_at', $month->year)
    //             ->whereMonth('created_at', $month->month)
    //             ->count();

    //         $months->push([
    //             'month' => $month->format('M'),
    //             'year'  => $month->year,
    //             'total_requests' => $total,
    //             'reviewed_requests' => $reviewed,
    //             'response_rate' => $total > 0
    //                 ? round(($reviewed / $total) * 100, 2)
    //                 : 0,
    //         ]);
    //     }

    //     $recent_request = Review::latest()->take(10)->get();

    //     $recent_reviews = null;

    //     return response()->json([
    //         'success' => true,
    //         'data' => [
    //             'total_request' => $total_request,
    //             'total_review' => 45,
    //             'avg_rating' => 45,
    //             'last_6_months' => $months,
    //             'recent_request' => $recent_request,
    //             'recent_reviews' => $recent_reviews
    //         ],
    //     ]);
    // }
}
