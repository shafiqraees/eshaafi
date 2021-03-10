<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AppointmentSymptom extends Model
{
    protected $guarded = [];

    public function symptom()
    {
        return $this->belongsTo(\App\Models\Symptom::class);
    }
}
