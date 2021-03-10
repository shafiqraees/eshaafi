<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PatientProfile;
class Relation extends Model
{
    protected $guarded = [];

    public function patientProfle()
    {
        return $this->belongsTo(\App\Models\PatientProfile::class, 'patient_profile_id');
    }
}
