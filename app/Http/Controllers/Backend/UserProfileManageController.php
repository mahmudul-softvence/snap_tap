<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Http\Request;

class UserProfileManageController extends Controller
{
    public function userDetailsShow($id)
    {
        $authUser = Auth::user();
        abort_if(!$authUser || !$authUser->hasRole('super_admin'), 403, 'Unauthorized');

        $user = User::with('businessProfile')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ], 200);
    }
}
