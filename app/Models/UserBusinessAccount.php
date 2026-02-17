<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBusinessAccount extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_account_id',
        'business_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'meta_data',
        'status',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'token_expires_at' => 'datetime',
    ];

    public function reviews()
    {
        return $this->hasMany(GetReview::class, 'page_id', 'provider_account_id');
    }

}
