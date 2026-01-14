<?php

namespace App\Providers;

use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Laravel\Cashier\Cashier;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;
use App\Models\Setting;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        /**
         * -----------------------
         * MAIL SETTINGS
         * -----------------------
         */
        Config::set('mail.default', Setting::get('mail_mailer', 'smtp'));
        Config::set('mail.mailers.smtp.host', Setting::get('mail_host', '127.0.0.1'));
        Config::set('mail.mailers.smtp.port', Setting::get('mail_port', 2525));
        Config::set('mail.mailers.smtp.username', Setting::get('mail_username'));
        Config::set('mail.mailers.smtp.password', Setting::get('mail_password'));
        Config::set('mail.mailers.smtp.encryption', Setting::get('mail_encryption', 'tls'));
        Config::set('mail.from.address', Setting::get('mail_from_address', 'hello@example.com'));
        Config::set('mail.from.name', Setting::get('mail_from_name', config('app.name')));

        /**
         * -----------------------
         * FACEBOOK LOGIN
         * -----------------------
         */
        Config::set('services.facebook.client_id', Setting::get('facebook_login_client_id'));
        Config::set('services.facebook.client_secret', Setting::get('facebook_login_client_secret'));
        Config::set('services.facebook.redirect', Setting::get('facebook_login_redirect_uri', url('/api/auth/facebook/callback')));

        /**
         * -----------------------
         * FACEBOOK (PAGE / BUSINESS)
         * -----------------------
         */
        Config::set('services.facebook.page_client_id', Setting::get('facebook_page_client_id'));
        Config::set('services.facebook.page_client_secret', Setting::get('facebook_page_client_secret'));
        Config::set('services.facebook.page_redirect', Setting::get('facebook_page_redirect_uri', url('/api/facebook/callback')));

        /**
         * -----------------------
         * GOOGLE LOGIN
         * -----------------------
         */
        Config::set('services.google.client_id', Setting::get('google_client_id'));
        Config::set('services.google.client_secret', Setting::get('google_client_secret'));
        Config::set('services.google.redirect', Setting::get('google_redirect_uri', url('/api/auth/google/callback')));

        /**
         * -----------------------
         * TWILIO
         * -----------------------
         */
        Config::set('services.twilio.sid', Setting::get('twilio_sid'));
        Config::set('services.twilio.token', Setting::get('twilio_auth_token'));
        Config::set('services.twilio.from', Setting::get('twilio_from_number'));
        // -----------------------------
        // GOOGLE LOGIN
        // -----------------------------
        Config::set('services.google.client_id', Setting::where('key', 'google_client_id')->value('value'));
        Config::set('services.google.client_secret', Setting::where('key', 'google_client_secret')->value('value'));
        Config::set('services.google.redirect', Setting::where('key', 'google_redirect_uri')->value('value') ?? url('/api/auth/google/callback'));

        // -----------------------------
        // GOOGLE GMB
        // -----------------------------
        // Config::set('services.google_gmb.client_id', Setting::where('key', 'google_gmb_client_id')->value('value'));
        // Config::set('services.google_gmb.client_secret', Setting::where('key', 'google_gmb_client_secret')->value('value'));
        // Config::set('services.google_gmb.redirect', Setting::where('key', 'google_gmb_redirect_uri')->value('value'));

        // -----------------------------
        // TWILIO / SMS
        // -----------------------------
        Config::set('services.twilio.sid', Setting::where('key', 'twilio_sid')->value('value'));
        Config::set('services.twilio.token', Setting::where('key', 'twilio_auth_token')->value('value'));
        Config::set('services.twilio.from', Setting::where('key', 'twilio_from_number')->value('value'));

        //CUSTOM MODELS
         Cashier::useSubscriptionModel(Subscription::class);
         Cashier::useSubscriptionItemModel(SubscriptionItem::class);

    }
}
