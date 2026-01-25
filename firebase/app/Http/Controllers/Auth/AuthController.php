<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Kreait\Laravel\Firebase\Facades\Firebase;

class AuthController extends Controller
{
    private $auth;
    public function __construct()
    {
        $this->auth = Firebase::auth();
    }

    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
                'password' => 'required|string|min:8',
            ]);

            $user = $this->auth->createUser([
                'displayName' => $request['name'],
                'email' => $request['email'],
                'password' => $request['password']
            ]);

            $actionCodeSettings = [
                'continueUrl' => env('FIREBASE_CONTINUE_URL')
            ];

            $this->auth->sendEmailVerificationLink($user->email, $actionCodeSettings);

            return response()->json([
                'status' => 'Success',
                'message' => 'Please check your email for verification.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Error',
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Login an existing user.
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            $user = $this->auth->signInWithEmailAndPassword($request->email, $request->password);

            return response()->json($user->data());
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Error',
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Verify User
     */
    public function verify(Request $request)
    {
        return response()->json([
            'status' => 'Success',
            'message' => 'Verified User',
        ]);
    }
}
