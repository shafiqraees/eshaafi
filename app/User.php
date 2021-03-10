<?php

namespace App;

use App\Models\DoctorAward;
use App\Models\UserToken;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'user_type', 'phone', 'user_name', 'is_active', 'profile_image', 'is_logged_in'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function devices(): HasOne
    {
        return $this->hasOne(UserDevice::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(\App\Models\DoctorProfile::class);
    }
    public function patientProfile(): HasOne
    {
        return $this->hasOne(\App\Models\PatientProfile::class);
    }

    public function AauthAcessToken(){
        return $this->hasMany('\App\Models\OauthAccessToken');
    }
    public function faq(): HasMany
    {
        return $this->hasMany(\App\Models\Faq::class);
    }

    public function userToken(): HasMany
    {
        return $this->hasMany(\App\Models\UserToken::class);
    }
}
