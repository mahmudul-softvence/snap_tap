<?php

namespace App\Http\Controllers\Frontend\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpPasswordResetMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
    /**
     * Handle forgot password request
     *
     * Generates OTP and sends via email
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        try {

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = $request->email;

            $user = User::where('email', $email)->first();
            if (!$user) {
                return response()->json(['status' => false, 'error' => 'Email not found'], 404);
            }

            // Generate a 6-digit numeric OTP
            $otp = rand(100000, 999999);

            // Create new OTP with expiry
            PasswordResetOtp::create([
                'email' => $email,
                'otp' => $otp,
                'expires_at' => Carbon::now()->addMinutes(10)
            ]);
            //Send email with OTP
            Mail::to($email)->send(
                new OtpPasswordResetMail($user->name, $otp, 10)
            );

            return response()->json([
                'success' => true,
                'message' => 'OTP sent to your email address. Check your inbox.',
                'data' => [
                    'email' => $email,
                    'expires_in_minutes' => 10
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        try {

            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|digits:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }



            $otpRecord = PasswordResetOtp::where(['email' => $request->email, 'otp' => $request->otp])->first();

            if (!$otpRecord) {
                return response()->json(['status' => false, 'error' => 'Invalid OTP'], 400);
            }

            // Check expiration
            if (Carbon::parse($otpRecord->expires_at)->isPast()) {
                return response()->json(['status' => false, 'message' => 'OTP has expired.'], 400);
            }

            return response()->json(['status' => true, 'message' => 'OTP verified successfully'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP or email.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|digits:6',
                'password' => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }


            $otpRecord = PasswordResetOtp::where(['email' => $request->email, 'otp' => $request->otp])->first();

            if (!$otpRecord) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid OTP'
                ], 400);
            }

            // Update User Password
            User::where('email', $request->email)->update([
                'password' => bcrypt($request->password)
            ]);

            // Delete OTP record
            PasswordResetOtp::where('email', $request->email)
                ->delete();

            return response()->json([
                'status' => true,
                'message' => 'Password has been reset successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP or email.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
