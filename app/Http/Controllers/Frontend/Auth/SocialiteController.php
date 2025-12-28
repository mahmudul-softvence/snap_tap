<?php

namespace App\Http\Controllers\Frontend\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $user = User::firstOrCreate(
            ['email' => $socialUser->getEmail()],
            [
                'name'     => $socialUser->getName() ?? $socialUser->getNickname(),
                'password' => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
            ]
        );

        $user->forceFill([
            "{$provider}_id" => $socialUser->getId(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if ($user->wasRecentlyCreated) {
            event(new Registered($user));
        }

        $token = $user->createToken('social-login')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => $user,
        ]);
    }


    protected function validateProvider(string $provider): void
    {
        abort_unless(in_array($provider, $this->providers), 404);
    }
}
