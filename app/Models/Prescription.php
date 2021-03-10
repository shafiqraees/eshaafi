<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prescription extends Model
{
//    protected $guarded = [];

    protected $fillable = [
        'doctor_profile_id', 'patient_profile_id', 'appointments_id', 'uploaded_by'
    ];

    public function doctorProfile()
    {
        return $this->belongsTo(\App\Models\DoctorProfile::class);
    }

    public function patientProfile()
    {
        return $this->belongsTo(\App\Models\PatientProfile::class);
    }

    public function appointments()
    {
        return $this->belongsTo(\App\Models\Appointment::class, 'appointments_id');
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(\App\Models\MedicalRecord::class, 'prescriptions_id');
    }

}
