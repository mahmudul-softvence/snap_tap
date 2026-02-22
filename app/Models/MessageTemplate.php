<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'provider',
        'status',
        'message',
    ];

    protected $casts = [
        'status' => 'string',
        'provider' => 'string'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
