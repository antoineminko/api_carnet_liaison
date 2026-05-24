<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classe extends Model
{
    protected $fillable = [
        'ecole_id',
        'nom',
        'code_classe',
        'niveau',
        'annee_scolaire',
    ];

    public function ecole()
    {
        return $this->belongsTo(Ecole::class);
    }

    public function eleves()
    {
        return $this->hasMany(Eleve::class);
    }
}
