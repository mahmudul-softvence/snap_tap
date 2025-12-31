<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\UserBusinessAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FacebookController extends Controller
{
    public function authUrl()
    {
        $query = http_build_query([
            'client_id' => config('services.facebook.client_id'),
            'redirect_uri' => config('services.facebook.redirect'),
            'response_type' => 'code',
            'scope' => 'pages_show_list,pages_read_user_content,pages_manage_posts',
            'auth_type' => 'rerequest',
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

        $tokenResponse = Http::get('https://graph.facebook.com/v17.0/oauth/access_token', [
            'client_id'     => config('services.facebook.client_id'),
            'client_secret' => config('services.facebook.client_secret'),
            'redirect_uri'  => config('services.facebook.redirect'),
            'code'          => $request->code,
        ])->json();

        if (!isset($tokenResponse['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve access token',
                'facebook_response' => $tokenResponse,
            ], 400);
        }

        $accessToken = $tokenResponse['access_token'];
        $expiresAt = now()->addSeconds($tokenResponse['expires_in'] ?? 3600);

        $pagesResponse = Http::withToken($accessToken)
            ->get('https://graph.facebook.com/v17.0/me/accounts')
            ->json();

        if (!isset($pagesResponse['data']) || empty($pagesResponse['data'])) {
            return response()->json([
                'success' => false,
                'message' => 'No Facebook pages found for this account',
                'facebook_response' => $pagesResponse,
            ], 403);
        }

        foreach ($pagesResponse['data'] as $page) {
            UserBusinessAccount::updateOrCreate(
                [
                    'provider' => 'facebook',
                    'provider_account_id' => $page['id'],
                ],
                [
                    'user_id' => 1,
                    // 'user_id' => auth()->id(),
                    'business_name' => $page['name'] ?? null,
                    'access_token' => $page['access_token'] ?? $accessToken,
                    'token_expires_at' => now()->addDays(60),
                    // 'token_expires_at' => $expiresAt,
                    'status' => 'connected',
                    // 'meta_data' => json_encode($page),
                    'meta_data' => $page,
                ]
            );
        }

        return redirect(config('app.frontend_url') . '/facebook/success');
    }

    private function getToken(): string
    {
        $account = UserBusinessAccount::where('user_id', 1)
            // $account = UserBusinessAccount::where('user_id', auth()->id())
            ->where('provider', 'facebook')
            ->where('status', 'connected')
            ->first();

        if (!$account) abort(401, 'Facebook not connected');

        return $account->access_token;
    }

    public function metaData()
    {
        $accounts = UserBusinessAccount::where('user_id', auth()->id())
            // $accounts = UserBusinessAccount::where('user_id', auth()->id())
            ->where('provider', 'facebook')
            ->get(['provider_account_id', 'business_name', 'meta_data']);

        return response()->json([
            'success' => true,
            'accounts' => $accounts
        ]);
    }

    public function pages()
    {
        return Http::withToken($this->getToken())
            ->get('https://graph.facebook.com/v17.0/me/accounts')
            ->json();
    }

    public function reviews($page)
    {
        return Http::withToken($this->getToken())
            ->get("https://graph.facebook.com/v17.0/{$page}/ratings")
            ->json();
    }

    public function reply(Request $request)
    {
        $request->validate([
            'review_id' => 'required|string',
            'page_id'   => 'required|string',
            'comment'   => 'required|string|max:4000',
        ]);

        $response = Http::withToken($this->getToken())
            ->post("https://graph.facebook.com/v17.0/{$request->review_id}/comments", [
                'message' => $request->comment,
            ]);

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reply',
                'facebook_response' => $response->json(),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reply sent successfully',
        ]);
    }
}
