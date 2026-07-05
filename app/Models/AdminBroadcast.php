<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminBroadcast extends Model
{
    use HasFactory;

    protected $fillable = [
        'ecole_id',
        'type',
        'titre',
        'contenu',
        'fichier_url',
        'cibles',
    ];

    protected $casts = [
        'cibles' => 'array',
    ];
}
