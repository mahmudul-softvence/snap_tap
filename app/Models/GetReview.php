<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GetReview extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_review_id',
        'page_id',
        'user_business_account_id',
        'reviewer_name',
        'reviewer_image',
        'rating',
        'review_text',
        'status',
        'ai_agent_id',
        'reviewed_at',
        'review_reply_id',
        'review_reply_text',
        'replied_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function aiAgent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function facebookPage()
    {
        return $this->belongsTo(UserBusinessAccount::class, 'page_id', 'provider_account_id');
    }

    public function googleGmbProfile()
    {
        return $this->belongsTo(UserBusinessAccount::class, 'page_id', 'provider_account_id');
    }
}
