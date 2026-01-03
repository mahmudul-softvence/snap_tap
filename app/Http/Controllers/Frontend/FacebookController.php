<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\UserBusinessAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
            'scope'         => implode(',', $scope), // CHANGE: only valid scope
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
                'user_id' => auth()->id(), //  CHANGE: auth()->id() use when production
                'business_name' => $request->page_name,
                'access_token' => $request->page_token, // page access token
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
        $accounts = UserBusinessAccount::where('user_id', auth()->id()) // auth()->id()
            ->where('provider', 'facebook')
            ->where('status', 'connected')
            ->get();

        return response()->json([
            'success' => true,
            'pages' => $accounts,
        ]);
    }


    // public function reviews($pageId)
    // {
    //     $account = $this->getPageAccount($pageId);

    //     return Http::withToken($account->access_token)
    //         ->get("https://graph.facebook.com/v17.0/{$pageId}/ratings")
    //         ->json();
    // }

    public function reviews($pageId)
    {
        $account = $this->getPageAccount($pageId);

        if (!$account || !$account->access_token) {
            return response()->json([
                'success' => false,
                'message' => 'Page access token not found'
            ], 400);
        }

        $allReviews = [];
        $url = "https://graph.facebook.com/v17.0/{$pageId}/ratings";
        $params = [
            'fields' => 'id,created_time,recommendation_type,review_text,reviewer{name,id}',
            'limit' => 25, // Number of reviews per page
        ];

        do {
            $response = Http::withToken($account->access_token)
                ->get($url, $params)
                ->json();

            if (!isset($response['data'])) {
                break;
            }

            // Add current batch of reviews
            $allReviews = array_merge($allReviews, $response['data']);

            // Check for next page
            $url = $response['paging']['next'] ?? null;

            // Clear params after first call because 'next' URL already has them
            $params = [];
        } while ($url);

        return response()->json([
            'success' => true,
            'reviews' => $allReviews
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
        $account = UserBusinessAccount::where('user_id', auth()->id()) // auth()->id()
            ->where('provider', 'facebook')
            ->where('provider_account_id', $pageId)
            ->where('status', 'connected')
            ->first();

        if (!$account) {
            abort(401, 'Facebook page not connected');
        }

        return $account;
    }




    // --------For Production Facebook page connector methods--------
    // public function authUrl()
    // {
    //     $scope = [
    //         'pages_show_list',
    //         'pages_read_user_content',
    //         'pages_manage_engagement',
    //     ];

    //     $query = http_build_query([
    //         'client_id'     => config('services.facebook.client_id'),
    //         'redirect_uri'  => config('services.facebook.redirect'),
    //         'response_type' => 'code',
    //         'scope'         => implode(',', $scope),
    //         'auth_type'     => 'rerequest',
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'url' => "https://www.facebook.com/v17.0/dialog/oauth?$query",
    //     ]);
    // }

    // public function callback(Request $request)
    // {
    //     if (!$request->has('code')) {
    //         abort(400, 'Authorization code missing');
    //     }

    //     $tokenResponse = Http::retry(3, 200)->get(
    //         'https://graph.facebook.com/v17.0/oauth/access_token',
    //         [
    //             'client_id'     => config('services.facebook.client_id'),
    //             'client_secret' => config('services.facebook.client_secret'),
    //             'redirect_uri'  => config('services.facebook.redirect'),
    //             'code'          => $request->code,
    //         ]
    //     )->json();

    //     if (!isset($tokenResponse['access_token'])) {
    //         Log::error('Facebook token error', $tokenResponse);
    //         abort(400, 'Failed to retrieve access token');
    //     }

    //     $userToken = $tokenResponse['access_token'];

    //     $pages = Http::retry(3, 200)
    //         ->withToken($userToken)
    //         ->get('https://graph.facebook.com/v17.0/me/accounts')
    //         ->json('data');

    //     if (empty($pages)) {
    //         abort(403, 'No Facebook pages found');
    //     }

    //     Cache::put(
    //         $this->cacheKey(),
    //         collect($pages)->keyBy('id')->toArray(),
    //         now()->addMinutes(10)
    //     );

    //     return response()->json([
    //         'success' => true,
    //         'pages' => collect($pages)->map(fn($p) => [
    //             'id' => $p['id'],
    //             'name' => $p['name'],
    //             'category' => $p['category'] ?? null,
    //         ]),
    //     ]);
    // }

    // public function connectPage(Request $request)
    // {
    //     $request->validate([
    //         'page_id' => 'required|string',
    //     ]);

    //     $pages = Cache::get($this->cacheKey());

    //     if (!$pages || !isset($pages[$request->page_id])) {
    //         abort(401, 'Facebook session expired or invalid page');
    //     }

    //     $page = $pages[$request->page_id];

    //     $account = UserBusinessAccount::updateOrCreate(
    //         [
    //             'user_id' => auth()->id(),
    //             'provider' => 'facebook',
    //             'provider_account_id' => $page['id'],
    //         ],
    //         [
    //             'business_name' => $page['name'],
    //             'access_token' => encrypt($page['access_token']), // encrypted
    //             'token_expires_at' => now()->addDays(60),
    //             'status' => 'connected',
    //             'meta_data' => $page,
    //         ]
    //     );

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Facebook page connected successfully',
    //         'page' => $account,
    //     ]);
    // }

    // public function pages()
    // {
    //     return response()->json([
    //         'success' => true,
    //         'pages' => UserBusinessAccount::where('user_id', auth()->id())
    //             ->where('provider', 'facebook')
    //             ->where('status', 'connected')
    //             ->get(),
    //     ]);
    // }

    // public function reviews(string $pageId)
    // {
    //     $page = $this->getPage($pageId);

    //     return Http::retry(3, 200)
    //         ->withToken(decrypt($page->access_token))
    //         ->get("https://graph.facebook.com/v17.0/{$pageId}/ratings")
    //         ->json();
    // }

    // public function reply(Request $request)
    // {
    //     $request->validate([
    //         'page_id' => 'required|string',
    //         'review_id' => 'required|string',
    //         'comment' => 'required|string|max:4000',
    //     ]);

    //     $page = $this->getPage($request->page_id);

    //     $response = Http::retry(3, 200)
    //         ->withToken(decrypt($page->access_token))
    //         ->post(
    //             "https://graph.facebook.com/v17.0/{$request->review_id}/comments",
    //             ['message' => $request->comment]
    //         );

    //     if ($response->failed()) {
    //         Log::error('Facebook reply failed', $response->json());
    //         abort(400, 'Failed to send reply');
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Reply sent successfully',
    //     ]);
    // }

    // private function getPage(string $pageId)
    // {
    //     $page = UserBusinessAccount::where('user_id', auth()->id())
    //         ->where('provider', 'facebook')
    //         ->where('provider_account_id', $pageId)
    //         ->where('status', 'connected')
    //         ->first();

    //     if (!$page) {
    //         abort(403, 'Facebook page not connected');
    //     }

    //     return $page;
    // }

    // private function cacheKey(): string
    // {
    //     return 'facebook_pages_' . auth()->id();
    // }
    // --------------------------------------------------------------




}
