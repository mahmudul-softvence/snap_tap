<?php

namespace App\Models;

use App\Jobs\VerifyEmailJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Cashier\Billable;
use App\Models\Subscription;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles, Billable;

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
        'password_add_first_time',
        'two_factor_secret',
        'two_factor_enabled',
        'two_factor_confirmed_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_confirmed_at'
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

    protected $casts = [
        'two_factor_email_expires_at' => 'datetime',
        'two_factor_code_expires_at' => 'datetime',
    ];

    public function sendEmailVerificationNotification()
    {
        dispatch(new VerifyEmailJob($this));
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

    public function businessAccounts()
    {
        return $this->hasMany(UserBusinessAccount::class);
    }

    public function googleBusinessAccount()
    {
        return $this->businessAccounts()->where('provider', 'google')->first();
    }

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

    public function aiAgents()
    {
        return $this->hasMany(AiAgent::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function getImageAttribute($value)
    {
        return $value ? asset($value) : null;
    }
}
