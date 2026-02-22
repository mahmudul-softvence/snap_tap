<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Review extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'status',
        'message',
        'sent_sms',
        'sent_email',
        'retries',
        'provider',
        'user_id',
        'unique_id'
    ];

    protected $casts = [
        'sent_sms'   => 'boolean',
        'sent_email' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($review) {
            $review->unique_id = bin2hex(random_bytes(8)) . Str::random(6);
        });
    }
}
