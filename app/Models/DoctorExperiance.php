<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorExperiance extends Model
{
    protected $fillable = ['doctor_profile_id','hospital_name','designation','start_date','end_date','country','is_active',];
}
