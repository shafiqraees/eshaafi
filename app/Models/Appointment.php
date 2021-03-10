<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    protected $guarded = [];

    public function patient()
    {
        return $this->belongsTo(\App\Models\PatientProfile::class, 'patient_profile_id');
    }

    public function doctor()
    {
        return $this->belongsTo(\App\Models\DoctorProfile::class, 'doctor_profile_id');
    }

    public function prescription(): HasMany
    {
        return $this->hasMany(\App\Models\Prescription::class, 'appointments_id');
    }

    public function symptoms(): HasMany
    {
        return $this->hasMany(\App\Models\AppointmentSymptom::class, 'appointment_id');
    }

    public function patientPrescription(): HasMany
    {
        return $this->HasMany(\App\Models\Prescription::class, 'appointments_id');
    }
    public function records()
    {
        return $this->hasManyThrough('\App\Models\MedicalRecord', '\App\Models\Prescription', 'appointments_id', 'prescriptions_id');
    }

    public function relation()
    {
        return $this->belongsTo(\App\Models\Relation::class);
    }

    public function patientUser()
    {
        return $this->hasOneThrough('\App\User', '\App\Models\PatientProfile');
    }

    public function doctorUser()
    {
        return $this->hasOneThrough('\App\User', '\App\Models\DoctorProfile');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(\App\Models\Rating::class);
    }

    public function channel(): HasOne
    {
        return $this->hasOne(\App\Models\Channel::class);
    }
}
