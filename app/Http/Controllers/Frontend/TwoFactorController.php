<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TwoFactorController extends Controller
{

    // public function setup(Request $request)
    // {
    //     $user = $request->user();

    //     $google2fa = new Google2FA();

    //     $secret = $google2fa->generateSecretKey();

    //     $otpauthUrl = $google2fa->getQRCodeUrl(
    //         config('app.name'),
    //         $user->email,
    //         $secret
    //     );

    //     $user->update([
    //         'two_factor_secret' => encrypt($secret)
    //     ]);

    //     $qrCodeImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='
    //         . urlencode($otpauthUrl);

    //     return response()->json([
    //         'qr_code_url' => $qrCodeImageUrl,
    //         'secret'      => $secret
    //     ]);
    // }


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



    // public function setup(Request $request)
    // {
    //     $google2fa = new Google2FA();
    //     $secret = $google2fa->generateSecretKey();

    //     $qr = $google2fa->getQRCodeUrl(
    //         config('app.name'),
    //         $request->user()->email,
    //         $secret
    //     );

    //     $request->user()->update([
    //         'two_factor_secret' => encrypt($secret)
    //     ]);

    //     return response()->json([
    //         'qr_code' => $qr,
    //         'secret'  => $secret
    //     ]);
    // }

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

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $user->createToken('auth')->plainTextToken,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ], 200);
    }


    // STEP 4: Disable 2FA
    public function disable(Request $request)
    {
        $request->user()->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null
        ]);

        return response()->json(['message' => '2FA disabled']);
    }
}
