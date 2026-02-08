<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\BusinessProfile;
use App\Models\UserBusinessAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Services\ImageUpload;

class UserProfileController extends Controller
{
    public function authMe()
    {
        $user = User::with('businessProfile', 'businessAccounts')->find(Auth::id());

        $subscription = auth()->user()->subscription('default');

        abort_if($user->id !== Auth::id(), 403, 'Unauthorized');

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user,
            'subscription' => $subscription,
        ]);
    }

    public function showProfile()
    {
        $user = User::with('businessProfile')->find(Auth::id());

        abort_if($user->id !== Auth::id(), 403, 'Unauthorized');

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        abort_if($user->id !== Auth::id(), 403, 'Unauthorized');
        $business = $user->businessProfile;

        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'phone'     => 'nullable|string|max:50',
            // 'email'     => 'required|email|unique:users,email,' . $user->id,
            'image'     => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'b_name'    => 'required_with:b_type,b_email,b_phone,b_website,b_address,b_logo|string|max:255',
            'b_type'    => 'required_with:b_name,b_email,b_phone,b_website,b_address,b_logo|string|max:255',
            'b_email'   => ['nullable', 'email', 'max:255', Rule::unique('business_profiles', 'b_email')->ignore($business->id ?? null),],
            'b_phone'   => 'nullable|string|max:50',
            'b_website' => 'nullable|url|max:255',
            'b_address' => 'nullable|string|max:255',
            'b_logo'    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $image = ImageUpload::upload($request->image, 'user', $user->image);
        $user->name = $request->name;
        if ($request->filled('email')) {
            $user->email = $request->email;
        }
        $user->phone = $request->phone;
        $user->image = $image;
        $user->save();

        if ($request->filled('b_name') && $request->filled('b_type')) {

            $b_logo = ImageUpload::upload($request->b_logo, 'business_profile', $business?->b_logo);

            $business = BusinessProfile::updateOrCreate([
                'user_id' => $user->id
            ], [
                'b_name'    => $request->b_name,
                'b_type'    => $request->b_type,
                'b_email'   => $request->b_email,
                'b_phone'   => $request->b_phone,
                'b_website' => $request->b_website,
                'b_address' => $request->b_address,
                'b_logo'    => $b_logo,
            ]);
        }
        $user->refresh()->load('businessProfile');
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $user,
            // 'business' => isset($business) ? $business : null
        ]);
    }

    public function integration()
    {
        $user = User::with('businessAccounts')->find(Auth::id());

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $accounts = $user->businessAccounts;

        return response()->json([
            'success' => true,
            'message' => $accounts->isEmpty() ? 'No business accounts found' : 'Business accounts retrieved',
            'business_accounts' => $accounts
        ]);
    }


    public function toggleIntegrationStatus(Request $request)
    {
        $user = Auth::user();

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
                'message' => 'Business account not found or unauthorized'
            ], 404);
        }

        $businessAccount->update([
            'status' => $request->status
        ]);

        $businessAccount->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'business_account' => $businessAccount
        ], 200);
    }






























    public function adminProfileUpdate(Request $request)
    {
        $user = Auth::user();

        abort_if(! $user || ! $user->hasRole('super_admin'), 403, 'Unauthorized');

        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->hasFile('image')) {
            $user->image = ImageUpload::upload($request->image, 'user', $user->image);
        }

        $user->name = $request->name;

        if ($request->filled('email')) {
            $user->email = $request->email;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data'    => $user,
        ]);
    }
}
