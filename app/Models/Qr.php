<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Qr extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'qr_code',
        'text',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
