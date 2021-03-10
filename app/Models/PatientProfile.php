<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientProfile extends Model
{
    protected $guarded = [];
//    protected $fillable = ['dob', 'age', 'blood_group', 'weight', 'height', 'gender', 'marital_status', 'address'];

    public function appointments(): HasMany
    {
        return $this->hasMany(\App\Models\Appointment::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function relation(): HasMany
    {
        return $this->hasMany(\App\Models\Relation::class);
    }
}
