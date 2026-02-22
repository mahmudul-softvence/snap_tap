<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'is_sensitive'];

    public $timestamps = false;

    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        if ($setting->is_sensitive && $setting->value) {
            try {
                return Crypt::decryptString($setting->value);
            } catch (\Exception $e) {
                return $default;
            }
        }

        return $setting->value;
    }

    protected $imageFields = [
        'platform_logo',
        'signup_onboarding_image',
        'login_onboarding_image'
    ];

    public function getValueAttribute($value)
    {
        if (in_array($this->key, $this->imageFields) && $value) {
            return asset($value);
        }

        return $value;
    }
}
