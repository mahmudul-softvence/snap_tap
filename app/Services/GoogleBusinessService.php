<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleBusinessService
{
    private function config()
    {
        return config('services.google_gmb');
    }

    public function oauthUrl()
{
    $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $this->config()['client_id'],
        'redirect_uri' => $this->config()['redirect'],
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/business.manage',
        'access_type' => 'offline',
        'prompt' => 'consent',
    ]);

    \Log::info('GMB OAuth URL', ['url' => $url]);

    return $url;
}

    // public function oauthUrl()
    // {
    //     return response()->json([
    //         'url' => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    //             'client_id' => $this->config()['client_id'],
    //             'redirect_uri' => $this->config()['redirect'],
    //             'response_type' => 'code',
    //             'scope' => implode(' ', [
    //                 'https://www.googleapis.com/auth/business.manage',
    //                 'https://www.googleapis.com/auth/userinfo.email',
    //                 'https://www.googleapis.com/auth/userinfo.profile',
    //             ]),
    //             'access_type' => 'offline',
    //             'prompt' => 'consent',
    //         ])
    //     ])->original['url'];
    // }

    public function tokenFromCode($code)
    {
        return Http::asForm()->post(
            'https://oauth2.googleapis.com/token',
            [
                'client_id' => $this->config()['client_id'],
                'client_secret' => $this->config()['client_secret'],
                'redirect_uri' => $this->config()['redirect'],
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]
        )->json();
    }

    public function refreshToken($refreshToken)
    {
        return Http::asForm()->post(
            'https://oauth2.googleapis.com/token',
            [
                'client_id' => $this->config()['client_id'],
                'client_secret' => $this->config()['client_secret'],
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]
        )->json();
    }

    public function accounts($token)
    {
        return Http::withToken($token)
            ->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts')
            ->json();
    }

    public function locations($token, $accountId)
    {
        return Http::withToken($token)
            ->get("https://mybusinessbusinessinformation.googleapis.com/v1/{$accountId}/locations")
            ->json();
    }

    public function reviews($token, $accountId, $locationId)
    {
        return Http::withToken($token)
            ->get("https://mybusiness.googleapis.com/v4/{$accountId}/locations/{$locationId}/reviews")
            ->json();
    }

    public function reply($token, $accountId, $locationId, $reviewId, $comment)
    {
        return Http::withToken($token)
            ->put("https://mybusiness.googleapis.com/v4/{$accountId}/locations/{$locationId}/reviews/{$reviewId}/reply", [
                'comment' => $comment
            ]);
    }
}
