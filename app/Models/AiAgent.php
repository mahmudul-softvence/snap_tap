<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAgent extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'content',
        'method',
        'review_type',
        'review_count',
        'is_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
