<?php

namespace App\Http\Controllers\Frontend\Auth;

use App\Http\Controllers\Controller;
use App\Models\BasicSetting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    protected array $providers = ['google', 'facebook', 'github'];

    /**
     * Redirect URL for frontend (SPA / Mobile)
     */
    public function redirect(string $provider): JsonResponse
    {
        $this->validateProvider($provider);

        return response()->json([
            'url' => Socialite::driver($provider)->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Handle provider callback
     */
    public function callback(Request $request, string $provider): JsonResponse
    {
        $this->validateProvider($provider);

        $socialUser = Socialite::driver($provider)
            ->stateless()
            ->user();

        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user?->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong!',
            ], 403);
        }

        if (!$user) {
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'password' => '',
                'email_verified_at' => now(),
            ]);

            $user->assignRole('user');

            $user->basicSetting()->create();

            event(new Registered($user));

        }

        $user->forceFill([
            "{$provider}_id" => $socialUser->getId(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();



        $token = $user->createToken('social-login')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user,
            'role' => $user->getRoleNames(),
        ]);
    }


    protected function validateProvider(string $provider): void
    {
        abort_unless(in_array($provider, $this->providers), 404);
    }
}
