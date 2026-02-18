<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class ImageUpload
{

    public static function upload($image, $type = 'default', $oldImage = null)
    {
        if (!$image) {
            return $oldImage;
        }

        $folders = [
            'user'              => 'users',
            'business_profile'  => 'business_profiles',
            'default'           => 'others',
            'setting'           => 'settings',
        ];

        $folder = $folders[$type] ?? 'others';
        $path = public_path("uploads/$folder");

        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        if ($oldImage) {
            $cleanPath = str_replace(url('/'), '', $oldImage);
            $fullOldPath = public_path(ltrim($cleanPath, '/'));

            if (File::exists($fullOldPath)) {
                File::delete($fullOldPath);
            }
        }

        $fileName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
        $image->move($path, $fileName);

        return "uploads/$folder/$fileName";
    }

}

//blade view like <img src="{{ asset($user->image) }}" alt="">
//blade view like <img src="{{ asset($business_profile->b_logo) }}" alt="">
