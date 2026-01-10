<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BasicSetting extends Model
{

    protected $fillable = [
        'msg_after_checkin',
        'next_message_time',
        're_try_time',


        'new_customer_review',
        'ai_reply',
        'ai_review_reminder',
        'customer_review',
        'renewel_reminder',
        'timezone',
        'auto_request_auto',
        'review_sent_time',
        'lang',
        'date_format',
        'user_id',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
