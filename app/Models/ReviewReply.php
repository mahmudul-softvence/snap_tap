<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'page_id',
        'review_id',
        'reply_id',
        'reply_type',
        'comment',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
