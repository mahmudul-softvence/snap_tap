<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

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

        $recent_reviews = null;



        return response()->json([
            'success' => true,
            'data' => [
                'total_request' => $total_request,
                'total_review' => 45,
                'avg_rating' => 45,
                'response_rate' => $response_rate,
                'recent_request' => $recent_request,
                'recent_reviews' => $recent_reviews
            ],
        ]);
    }

    public function analytics()
    {
        $total_request = Review::where('user_id', Auth::id())->count();

        $months = collect();
        $now = Carbon::now()->startOfMonth();

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);

            $total = Review::where('user_id', Auth::id())
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $reviewed = Review::where('user_id', Auth::id())
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

        $recent_reviews = null;

        return response()->json([
            'success' => true,
            'data' => [
                'total_request' => $total_request,
                'total_review' => 45,
                'avg_rating' => 45,
                'last_6_months' => $months,
                'recent_request' => $recent_request,
                'recent_reviews' => $recent_reviews
            ],
        ]);
    }
}
