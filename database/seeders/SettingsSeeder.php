<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [

            /*
            |--------------------------------------------------------------------------
            | PLATFORM SETTINGS
            |--------------------------------------------------------------------------
            */
            [
                'key' => 'platform_name',
                'value' => 'SnapTap',
                'is_sensitive' => false,
            ],
            [
                'key' => 'platform_logo',
                'value' => null,
                'is_sensitive' => false,
            ],
            [
                'key' => 'signup_onboarding_image',
                'value' => null,
                'is_sensitive' => false,
            ],
            [
                'key' => 'login_onboarding_image',
                'value' => null,
                'is_sensitive' => false,
            ],

            /*
            |--------------------------------------------------------------------------
            | PLATFORM SETTINGS
            |--------------------------------------------------------------------------
            */
            [
                'key' => 'auto_time_zone',
                'value' => false,
                'is_sensitive' => false,
            ],
            [
                'key' => 'lang',
                'value' => "en",
                'is_sensitive' => false,
            ],
            [
                'key' => 'date_format',
                'value' => "dd/mm/yyyy",
                'is_sensitive' => false,
            ],
            /*
            |--------------------------------------------------------------------------
            | SMS SETTINGS (TWILIO)
            |--------------------------------------------------------------------------
            */
            [
                'key' => 'sms_provider',
                'value' => 'twilio',
                'is_sensitive' => false,
            ],
            [
                'key' => 'twilio_sid',
                'value' => null,
                'is_sensitive' => true,
            ],
            [
                'key' => 'twilio_auth_token',
                'value' => null,
                'is_sensitive' => true,
            ],
            [
                'key' => 'twilio_from_number',
                'value' => null,
                'is_sensitive' => false,
            ],
            [
                'key' => 'sms_country',
                'value' => 'USA',
                'is_sensitive' => false,
            ],
            /*
            |--------------------------------------------------------------------------
            | Notification
            |--------------------------------------------------------------------------
            */
            [
                'key' => 'new_customer_singup_n',
                'value' => false,
                'is_sensitive' => false,
            ],
            [
                'key' => 'customer_plan_upgraded_n',
                'value' => false,
                'is_sensitive' => false,
            ],
            [
                'key' => 'customer_subs_cancel_n',
                'value' => false,
                'is_sensitive' => false,
            ],

            /*
            |--------------------------------------------------------------------------
            | EMAIL SETTINGS (SMTP)
            |--------------------------------------------------------------------------
            */
            [
                'key' => 'mail_mailer',
                'value' => 'smtp',
                'is_sensitive' => false,
            ],
            [
                'key' => 'mail_host',
                'value' => 'sandbox.smtp.mailtrap.io',
                'is_sensitive' => false,
            ],
            [
                'key' => 'mail_port',
                'value' => '2525',
                'is_sensitive' => false,
            ],
            [
                'key' => 'mail_username',
                'value' => '43312417d51682',
                'is_sensitive' => true,
            ],
            [
                'key' => 'mail_password',
                'value' => '281f036ba67333',
                'is_sensitive' => true,
            ],
            [
                'key' => 'mail_encryption',
                'value' => 'tls',
                'is_sensitive' => false,
            ],
            [
                'key' => 'mail_from_address',
                'value' => 'emial@example.com',
                'is_sensitive' => false,
            ],
            [
                'key' => 'mail_from_name',
                'value' => 'SnapTap',
                'is_sensitive' => false,
            ],

            /*
            |--------------------------------------------------------------------------
            | SOCIAL LOGIN (FACEBOOK)
            |--------------------------------------------------------------------------
            */
            [
                'key' => 'facebook_login_client_id',
                'value' => '217362385600351',
                'is_sensitive' => false,
            ],
            [
                'key' => 'facebook_login_client_secret',
                'value' => '69764ccd219cb37e60651b7f411b874',
                'is_sensitive' => true,
            ],
            [
                'key' => 'facebook_page_client_id',
                'value' => '217362385600351',
                'is_sensitive' => false,
            ],
            [
                'key' => 'facebook_page_client_secret',
                'value' => '69764ccd219cb37e60651b7f411b874',
                'is_sensitive' => true,
            ],
            /*
            |--------------------------------------------------------------------------
            | SOCIAL LOGIN (Google)
            |--------------------------------------------------------------------------
            */
            [
                'key' => 'google_client_id',
                'value' => '217362385600351',
                'is_sensitive' => false,
            ],
            [
                'key' => 'google_client_secret',
                'value' => '69764ccd219cb37e60651b7f411b874',
                'is_sensitive' => true,
            ],
            /*
            |--------------------------------------------------------------------------
            | STRIPE Settings (STRIPE)
            |--------------------------------------------------------------------------
            */
            [
                'key' => 'stripe_publishable_key',
                'value' => 'pk_test_51K7D80H234567890',
                'is_sensitive' => false,
            ],
            [
                'key' => 'stripe_secret_key',
                'value' => 'sk_test_51K7D80H234567890',
                'is_sensitive' => false,
            ],
            [
                'key' => 'stripe_signing_secret',
                'value' => '69764ccd219cb37e60651b7f411b874',
                'is_sensitive' => true,
            ],
            /*
            |--------------------------------------------------------------------------
            | ChatGPT Settings (chatgpt)
            |--------------------------------------------------------------------------
            */
            [
                'key' => 'chatgpt_api_key',
                'value' => 'sk-1234567890abcdef',
                'is_sensitive' => true,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'is_sensitive' => $setting['is_sensitive'],
                ]
            );
        }
    }
}
