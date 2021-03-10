<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DoctorSpeciality extends Model
{
    protected $guarded = [];

    public function speciality()
    {
        return $this->belongsTo(\App\Models\Speciality::class);
    }
}
