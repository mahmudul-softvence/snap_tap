<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\ImageUpload;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\BusinessProfile;
use App\Models\GetReview;
use App\Models\UserBusinessAccount;
use Illuminate\Http\JsonResponse;
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


    public function userDetailsUpdate(Request $request, $id)
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

        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'phone'     => 'nullable|string|max:50',
            // 'email'     => 'required|email|unique:users,email,' . $user->id,
            'image'     => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            'b_name'    => 'required_with:b_type,b_email,b_phone,b_website,b_address,b_logo|string|max:255',
            'b_type'    => 'required_with:b_name,b_email,b_phone,b_website,b_address,b_logo|string|max:255',
            'b_email'   => ['nullable', 'email', 'max:255', Rule::unique('business_profiles', 'b_email')->ignore($user->businessProfile?->id)],
            'b_phone'   => 'nullable|string|max:50',
            'b_website' => 'nullable|url|max:255',
            'b_address' => 'nullable|string|max:255',
            'b_logo'    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $userImage = ImageUpload::upload(
            $request->image,
            'user',
            $user->image
        );

        $user->update([
            'name'  => $request->name,
            // 'email' => $request->email,
            'phone' => $request->phone,
            'image' => $userImage,
        ]);

        if ($request->filled('b_name') && $request->filled('b_type')) {

            $businessLogo = ImageUpload::upload(
                $request->b_logo,
                'business_profile',
                $user->businessProfile?->b_logo
            );

            BusinessProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'b_name'    => $request->b_name,
                    'b_type'    => $request->b_type,
                    'b_email'   => $request->b_email,
                    'b_phone'   => $request->b_phone,
                    'b_website' => $request->b_website,
                    'b_address' => $request->b_address,
                    'b_logo'    => $businessLogo,
                ]
            );
        }

        $user->refresh()->load('businessProfile');

        return response()->json([
            'success'  => true,
            'message'  => 'Profile updated successfully',
            'user'     => $user,
            // 'business' => $user->businessProfile,
        ], 200);
    }


    // Integration Account
    public function userIntegrationDetails($id)
    {
        $authUser = Auth::user();
        abort_if(!$authUser || !$authUser->hasRole('super_admin'), 403, 'Unauthorized');

        $user = User::with('businessAccounts')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->businessAccounts->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No business accounts found',
                'business_accounts' => []
            ]);
        }

        return response()->json([
            'success' => true,
            'business_accounts' => $user->businessAccounts
        ], 200);
    }


    // Integration Account Status Update
    public function userIntegrationStatusUpdate(Request $request, $id)
    {
        $authUser = Auth::user();
        abort_if(!$authUser || !$authUser->hasRole('super_admin'), 403, 'Unauthorized');

        $user = User::with('businessAccounts')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $request->validate([
            'business_account_id' => 'required|exists:user_business_accounts,id',
            'status'              => 'required|in:connected,disconnect',
        ]);

        $businessAccount = UserBusinessAccount::where('id', $request->business_account_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$businessAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Business account not found for this user'
            ], 404);
        }

        $businessAccount->update([
            'status' => $request->status
        ]);

        $businessAccount->refresh();

        return response()->json([
            'success'          => true,
            'message'          => 'Integration status updated successfully',
            'business_account' => $businessAccount
        ], 200);
    }

    //user provider account remove by admin
    public function removeUserProviderAccount(Request $request, $userId)
    {
        $authUser = Auth::user();
        abort_if(!$authUser || !$authUser->hasRole('super_admin'), 403, 'Unauthorized');

        $provider = $request->input('provider'); // 'facebook' or 'google'
        abort_if(!$provider || !in_array($provider, ['facebook', 'google']), 400, 'Invalid provider');

        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $businessAccount = UserBusinessAccount::where('user_id', $userId)
            ->where('provider', $provider)
            ->first();

        if (!$businessAccount) {
            return response()->json([
                'success' => false,
                'message' => "No $provider account found for this user"
            ], 404);
        }

        $businessAccount->reviews()->delete();

        $businessAccount->delete();

        return response()->json([
            'success' => true,
            'message' => ucfirst($provider) . ' account and its reviews deleted successfully'
        ], 200);
    }





    public function user_ai_agents(Request $request, $id): JsonResponse
    {
        $perPage = $request->input('per_page', 10);

        $query = AiAgent::where('user_id', $id);

        $total_agents = $query->count();

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('method', 'like', "%{$search}%");
            });
        }

        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        if ($request->input('sort') === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $agents = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $agents,
            'total_agents' => $total_agents
        ]);
    }
}
