<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonumentImage extends Model
{
    protected $fillable = [
        'monument_id',
        'chemin',
        'ordre',
    ];

    public function monument()
    {
        return $this->belongsTo(Monument::class);
    }
}
