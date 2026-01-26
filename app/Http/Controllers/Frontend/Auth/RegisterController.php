<?php

namespace App\Http\Controllers\Frontend\Auth;

use App\Http\Controllers\Controller;
use App\Models\BasicSetting;
use App\Models\Setting;
use App\Models\User;
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
        $user = User::create($input);
        $user->assignRole('user');

        $user->basicSetting()->create();

        $notifyEnabled = Setting::where('key', 'new_customer_singup_n')
            ->where('value', '1')
            ->exists();

        if ($notifyEnabled) {
            $superAdmin = User::role('super_admin')->first();
            $superAdmin->notify(new \App\Notifications\NewUserRegisteredNotification($user));
        }

        $token = $user->createToken('MyApp')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully.',
            'data' => [
                'token' => $token,
                'name'  => $user->name,
            ],
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

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorised.',
                'errors' => [
                    'error' => 'Invalid email or password',
                ],
            ], 401);
        }

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

        $hasPassword = Hash::check($request->current_password, '');

        $rules = [
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if ($hasPassword) {
            $rules['current_password'] = ['required', 'string'];
        }

        $request->validate($rules);

        if ($hasPassword && !Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        // $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ], 200);
    }
}
