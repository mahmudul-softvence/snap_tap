<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserProfileController extends Controller
{
    public function showProfile()
    {
        $user = User::with('businessProfile')->find(Auth::id());

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
        $business = $user->businessProfile;

        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'b_name'    => 'required|string|max:255',
            'b_type'    => 'required|string|max:255',
            'b_email'   => ['nullable','email','max:255',Rule::unique('business_profiles', 'b_email')->ignore($business->id ?? null),],
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

        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;

        if ($request->hasFile('image')) {
            if ($user->image && file_exists(public_path($user->image))) unlink(public_path($user->image));
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/users'), $filename);
            $user->image = 'uploads/users/' . $filename;
            $user->save();
        }

        $user->save();

        $business = BusinessProfile::updateOrCreate([
            'user_id' => $user->id
        ], [
            'b_name' => $request->b_name,
            'b_type' => $request->b_type,
            'b_email' => $request->b_email,
            'b_phone' => $request->b_phone,
            'b_website' => $request->b_website,
            'b_address' => $request->b_address,
        ]);

        if ($request->hasFile('b_logo')) {
            if ($business->b_logo && file_exists(public_path($business->b_logo))) unlink(public_path($business->b_logo));
            $file = $request->file('b_logo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/business_profile'), $filename);
            $business->b_logo = 'uploads/business_profile/' . $filename;
            $business->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $user,
            'business' => isset($business) ? $business : null
        ]);
    }
}
