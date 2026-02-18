<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    protected $fillable = [
        'user_id',
        'b_name',
        'b_type',
        'b_email',
        'b_phone',
        'b_website',
        'b_address',
        'b_logo',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getBLogoAttribute($value)
    {
        return $value ? asset($value) : null;
    }
}
