<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\GetReview;
use App\Models\Review;
use App\Models\UserBusinessAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();

        $hasAccess = $user->subscribed('default');

        $userAccounts = \App\Models\UserBusinessAccount::where('user_id', $user->id)
            ->whereIn('provider', ['facebook', 'google'])
            ->get();

        $providerStatus = [
            'facebook' => $userAccounts->where('provider', 'facebook')->first()->status ?? 'not_connected',
            'google'   => $userAccounts->where('provider', 'google')->first()->status ?? 'not_connected',
        ];

        $connectedProviders = \App\Models\UserBusinessAccount::where('user_id', $user->id)
            ->where('status', 'connected')
            ->pluck('provider')
            ->toArray();

        if ($hasAccess && !empty($connectedProviders)) {

            $total_request = Review::where('user_id', $user->id)->count();
            $reviewed_reviews = Review::where('user_id', $user->id)->where('status', 'reviewed')->count();
            $response_rate = $total_request > 0 ? round(($reviewed_reviews / $total_request) * 100, 2) : 0;
            $recent_request = $user->reviews()->latest()->take(10)->get();

            $reviews = GetReview::where('user_id', $user->id)
                ->whereIn('provider', $connectedProviders)
                ->orderBy('reviewed_at', 'desc')
                ->get()
                ->map(function ($review) {
                    return [
                        'provider' => $review->provider,
                        'review_id' => $review->provider_review_id,
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
            $avgRating = $totalReview > 0 ? round($reviews->avg('rating'), 1) : 0;
        } else {
            $total_request = 0;
            $totalReview = 0;
            $avgRating = 0;
            $response_rate = 0;
            $recent_request = [];
            $recentReviews = [];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_active_subscription' => $hasAccess,
                'is_account_connected' => !empty($connectedProviders),
                'provider_status' => $providerStatus,
                'total_request' => $total_request,
                'total_review' => $totalReview,
                'avg_rating' => $avgRating,
                'response_rate' => $response_rate,
                'recent_request' => $recent_request,
                'total' => count($recentReviews),
                'reviews' => $recentReviews,
            ],
        ]);
    }


    // public function analytics()
    // {
    //     $user = auth()->user();
    //     $userId = $user->id;

    //     $userAccounts = \App\Models\UserBusinessAccount::where('user_id', $userId)->get();

    //     $providerStatus = [
    //         'facebook' => $userAccounts->where('provider', 'facebook')->first()->status ?? 'not_connected',
    //         'google'   => $userAccounts->where('provider', 'google')->first()->status ?? 'not_connected',
    //     ];

    //     $hasAccess = $user->subscribed('default');

    //     $connectedProviders = $userAccounts->where('status', 'connected')
    //         ->pluck('provider')
    //         ->toArray();

    //     $total_request = 0;
    //     $total_review = 0;
    //     $avg_rating = 0;
    //     $response_rate = 0;
    //     $facebookReviewCount = 0;
    //     $googleReviewCount = 0;
    //     $starBreakdown = collect([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]);
    //     $months = collect();

    //     if ($hasAccess && !empty($connectedProviders)) {

    //         $total_request = Review::where('user_id', $userId)
    //             ->whereIn('provider', $connectedProviders)
    //             ->count();

    //         $currentMonth = \Carbon\Carbon::now()->month;
    //         $currentYear  = \Carbon\Carbon::now()->year;

    //         $dbReviews = GetReview::where('user_id', $userId)
    //             ->whereIn('provider', $connectedProviders)
    //             ->get();

    //         $allReviewsCollection = collect([]);

    //         $reviewedCount = GetReview::where('user_id', $userId)
    //             ->whereIn('provider', $connectedProviders)
    //             ->where('status', 'reviewed')
    //             ->count();

    //         $response_rate = $total_request > 0 ? round(($reviewedCount / $total_request) * 100, 2) : 0;

    //         foreach ($dbReviews as $review) {
    //             $createdAt = \Carbon\Carbon::parse($review->reviewed_at ?? $review->created_at);
    //             $reviewMonth = $createdAt->month;
    //             $reviewYear  = $createdAt->year;

    //             if ($reviewMonth === $currentMonth && $reviewYear === $currentYear) {
    //                 if ($review->provider === 'facebook') $facebookReviewCount++;
    //                 if ($review->provider === 'google') $googleReviewCount++;
    //             }

    //             $allReviewsCollection->push([
    //                 'rating' => (int) $review->rating,
    //                 'provider' => $review->provider,
    //                 'created_time' => $createdAt,
    //             ]);
    //         }

    //         $starBreakdown = collect([1, 2, 3, 4, 5])->mapWithKeys(function ($star) use ($allReviewsCollection, $currentMonth, $currentYear) {
    //             $count = $allReviewsCollection->filter(function ($r) use ($star, $currentMonth, $currentYear) {
    //                 return $r['rating'] == $star
    //                     && $r['created_time']->month === $currentMonth
    //                     && $r['created_time']->year === $currentYear;
    //             })->count();
    //             return [$star => $count];
    //         });

    //         $total_review = $allReviewsCollection->count();
    //         $avg_rating = $total_review > 0 ? round($allReviewsCollection->avg('rating'), 1) : 0;

    //         $now = \Carbon\Carbon::now()->startOfMonth();
    //         for ($i = 5; $i >= 0; $i--) {
    //             $month = $now->copy()->subMonths($i);

    //             $total = Review::where('user_id', $userId)
    //                 ->whereIn('provider', $connectedProviders)
    //                 ->whereYear('created_at', $month->year)
    //                 ->whereMonth('created_at', $month->month)
    //                 ->count();

    //             $reviewed = Review::where('user_id', $userId)
    //                 ->whereIn('provider', $connectedProviders)
    //                 ->where('status', 'reviewed')
    //                 ->whereYear('created_at', $month->year)
    //                 ->whereMonth('created_at', $month->month)
    //                 ->count();

    //             $months->push([
    //                 'month' => $month->format('M'),
    //                 'year'  => $month->year,
    //                 'sent_request' => $total,
    //                 'reviews_received' => $reviewed,
    //             ]);
    //         }
    //     } else {
    //         $now = \Carbon\Carbon::now()->startOfMonth();
    //         for ($i = 5; $i >= 0; $i--) {
    //             $month = $now->copy()->subMonths($i);
    //             $months->push([
    //                 'month' => $month->format('M'),
    //                 'year'  => $month->year,
    //                 'sent_request' => 0,
    //                 'reviews_received' => 0,
    //             ]);
    //         }
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'has_active_subscription' => $hasAccess,
    //         'is_account_connected' => !empty($connectedProviders),
    //         'provider_status' => $providerStatus,
    //         'data' => [
    //             'total_request' => $total_request,
    //             'total_review'  => $total_review,
    //             'avg_rating'    => $avg_rating,
    //             'response_rate' => $response_rate,
    //             'facebook_review_count_current_month' => $facebookReviewCount,
    //             'google_review_count_current_month'   => $googleReviewCount,
    //             'star_breakdown_current_month' => $starBreakdown,
    //             'last_6_months' => $months,
    //         ],
    //     ]);
    // }

    public function analytics(Request $request)
    {
        $user = auth()->user();
        $userId = $user->id;

        $selectedMonth = (int) $request->query('month', \Carbon\Carbon::now()->month);
        $selectedYear  = (int) $request->query('year', \Carbon\Carbon::now()->year);

        $currentMonthDate = \Carbon\Carbon::create($selectedYear, $selectedMonth, 1);
        $lastMonthDate = $currentMonthDate->copy()->subMonth();

        $userAccounts = \App\Models\UserBusinessAccount::where('user_id', $userId)->get();
        $providerStatus = [
            'facebook' => $userAccounts->where('provider', 'facebook')->first()->status ?? 'not_connected',
            'google'   => $userAccounts->where('provider', 'google')->first()->status ?? 'not_connected',
        ];

        $hasAccess = $user->subscribed('default');
        $connectedProviders = $userAccounts->where('status', 'connected')->pluck('provider')->toArray();

        $stats = [
            'total_request' => ['current' => 0, 'prev' => 0, 'change' => 0],
            'total_review'  => ['current' => 0, 'prev' => 0, 'change' => 0],
            'avg_rating'    => ['current' => 0, 'prev' => 0, 'change' => 0],
            'response_rate' => ['current' => 0, 'prev' => 0, 'change' => 0],
        ];

        $facebookReviewCount = 0;
        $googleReviewCount = 0;
        $starBreakdown = collect([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]);
        $months = collect();

        if ($hasAccess && !empty($connectedProviders)) {

            $stats['total_request']['current'] = Review::where('user_id', $userId)->whereIn('provider', $connectedProviders)
                ->whereMonth('created_at', $currentMonthDate->month)->whereYear('created_at', $currentMonthDate->year)->count();

            $stats['total_request']['prev'] = Review::where('user_id', $userId)->whereIn('provider', $connectedProviders)
                ->whereMonth('created_at', $lastMonthDate->month)->whereYear('created_at', $lastMonthDate->year)->count();

            $dbReviews = GetReview::where('user_id', $userId)->whereIn('provider', $connectedProviders)->get();

            $currentReviews = $dbReviews->filter(
                fn($r) =>
                \Carbon\Carbon::parse($r->reviewed_at ?? $r->created_at)->month == $currentMonthDate->month &&
                    \Carbon\Carbon::parse($r->reviewed_at ?? $r->created_at)->year == $currentMonthDate->year
            );

            $prevReviews = $dbReviews->filter(
                fn($r) =>
                \Carbon\Carbon::parse($r->reviewed_at ?? $r->created_at)->month == $lastMonthDate->month &&
                    \Carbon\Carbon::parse($r->reviewed_at ?? $r->created_at)->year == $lastMonthDate->year
            );

            $stats['total_review']['current'] = $currentReviews->count();
            $stats['total_review']['prev']    = $prevReviews->count();

            $stats['avg_rating']['current'] = $currentReviews->count() > 0 ? round($currentReviews->avg('rating'), 1) : 0;
            $stats['avg_rating']['prev']    = $prevReviews->count() > 0 ? round($prevReviews->avg('rating'), 1) : 0;

            $currReviewedCount = $currentReviews->where('status', 'reviewed')->count();
            $prevReviewedCount = $prevReviews->where('status', 'reviewed')->count();

            $stats['response_rate']['current'] = $stats['total_request']['current'] > 0 ? round(($currReviewedCount / $stats['total_request']['current']) * 100, 2) : 0;
            $stats['response_rate']['prev']    = $stats['total_request']['prev'] > 0 ? round(($prevReviewedCount / $stats['total_request']['prev']) * 100, 2) : 0;

            $stats['total_request']['change'] = $this->calculatePercentageChange($stats['total_request']['current'], $stats['total_request']['prev']);
            $stats['total_review']['change']  = $this->calculatePercentageChange($stats['total_review']['current'], $stats['total_review']['prev']);
            $stats['avg_rating']['change']    = round($stats['avg_rating']['current'] - $stats['avg_rating']['prev'], 1);
            $stats['response_rate']['change'] = round($stats['response_rate']['current'] - $stats['response_rate']['prev'], 2);

            foreach ($currentReviews as $review) {
                if ($review->provider === 'facebook') $facebookReviewCount++;
                if ($review->provider === 'google') $googleReviewCount++;
            }

            $starBreakdown = collect([1, 2, 3, 4, 5])->mapWithKeys(function ($star) use ($currentReviews) {
                return [$star => $currentReviews->where('rating', $star)->count()];
            });

            $now = \Carbon\Carbon::now()->startOfMonth();
            for ($i = 5; $i >= 0; $i--) {
                $m = $now->copy()->subMonths($i);
                $months->push([
                    'month' => $m->format('M'),
                    'year'  => $m->year,
                    'sent_request' => Review::where('user_id', $userId)->whereIn('provider', $connectedProviders)->whereYear('created_at', $m->year)->whereMonth('created_at', $m->month)->count(),
                    'reviews_received' => Review::where('user_id', $userId)->whereIn('provider', $connectedProviders)->where('status', 'reviewed')->whereYear('created_at', $m->year)->whereMonth('created_at', $m->month)->count(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_request' => $stats['total_request']['current'],
                'total_request_vs_last_month' => $stats['total_request']['change'],

                'total_review' => $stats['total_review']['current'],
                'total_review_vs_last_month' => $stats['total_review']['change'],

                'avg_rating' => $stats['avg_rating']['current'],
                'avg_rating_vs_last_month' => $stats['avg_rating']['change'],

                'response_rate' => $stats['response_rate']['current'],
                'response_rate_vs_last_month' => $stats['response_rate']['change'],

                'facebook_review_count_current_month' => $facebookReviewCount,
                'google_review_count_current_month'   => $googleReviewCount,
                'star_breakdown_current_month' => $starBreakdown,
                'last_6_months' => $months,
                'provider_status' => $providerStatus,
            ],
        ]);
    }


    private function calculatePercentageChange($current, $prev)
    {
        if ($prev == 0) return $current > 0 ? 100 : 0;
        return round((($current - $prev) / $prev) * 100, 2);
    }
}
