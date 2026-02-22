<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\BasicSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BasicSettingController extends Controller
{
    public function index()
    {
        $setting = auth()->user()->basicSetting;

        return response()->json([
            'success' => true,
            'data' => $setting
        ]);
    }

    public function update(Request $request)
    {
        $setting = auth()->user()->basicSetting;

        if (!$setting) {
            $setting = auth()->user()->basicSetting()->create([]);
        }

        $validated = $request->validate([
            'msg_after_checkin'     => 'nullable|integer|min:0',
            'message_checkin_status' => 'nullable|boolean',
            'next_message_time'     => 'nullable|integer|min:0',
            're_try_time'           => 'nullable|integer|min:0',

            'new_customer_review'   => 'nullable|boolean',
            'ai_reply'              => 'nullable|boolean',
            'ai_review_reminder'    => 'nullable|boolean',
            'customer_review'       => 'nullable|boolean',
            'renewel_reminder'      => 'nullable|boolean',
            'timezone'              => 'nullable|boolean',
            'auto_request_auto'     => 'nullable|boolean',

            'auto_ai_reply'          => 'nullable|boolean',
            'auto_ai_review_request' => 'nullable|boolean',
            'multi_language_ai'      => 'nullable|boolean',

            'review_sent_time'      => 'nullable|date',
            'lang'                  => 'nullable|string|max:5',
            'date_format'           => 'nullable|string|in:dd/mm/yyyy,yyyy/mm/dd',
        ]);

        $setting->update($validated);

        return response()->json([
            'message' => 'Basic settings updated successfully',
            'data' => $setting,
        ]);
    }
}
