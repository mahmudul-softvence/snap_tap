<?php

namespace App\Http\Controllers\Frontend\Auth;

use App\Http\Controllers\Controller;
use App\Models\BasicSetting;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewUserRegisteredNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{

    /**
     * Register API
     *
     * @return \Illuminate\Http\Response
     */

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' =>  'required|email|unique:users,email',
            'password' => 'required',
            'c_password' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $input = $request->all();
        $input['password'] = bcrypt($input['password']);
        $input['password_add_first_time'] = true;
        $user = User::create($input);
        $user->assignRole('user');

        $user->basicSetting()->create();

        $notifyEnabled = Setting::where('key', 'new_customer_singup_n')
            ->where('value', '1')
            ->exists();

        if ($notifyEnabled) {
            $superAdmin = User::role('super_admin')->first();
            $superAdmin->notify(new NewUserRegisteredNotification($user));
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'A verification link has been sent to your email. Please verify your email to complete the registration.',
        ], 201);
    }

    /**
     * Login API
     *
     * @return \Illuminate\Http\Response
     */

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::validate($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();

            return response()->json([
                'success' => false,
                'verified' => false,
                'message' => 'Please verify your email. We sent a verification email again.',
            ], 403);
        }

        Auth::attempt($credentials);
        $user = Auth::user();

        if ($user->two_factor_enabled) {
            return response()->json([
                'success' => true,
                'two_factor_required' => true,
                'message' => 'Two-factor authentication required.',
                'data' => [
                    'user_id' => $user->id,
                ],
            ], 200);
        }

        $token = $user->createToken('MyApp')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User logged in successfully.',
            'data' => [
                'token' => $token,
                'name'  => $user->name,
                'role' => $user->getRoleNames(),
            ],
        ], 200);
    }



    /**
     * Logout API
     *
     * @return \Illuminate\Http\Response
     */

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'User logged out successfully.',
        ], 200);
    }

    /**
     * Change Password API
     *
     * @return \Illuminate\Http\Response
     */

    public function change_pwd(Request $request): JsonResponse
    {
        $user = $request->user();

        $mustCheckCurrentPassword = $user->password_add_first_time == 1;

        $rules = [
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if ($mustCheckCurrentPassword) {
            $rules['current_password'] = ['required', 'string'];
        }

        $request->validate($rules);

        if (
            $mustCheckCurrentPassword &&
            !Hash::check($request->current_password, $user->password)
        ) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->password = Hash::make($request->new_password);
        $user->password_add_first_time = 1;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ], 200);
    }

    /**
     * Verify user's email.
     *
     * @param  int  $id, $hash
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function verify_email($id, $hash, Request $request)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link.'], 403);
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return view('frontend.email_verified');
    }



    /**
     * Resend email verification link.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function resend_verification(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['success' => true, 'message' => 'Verification link resent!']);
    }
}
