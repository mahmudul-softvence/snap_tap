<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FacebookAvatarService
{
    protected string $folder;

    public function __construct(string $folder = 'uploads/reviewers')
    {
        $this->folder = rtrim($folder, '/');
    }

    public function saveAvatar(?string $url, ?string $oldFile = null): ?string
    {
        if (empty($url)) {
            return null;
        }

        try {
            $imageContents = Http::get($url)->body();

            if (!$imageContents) {
                return null;
            }

            $folderPath = public_path($this->folder);
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0755, true);
            }

            $fileName = $this->folder . '/' . Str::random(20) . '.jpg';
            $filePath = public_path($fileName);

            file_put_contents($filePath, $imageContents);

            if ($oldFile) {

                $oldFileRelative = str_replace(url('/'), '', $oldFile);
                $oldFilePath = public_path(ltrim($oldFileRelative, '/'));

                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            return asset($fileName);
        } catch (\Exception $e) {
            Log::error('FacebookAvatarService: Failed to save avatar - ' . $e->getMessage());
            return null;
        }
    }



    // public function saveAvatar(?string $url): ?string
    // {
    //     if (empty($url)) {
    //         return null;
    //     }

    //     try {
    //         $imageContents = Http::get($url)->body();

    //         if (!$imageContents) {
    //             return null;
    //         }

    //         $folderPath = public_path($this->folder);
    //         if (!file_exists($folderPath)) {
    //             mkdir($folderPath, 0755, true);
    //         }

    //         $fileName = $this->folder . '/' . Str::random(20) . '.jpg';
    //         $filePath = public_path($fileName);

    //         file_put_contents($filePath, $imageContents);

    //         return asset($fileName);
    //     } catch (\Exception $e) {
    //         Log::error('FacebookAvatarService: Failed to save avatar - ' . $e->getMessage());
    //         return null;
    //     }
    // }
}
