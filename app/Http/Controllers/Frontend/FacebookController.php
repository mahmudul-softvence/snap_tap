<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\GetReview;
use App\Models\UserBusinessAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FacebookController extends Controller
{
    public function authUrl()
    {
        $scope = [
            'pages_show_list',
            'pages_read_user_content',
            'pages_read_engagement',
            'pages_manage_engagement',
        ];

        $query = http_build_query([
            'client_id'     => config('services.facebook.client_id'),
            'redirect_uri'  => config('services.facebook.redirect'),
            'response_type' => 'code',
            'scope'         => implode(',', $scope),
            'auth_type'     => 'rerequest',
        ]);

        return response()->json([
            'success' => true,
            'url' => "https://www.facebook.com/v17.0/dialog/oauth?$query",
        ]);
    }

    public function callback(Request $request)
    {
        if (!$request->has('code')) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization code missing',
            ], 400);
        }

        $tokenResponse = Http::get(
            'https://graph.facebook.com/v17.0/oauth/access_token',
            [
                'client_id'     => config('services.facebook.client_id'),
                'client_secret' => config('services.facebook.client_secret'),
                'redirect_uri'  => config('services.facebook.redirect'),
                'code'          => $request->code,
            ]
        )->json();

        if (!isset($tokenResponse['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve access token',
                'facebook_response' => $tokenResponse,
            ], 400);
        }

        $userAccessToken = $tokenResponse['access_token'];

        $pagesResponse = Http::withToken($userAccessToken)
            ->get('https://graph.facebook.com/v17.0/me/accounts')
            ->json();

        if (empty($pagesResponse['data'])) {
            return response()->json([
                'success' => false,
                'message' => 'No Facebook pages found',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'user_access_token' => $userAccessToken,
            'pages' => $pagesResponse['data'],
        ]);
    }

    public function connectPage(Request $request)
    {
        $request->validate([
            'page_id'    => 'required|string',
            'page_name'  => 'required|string',
            'page_token' => 'required|string',
        ]);

        $account = UserBusinessAccount::updateOrCreate(
            [
                'provider' => 'facebook',
                'provider_account_id' => $request->page_id,
            ],
            [
                'user_id' => auth()->id(),
                'business_name' => $request->page_name,
                'access_token' => $request->page_token,
                'token_expires_at' => now()->addDays(60),
                'status' => 'connected',
                'meta_data' => [
                    'page_id' => $request->page_id,
                    'page_name' => $request->page_name,
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Facebook page connected successfully',
            'account' => $account,
        ]);
    }

    public function pages()
    {
        $accounts = UserBusinessAccount::where('user_id', auth()->id())
            ->where('provider', 'facebook')
            ->where('status', 'connected')
            ->get();

        return response()->json([
            'success' => true,
            'pages' => $accounts,
        ]);
    }

    public function reviews()
    {
        $pageId = UserBusinessAccount::where('user_id', auth()->id())
            ->where('status', 'connected')
            ->first()
            ->provider_account_id;

        $account = $this->getPageAccount($pageId);

        $response = Http::get("https://graph.facebook.com/v24.0/{$pageId}/ratings?fields=reviewer,rating,review_text,created_time,recommendation_type,open_graph_story&access_token={$account->access_token}");

        $data = $response->json();

        if (!isset($data['data']) || empty($data['data'])) {
            return response()->json([
                'success' => false,
                'message' => 'No reviews found',
            ], 404);
        }

        $savedCount = 0;

        foreach ($data['data'] as $item) {
            $reviewId = $item['open_graph_story']['id'] ?? null;

            if (!$reviewId) {
                continue;
            }

            $review = GetReview::updateOrCreate(
                ['facebook_review_id' => $reviewId],
                [
                    'user_id' => auth()->id(),
                    'page_id' => $pageId,
                    'open_graph_story_id' => $reviewId,
                    'reviewer_name' => $item['reviewer']['name'] ?? null,
                    'rating' => $item['rating'] ?? null,
                    'review_text' => $item['review_text'] ?? ($item['open_graph_story']['message'] ?? null),
                    'reviewed_at' => $item['created_time'] ?? now(),
                    'status' => 'pending',
                ]
            );

            if ($review->wasRecentlyCreated || $review->wasChanged()) {
                $savedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "$savedCount reviews synced successfully",
            'data' => $data['data'],
        ]);
    }


    public function reply(Request $request)
    {
        $request->validate([
            'review_id' => 'required|string',
            'page_id'   => 'required|string',
            'comment'   => 'required|string|max:4000',
        ]);

        $account = $this->getPageAccount($request->page_id);

        $response = Http::withToken($account->access_token)
            ->post("https://graph.facebook.com/v17.0/{$request->review_id}/comments", [
                'message' => $request->comment,
            ]);

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'facebook_response' => $response->json(),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reply sent successfully',
        ]);
    }

    private function getPageAccount($pageId)
    {
        $account = UserBusinessAccount::where('user_id', auth()->id())
            ->where('provider', 'facebook')
            ->where('provider_account_id', $pageId)
            ->where('status', 'connected')
            ->first();

        if (!$account) {
            abort(401, 'Facebook page not connected');
        }

        return $account;
    }
}
