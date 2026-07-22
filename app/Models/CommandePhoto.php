<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommandePhoto extends Model
{
    protected $fillable = [
        'commande_id',
        'chemin',
        'visible_client',
        'ordre',
    ];

    protected $casts = [
        'visible_client' => 'boolean',
    ];

    public function commande()
    {
        return $this->belongsTo(Commande::class);
    }
}
