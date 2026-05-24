<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ecole extends Model
{
    protected $fillable = [
        'nom',
        'code_ecole',
        'annee_scolaire',
        'logo',
    ];

    public function classes()
    {
        return $this->hasMany(Classe::class);
    }
}
