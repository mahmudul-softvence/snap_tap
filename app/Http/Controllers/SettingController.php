<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use App\Services\ImageUpload;

class SettingController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        abort_if(!$user || !$user->hasRole('super_admin'), 403, 'Unauthorized');

        $settings = Setting::all()->map(function ($setting) {
            // Decrypt sensitive fields safely
            if ($setting->is_sensitive && $setting->value) {
                try {
                    $value = Crypt::decryptString($setting->value);
                } catch (\Exception $e) {
                    $value = null; // fallback
                }
            } else {
                $value = $setting->value;
            }
            return [
                'key' => $setting->key,
                'value' => $value,
            ];
        });

        $redirects = [
            'facebook_login_redirect_uri' => config('services.facebook.redirect'),
            'facebook_page_redirect_uri'  => config('services.facebook.page_redirect'),
            'google_login_redirect_uri'   => config('services.google.redirect'),
            'google_business_redirect_uri'   => config('services.google.business_redirect'),
            'stripe_webhook_redirect_uri'   => config('cashier.webhook_redirect'),
        ];


        return response()->json([
            'success' => true,
            'message' => 'Settings fetched successfully.',
            'data' => $settings,
            'redirects' => $redirects,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $user = Auth::user();
        abort_if(!$user || !$user->hasRole('super_admin'), 403, 'Unauthorized');

        $rules = [
            // Platform
            'platform_name' => 'nullable|string|max:255',
            'platform_logo' => 'nullable|mimes:jpg,jpeg,png,gif|max:5120',
            'signup_onboarding_image' => 'nullable|mimes:jpg,jpeg,png,gif|max:5120',
            'login_onboarding_image' => 'nullable|mimes:jpg,jpeg,png,gif|max:5120',

            // Mail
            'mail_mailer' => 'nullable|string',
            'mail_host' => 'nullable|string',
            'mail_port' => 'nullable|numeric',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|string',
            'mail_from_address' => 'nullable|email',
            'mail_from_name' => 'nullable|string',

            // Facebook
            'facebook_login_client_id' => 'nullable|string',
            'facebook_login_client_secret' => 'nullable|string',
            'facebook_page_client_id' => 'nullable|string',
            'facebook_page_client_secret' => 'nullable|string',

            // Google
            'google_client_id' => 'nullable|string',
            'google_client_secret' => 'nullable|string',

            // Twilio
            'twilio_sid' => 'nullable|string',
            'twilio_auth_token' => 'nullable|string',
            'twilio_from_number' => 'nullable|string',

            // AI
            'auto_ai_reply' => 'nullable|boolean',
            'auto_ai_review_request' => 'nullable|boolean',
            'multi_language_ai' => 'nullable|boolean',

            // Notification
            'new_customer_singup_n' => 'nullable|boolean',
            'customer_plan_upgraded_n' => 'nullable|boolean',
            'customer_subs_cancel_n' => 'nullable|boolean',

            'auto_time_zone'    => 'nullable|boolean',
            'lang'           => 'nullable|string|max:10',
            'date_format'          => 'nullable|string|max:20',
        ];

        $validatedData = $request->validate($rules);

        // Sensitive fields to encrypt
        $sensitiveKeys = [
            'mail_username',
            'mail_password',
            'facebook_login_client_secret',
            'facebook_page_client_secret',
            'google_client_secret',
            'twilio_sid',
            'twilio_auth_token',
            'stripe_signing_secret',
            'chatgpt_api_key'
        ];

        $imageFields = ['platform_logo', 'signup_onboarding_image', 'login_onboarding_image'];

        $updatedSettings = [];

        foreach ($validatedData as $key => $value) {

            if (in_array($key, $imageFields)) {
                if ($request->hasFile($key)) {
                    $setting = Setting::firstOrCreate(['key' => $key]);
                    $oldImagePath = $setting->getRawOriginal('value');

                    $path = ImageUpload::upload($request->file($key), 'setting', $oldImagePath);
                    $setting->value = $path;
                    $setting->save();

                    $updatedSettings[] = ['key' => $key, 'value' => asset($path)];
                } else {
                    $existing = Setting::where('key', $key)->first();
                    if ($existing) $updatedSettings[] = ['key' => $key, 'value' => asset($existing->value)];
                }
                continue;
            }

            $isSensitive = in_array($key, $sensitiveKeys);
            $valueToSave = ($isSensitive && !is_null($value)) ? Crypt::encryptString($value) : $value;

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $valueToSave, 'is_sensitive' => $isSensitive]
            );

            $updatedSettings[] = ['key' => $key, 'value' => $value];
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
            'data' => $updatedSettings,
        ]);
    }
}
