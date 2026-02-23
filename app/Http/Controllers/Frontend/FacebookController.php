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
use Illuminate\Support\Facades\Session;

class FacebookController extends Controller
{
    public function authUrl(Request $request)
    {
        $type = $request->type ?? 'web';

        $scope = [
            'pages_show_list',
            'pages_read_user_content',
            'pages_read_engagement',
            'pages_manage_engagement',
        ];

        $query = http_build_query([
            'client_id'     => config('services.facebook.page_client_id'),
            'redirect_uri'  => config('services.facebook.page_redirect'),
            'response_type' => 'code',
            'scope'         => implode(',', $scope),
            'auth_type'     => 'rerequest',
            'state'         => $type,
        ]);

        return response()->json([
            'success' => true,
            'url' => "https://www.facebook.com/v17.0/dialog/oauth?$query",
        ]);
    }


    public function callback(Request $request)
    {
        $type = $request->state ?? 'web';

        if (!$request->has('code')) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization code missing',
            ], 400);
        }

        $tokenResponse = Http::get('https://graph.facebook.com/v17.0/oauth/access_token', [
            'client_id'     => config('services.facebook.page_client_id'),
            'client_secret' => config('services.facebook.page_client_secret'),
            'redirect_uri'  => config('services.facebook.page_redirect'),
            'code'          => $request->code,
        ])->json();

        if (!isset($tokenResponse['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve access token',
            ], 400);
        }

        $shortLivedToken = $tokenResponse['access_token'];

        $longLivedResponse = Http::get('https://graph.facebook.com/v17.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.facebook.page_client_id'),
            'client_secret' => config('services.facebook.page_client_secret'),
            'fb_exchange_token' => $shortLivedToken,
        ])->json();

        if (!isset($longLivedResponse['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert to long-lived token',
            ], 400);
        }

        $userLongLivedToken = $longLivedResponse['access_token'];

        $pagesResponse = Http::withToken($userLongLivedToken)
            ->get('https://graph.facebook.com/v17.0/me/accounts')
            ->json();

        $pagesJson = urlencode(json_encode($pagesResponse['data']));

        $query = http_build_query([
            'user_id' => auth()->id(),
            'access_token' => $userLongLivedToken,
            'pages' => $pagesJson,
        ]);

        if ($type === 'app') {
            return response()->json([
                'success' => true,
                'message' => 'Facebook connected',
                'pages' => $pagesResponse['data'] ?? [],
                'access_token' => $userLongLivedToken,
            ]);
        }

        return redirect(config('app.frontend_url') . "/facebook/callback?$query");
    }


    public function connectPage(Request $request)
    {
        $request->validate([
            'page_id'    => 'required|string',
            'page_name'  => 'required|string',
            'page_token' => 'required|string',
        ]);

        $existingAccount = UserBusinessAccount::where('user_id', auth()->id())
            ->where('provider', 'facebook')
            ->first();

        if ($existingAccount) {
            if ($existingAccount->status === 'disconnect') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your Facebook account connection has been disabled by the admin. You cannot reconnect at this time. Please contact support.',
                ], 403);
            }

            if (trim($existingAccount->provider_account_id) != trim($request->page_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only reconnect your previously connected Facebook page.',
                    'allowed_page_id' => $existingAccount->provider_account_id,
                    'allowed_page_name' => $existingAccount->business_name,
                ], 403);
            }
        }

        $pageAlreadyConnected = UserBusinessAccount::where('provider', 'facebook')
            ->where('provider_account_id', $request->page_id)
            ->where('user_id', '!=', auth()->id())
            ->exists();

        if ($pageAlreadyConnected) {
            return response()->json([
                'success' => false,
                'message' => 'This Facebook page is already connected by another user.',
            ], 403);
        }

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
        $disconnectedAccount = UserBusinessAccount::where('user_id', auth()->id())
            ->where('provider', 'facebook')
            ->where('status', 'disconnect')
            ->first();

        if ($disconnectedAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Your account disconnect by admin, please contact with admin'
            ], 403);
        }

        $accounts = UserBusinessAccount::where('user_id', auth()->id())
            ->where('provider', 'facebook')
            ->where('status', 'connected')
            ->get();

        if ($accounts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No Facebook page connected',
                'pages'   => []
            ], 404);
        }

        $accounts = $accounts->map(function ($account) {
            try {
                $response = Http::get('https://graph.facebook.com/debug_token', [
                    'input_token' => $account->access_token,
                    'access_token' => config('services.facebook.page_client_id') . '|' . config('services.facebook.page_client_secret'),
                ]);

                $data = $response->json()['data'] ?? [];


                $account->is_token_valid = $data['is_valid'] ?? false;


                $account->token_expires_at_live = isset($data['expires_at'])
                    ? \Carbon\Carbon::createFromTimestamp($data['expires_at'])
                    : null;
            } catch (\Exception $e) {
                $account->is_token_valid = false;
                $account->token_expires_at_live = null;
            }

            return $account;
        });

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

            $rating = null;

            if (!empty($item['review_text'])) {
                $rating = 5;
            } else {
                $recommendationType = $item['open_graph_story']['data']['recommendation_type'] ?? null;
                $rating = match ($recommendationType) {
                    'positive' => 5,
                    'negative' => 1,
                    default => null,
                };
            }

            $review = GetReview::updateOrCreate(
                ['facebook_review_id' => $reviewId],
                [
                    'user_id' => auth()->id(),
                    'page_id' => $pageId,
                    'open_graph_story_id' => $reviewId,
                    'reviewer_name' => $item['reviewer']['name'] ?? null,
                    'rating' => $rating,
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
