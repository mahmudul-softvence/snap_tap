<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Qr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrController extends Controller
{
    /**
     * Store or update a QR code for a user and provider
     */
    public function store_qr(Request $request, $provider): JsonResponse
    {
        $request->validate([
            'text' => 'required|url',
        ]);

        $user = $request->user();
        $text = $request->text;

        $existingQr = Qr::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        if ($existingQr && $existingQr->qr_code) {
            $oldPath = public_path($existingQr->qr_code);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $qr = QrCode::size(400)->generate($text);

        $folder = public_path('uploads/qrs');
        if (!file_exists($folder)) {
            mkdir($folder, 0755, true);
        }

        $filename = 'qr_' . $user->id . '_' . $provider . '.svg';
        $filepath = $folder . '/' . $filename;

        file_put_contents($filepath, $qr);

        $qrRecord = Qr::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => $provider,
            ],
            [
                'text' => $text,
                'qr_code' => 'uploads/qrs/' . $filename,
            ]
        );

        return response()->json([
            'success' => true,
            'qr' => [
                'id' => $qrRecord->id,
                'provider' => $qrRecord->provider,
                'text' => $qrRecord->text,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'qr_code' => asset($qrRecord->qr_code),
            ],
        ], 201);
    }


    /**
     * Get QR code for the authenticated user by provider
     */
    public function get_qr($provider)
    {
        $user = Auth::user();

        $qrRecord = Qr::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        if (!$qrRecord) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found for this provider.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'qr' => [
                'id' => $qrRecord->id,
                'provider' => $qrRecord->provider,
                'text' => $qrRecord->text,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'qr_code' => asset($qrRecord->qr_code),
            ],
        ], 200);
    }
}
