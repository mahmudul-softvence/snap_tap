<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'image',
        'github_id',
        'google_id',
        'facebook_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function qrs()
    {
        return $this->hasMany(Qr::class);
    }

    public function messageTemplates()
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function businessProfile()
    {
        return $this->hasOne(BusinessProfile::class, 'user_id', 'id');
    }

    //for google and facebook business accounts
    public function businessAccounts()
    {
        return $this->hasMany(UserBusinessAccount::class);
    }

    // Helper for Google
    public function googleBusinessAccount()
    {
        return $this->businessAccounts()->where('provider', 'google')->first();
    }

    // Helper for Facebook (future)
    public function facebookBusinessAccount()
    {
        return $this->businessAccounts()->where('provider', 'facebook')->first();
    }
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function basicSetting()
    {
        return $this->hasOne(BasicSetting::class);
    }
}
