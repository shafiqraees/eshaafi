<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DoctorProfile extends Model
{
    protected $guarded = [];

    public function videoConsultancy(): HasOne
    {
        return $this->hasOne(\App\Models\DoctorVideoConsultancy::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(\App\Models\DoctorAward::class);
    }

    public function education(): HasMany
    {
        return $this->hasMany(\App\Models\DoctorEducation::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(\App\Models\DoctorExperiance::class);
    }

    public function hospitals(): HasMany
    {
        return $this->hasMany(\App\Models\DoctorHospital::class);
    }

    public function hospitalDays(): HasMany
    {
        return $this->hasMany(\App\Models\DoctorHospitalDay::class);
    }

    public function hospitalServices(): HasMany
    {
        return $this->hasMany(\App\Models\DoctorHospitalService::class);
    }

    public function hospitalVocations(): HasMany
    {
        return $this->hasMany(\App\Models\DoctorHospitalVocation::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(\App\Models\DoctorService::class);
    }

    public function specialities(): HasMany
    {
        return $this->hasMany(\App\Models\DoctorSpeciality::class);
    }

    public function videoConsultancyDays(): HasMany
    {
        return $this->hasMany(\App\Models\DoctorVideoConsultancyDay::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(\App\Models\Rating::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(\App\Models\Membership::class);
    }

    public function languag(): HasMany
    {
        return $this->hasMany(\App\Models\Language::class);
    }
    public function faq(): HasMany
    {
        return $this->hasMany(\App\Models\Faq::class);
    }
    public function appointments(): HasMany
    {
        return $this->hasMany(\App\Models\Appointment::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }
}
