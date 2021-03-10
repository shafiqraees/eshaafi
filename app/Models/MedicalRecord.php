<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
//    protected $guarded = [];

    protected $fillable = [
        'prescriptions_id', 'Type', 'image_url'
    ];

    public function prescription()
    {
        return $this->belongsTo(\App\Models\Prescription::class, 'prescriptions_id');
    }
}
