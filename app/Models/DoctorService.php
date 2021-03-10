<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorService extends Model
{
    protected $guarded = [];
    public function service()
    {
        return $this->belongsTo(\App\Models\Service::class);
    }
}
