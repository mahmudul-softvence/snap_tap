<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Mail;
use App\Mail\TwoFactorCodeMail;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    public function setup(Request $request)
    {
        $user = $request->user();
        $google2fa = new Google2FA();


        $secret = $google2fa->generateSecretKey();


        $user->update([
            'two_factor_secret' => encrypt($secret)
        ]);


        $otpauthUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );


        $qrCode = QrCode::format('svg')->size(250)->generate($otpauthUrl);
        $qrCodeDataUri = 'data:image/svg+xml;base64,' . base64_encode($qrCode);


        return response()->json([
            'qr_code' => $qrCodeDataUri,
            'secret'  => $secret
        ]);
    }



    // STEP 2: Confirm OTP
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6'
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json([
                'message' => '2FA secret not found. Please setup 2FA first.'
            ], 422);
        }

        try {
            $secret = decrypt($user->two_factor_secret);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid 2FA secret. Please re-setup 2FA.'
            ], 422);
        }

        $google2fa = new \PragmaRX\Google2FA\Google2FA();

        if (! $google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => 'Invalid OTP'], 422);
        }

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now()
        ]);

        return response()->json(['message' => '2FA enabled']);
    }



    // STEP 3: Login OTP Verify
    public function loginVerify(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'code' => 'required|digits:6'
        ]);

        $user = User::findOrFail($request->user_id);
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $verified = false;

        if ($user->two_factor_secret) {
            try {
                $secret = decrypt($user->two_factor_secret);

                if ($google2fa->verifyKey($secret, $request->code)) {
                    $verified = true;
                }
            } catch (\Exception $e) {
            }
        }

        if (
            !$verified &&
            $user->two_factor_email_code == $request->code &&
            $user->two_factor_email_expires_at?->isFuture()
        ) {
            $verified = true;
            $user->two_factor_email_code = null;
            $user->save();
        }

        if ($verified) {
            return response()->json([
                'success' => true,
                'message' => $user->two_factor_secret ?
                    'Login successful via Authenticator App' :
                    'Login successful via Email 2FA',
                'data' => [
                    'token' => $user->createToken('auth')->plainTextToken,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                ],
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid code or code has expired.'
        ], 422);
    }



    // STEP 4: Disable 2FA
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid password'
            ], 403);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null
        ]);

        return response()->json([
            'message' => '2FA disabled successfully'
        ]);
    }


    // public function disable(Request $request)
    // {
    //     $request->user()->update([
    //         'two_factor_secret' => null,
    //         'two_factor_enabled' => false,
    //         'two_factor_confirmed_at' => null
    //     ]);

    //     return response()->json(['message' => '2FA disabled']);
    // }



    public function sendEmailCode(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $user = User::findOrFail($request->user_id);

        $code = rand(100000, 999999);
        $user->two_factor_email_code = $code;
        $user->two_factor_email_expires_at = now()->addMinutes(2);
        $user->save();

        $platformName = Setting::where('key', 'platform_name')->value('value');

        Mail::to($user->email)->send(new TwoFactorCodeMail($code, $platformName));

        return response()->json(['message' => 'Email 2FA code has been sent.']);
    }
}
