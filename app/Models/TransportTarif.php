<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportTarif extends Model
{
    protected $fillable = [
        'transport_zone_id',
        'poids_min',
        'poids_max',
        'prix',
    ];

    public function zone()
    {
        return $this->belongsTo(TransportZone::class, 'transport_zone_id');
    }
}
