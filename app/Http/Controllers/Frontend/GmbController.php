<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\UserBusinessAccount;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GmbController extends Controller
{
    //Google GMB OAuth Authorization URL
    public function authUrl()
    {
        $clientId    = Setting::get('google_client_id');
        $redirectUrl = Setting::get('google_business_redirect')
            ?: url('/api/google/gmb/callback');

        $query = http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUrl,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/business.manage',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);

        return response()->json([
            'success' => true,
            'url'     => 'https://accounts.google.com/o/oauth2/v2/auth?' . $query,
        ]);
    }

    
    //OAuth Callback
    public function callback(Request $request)
    {
        if (!$request->has('code')) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization code missing',
            ], 400);
        }

        $clientId     = Setting::get('google_client_id');
        $clientSecret = Setting::get('google_client_secret');
        $redirectUrl  = Setting::get('google_business_redirect')
            ?: url('/api/google/gmb/callback');

        $tokenResponse = Http::asForm()->post(
            'https://oauth2.googleapis.com/token',
            [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => $redirectUrl,
                'grant_type'    => 'authorization_code',
                'code'          => $request->code,
            ]
        )->json();

        if (!isset($tokenResponse['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve access token',
                'google_response' => $tokenResponse,
            ], 400);
        }

        $accessToken  = $tokenResponse['access_token'];
        $refreshToken = $tokenResponse['refresh_token'] ?? null;
        $expiresAt    = now()->addSeconds($tokenResponse['expires_in'] ?? 3600);

        $accountsResponse = Http::withToken($accessToken)
            ->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts')
            ->json();

        if (empty($accountsResponse['accounts'])) {
            return response()->json([
                'success' => false,
                'message' => 'No Google Business Profile account found OR API access not approved yet',
                'google_response' => $accountsResponse,
            ], 403);
        }

        $account = $accountsResponse['accounts'][0];

        UserBusinessAccount::updateOrCreate(
            [
                'provider' => 'google',
                'provider_account_id' => $account['name'],
            ],
            [
                'user_id'          => auth()->id(),
                'business_name'    => $account['accountName'] ?? null,
                'access_token'     => $accessToken,
                'refresh_token'    => $refreshToken,
                'token_expires_at' => $expiresAt,
                'meta_data'        => $account,
                'status'           => 'connected',
            ]
        );

        return redirect(config('app.frontend_url') . '/gmb/success');
    }


    //Get valid Google access token
    private function getToken(): string
    {
        $account = UserBusinessAccount::where('user_id', auth()->id())
            ->where('provider', 'google')
            ->where('status', 'connected')
            ->first();

        if (!$account) {
            abort(401, 'Google Business Profile not connected');
        }

        if ($account->token_expires_at && now()->greaterThan($account->token_expires_at)) {

            if (!$account->refresh_token) {
                abort(401, 'Google token expired. Please reconnect your account.');
            }

            $refreshResponse = Http::asForm()->post(
                'https://oauth2.googleapis.com/token',
                [
                    'client_id'     => Setting::get('google_client_id'),
                    'client_secret' => Setting::get('google_client_secret'),
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $account->refresh_token,
                ]
            )->json();

            if (!isset($refreshResponse['access_token'])) {
                abort(401, 'Failed to refresh Google access token.');
            }

            $account->update([
                'access_token'     => $refreshResponse['access_token'],
                'token_expires_at' => now()->addSeconds($refreshResponse['expires_in'] ?? 3600),
            ]);

            return $refreshResponse['access_token'];
        }

        return $account->access_token;
    }


    //List Google Business Accounts
    public function accounts()
    {
        return Http::withToken($this->getToken())
            ->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts')
            ->json();
    }


    //List Locations under an Account
    public function locations($account)
    {
        return Http::withToken($this->getToken())
            ->get("https://mybusinessbusinessinformation.googleapis.com/v1/{$account}/locations")
            ->json();
    }


    //List Reviews of a Location
    public function reviews($location)
    {
        return Http::withToken($this->getToken())
            ->get("https://mybusiness.googleapis.com/v4/{$location}/reviews")
            ->json();
    }

    //Reply to a Review
    public function reply(Request $request)
    {
        $request->validate([
            'review_name' => 'required|string',
            'comment'     => 'required|string|max:4000',
        ]);

        $response = Http::withToken($this->getToken())
            ->put(
                "https://mybusiness.googleapis.com/v4/{$request->review_name}/reply",
                [
                    'comment' => $request->comment,
                ]
            );

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reply',
                'google_response' => $response->json(),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reply sent successfully',
        ]);
    }


    ///Mock Account create for google
    public function createMockGoogle(Request $request)
    {
        $userId = $request->user_id;

        $existingAccount = \App\Models\UserBusinessAccount::where('user_id', $userId)
            ->where('provider', 'google')
            ->first();

        if ($existingAccount && $existingAccount->status === 'disconnect') {
            return response()->json([
                'success' => false,
                'message' => 'Your Google account connection has been disabled by the admin. You cannot reconnect at this time. Please contact support.',
            ], 403);
        }

        $dynamicProviderId = 'gmb_123456_' . $userId;

        UserBusinessAccount::updateOrCreate(
            ['user_id' => $userId, 'provider' => 'google'],
            [
                'provider_account_id' => $dynamicProviderId,
                'business_name' => 'Test Business User ' . $userId,
                'access_token' => 'mock_token',
                'status' => 'connected',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Connect GMB Profile, created for User ID: $userId"
        ]);
    }


    ///mock account GMB
    public function googleAccounts()
    {
        $account = UserBusinessAccount::where('user_id', auth()->id())
            ->where('provider', 'google')
            ->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'No Google Business found. Please connect your account.',
                'accounts' => []
            ], 404);
        }

        if ($account->status === 'disconnect') {
            return response()->json([
                'success' => false,
                'status_type' => 'admin_suspended',
                'message' => 'Your Google account has been disconnected by the admin. Please contact support.',
                'accounts' => [$account],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'accounts' => [$account],
        ]);
    }

}
