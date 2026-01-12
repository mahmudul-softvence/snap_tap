<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;
use App\Models\Setting;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        // -----------------------------
        // MAIL SETTINGS
        // -----------------------------
        $mail_default = Setting::where('key', 'mail_mailer')->value('value') ?? 'smtp';
        $mail_host = Setting::where('key', 'mail_host')->value('value') ?? '127.0.0.1';
        $mail_port = Setting::where('key', 'mail_port')->value('value') ?? 2525;
        $mail_username = Setting::where('key', 'mail_username')->value('value');
        $mail_password = Setting::where('key', 'mail_password')->value('value');
        $mail_encryption = Setting::where('key', 'mail_encryption')->value('value') ?? 'tls';
        $mail_from_address = Setting::where('key', 'mail_from_address')->value('value') ?? 'hello@example.com';
        $mail_from_name = Setting::where('key', 'mail_from_name')->value('value') ?? config('app.name', 'SnapTap');

        Config::set('mail.default', $mail_default);
        Config::set('mail.mailers.smtp.host', $mail_host);
        Config::set('mail.mailers.smtp.port', $mail_port);
        Config::set('mail.mailers.smtp.username', $mail_username);
        Config::set('mail.mailers.smtp.password', $mail_password);
        Config::set('mail.mailers.smtp.encryption', $mail_encryption);
        Config::set('mail.from.address', $mail_from_address);
        Config::set('mail.from.name', $mail_from_name);

        // -----------------------------
        // FACEBOOK LOGIN
        // -----------------------------
        Config::set('services.facebook.client_id', Setting::where('key', 'facebook_login_client_id')->value('value'));
        Config::set('services.facebook.client_secret', Setting::where('key', 'facebook_login_client_secret')->value('value'));
        Config::set('services.facebook.redirect', Setting::where('key', 'facebook_login_redirect_uri')->value('value') ?? url('/api/auth/facebook/callback'));

        Config::set('services.facebook_page.client_id', Setting::where('key', 'facebook_page_client_id')->value('value'));
        Config::set('services.facebook_page.client_secret', Setting::where('key', 'facebook_page_client_secret')->value('value'));
        Config::set('services.facebook_page.redirect', Setting::where('key', 'facebook_page_redirect_uri')->value('value') ?? url('/api/facebook/callback'));

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
    }
}
