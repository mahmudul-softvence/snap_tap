<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserBusinessAccount;
use App\Services\GoogleBusinessService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Crypt;

class GoogleBusinessController extends Controller
{
    // public function __construct()
    // {
    //     // ensure user always authenticated
    //     $this->middleware('auth:sanctum');
    // }

    public function redirect(GoogleBusinessService $service)
    { 
        return redirect()->away($service->oauthUrl());
    }

    /**
     * Google OAuth callback
     */
    public function callback(Request $request, GoogleBusinessService $service)
    {
        if (!$request->has('code')) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization code missing'
            ], 400);
        }

        // 1. Exchange code for token
        $token = $service->tokenFromCode($request->code);

        if (!isset($token['access_token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get access token'
            ], 400);
        }

        // 2. Fetch business accounts
        $accounts = $service->accounts($token['access_token']);

        if (isset($accounts['error'])) {
            return response()->json([
                'success' => false,
                'message' => $accounts['error']['message']
            ], 403);
        }

        if (empty($accounts['accounts'])) {
            return response()->json([
                'success' => false,
                'code' => 'NO_GMB',
                'message' => 'No Google Business Profile found'
            ], 422);
        }

        // 3. Use first account (industry standard)
        $account = $accounts['accounts'][0];

        // 4. Fetch locations
        $locations = $service->locations(
            $token['access_token'],
            $account['name']
        );

        if (isset($locations['error'])) {
            return response()->json([
                'success' => false,
                'message' => $locations['error']['message']
            ], 403);
        }

        // 5. Save / Update DB
        $businessAccount = UserBusinessAccount::updateOrCreate(
            [
                'provider' => 'google',
                'provider_account_id' => $account['name'],
            ],
            [
                'user_id' => auth()->id(),
                'business_name' => $account['accountName'] ?? null,
                'access_token' => Crypt::encryptString($token['access_token']),
                'refresh_token' => isset($token['refresh_token'])
                    ? Crypt::encryptString($token['refresh_token'])
                    : null,
                'token_expires_at' => now()->addSeconds($token['expires_in']),
                'meta_data' => $locations,
                'status' => 'connected',
            ]
        );

        return response()->json([
            'success' => true,
            'business_account_id' => $businessAccount->id
        ]);
    }

    /**
     * Get reviews (supports multi-location)
     * ?location_id=locations/xxxx
     */
    public function reviews(Request $request, $id, GoogleBusinessService $service)
    {
        $acc = UserBusinessAccount::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'connected')
            ->firstOrFail();

        $token = $this->getValidToken($acc, $service);

        $locationId = $request->get('location_id')
            ?? ($acc->meta_data['locations'][0]['name'] ?? null);

        if (!$locationId) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 422);
        }

        return $service->reviews(
            $token,
            $acc->provider_account_id,
            $locationId
        );
    }

    /**
     * Reply to a review
     */
    public function reply(
        Request $request,
        $id,
        $reviewId,
        GoogleBusinessService $service
    ) {
        $request->validate([
            'comment' => 'required|string|max:4000',
            'location_id' => 'nullable|string'
        ]);

        $acc = UserBusinessAccount::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'connected')
            ->firstOrFail();

        $token = $this->getValidToken($acc, $service);

        $locationId = $request->location_id
            ?? ($acc->meta_data['locations'][0]['name'] ?? null);

        if (!$locationId) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 422);
        }

        $service->reply(
            $token,
            $acc->provider_account_id,
            $locationId,
            $reviewId,
            $request->comment
        );

        return response()->json([
            'success' => true
        ]);
    }

    /**
     * Disconnect Google Business (required for verification)
     */
    public function disconnect($id)
    {
        $acc = UserBusinessAccount::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $acc->update([
            'status' => 'disconnected'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Google Business disconnected'
        ]);
    }

    /**
     * Handle token expiry automatically
     */
    private function getValidToken(
        UserBusinessAccount $acc,
        GoogleBusinessService $service
    ) {
        if (
            $acc->token_expires_at &&
            now()->lessThan($acc->token_expires_at)
        ) {
            return Crypt::decryptString($acc->access_token);
        }

        // refresh token
        $newToken = $service->refreshToken(
            Crypt::decryptString($acc->refresh_token)
        );

        if (!isset($newToken['access_token'])) {
            abort(401, 'Unable to refresh Google token');
        }

        $acc->update([
            'access_token' => Crypt::encryptString($newToken['access_token']),
            'token_expires_at' => now()->addSeconds($newToken['expires_in']),
        ]);

        return $newToken['access_token'];
    }
}
