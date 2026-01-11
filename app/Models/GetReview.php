<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GetReview extends Model
{
    protected $fillable = [
        'user_id',
        'page_id',
        'facebook_review_id',
        'open_graph_story_id',
        'reviewer_name',
        'rating',
        'review_text',
        'status',
        'ai_agent_id',
        'reviewed_at',
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
}
