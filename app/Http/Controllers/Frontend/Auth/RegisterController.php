<?php

namespace App\Http\Controllers\Frontend\Auth;

use App\Http\Controllers\Controller;
use App\Models\BasicSetting;
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
            'email' => 'required|email',
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

        BasicSetting::create([
            'user_id' => $user->id
        ]);

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

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('MyApp')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User logged in successfully.',
                'data' => [
                    'token' => $token,
                    'name'  => $user->name,
                ],
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorised.',
                'errors' => [
                    'error' => 'Invalid email or password',
                ],
            ], 401);
        }
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

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully. Please login again.',
        ], 200);
    }
}
