<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departement extends Model
{
    protected $fillable = [
        'code',
        'nom',
        'transport_zone_id',
    ];

    public function transportZone()
    {
        return $this->belongsTo(TransportZone::class, 'transport_zone_id');
    }
}
