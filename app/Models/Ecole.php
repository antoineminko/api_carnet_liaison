<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ecole extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'code',
        'annee_scolaire',
        'nb_classes',
        'nb_profs',
        'nb_eleves',
    ];

    public function classes()
    {
        return $this->hasMany(Classe::class);
    }
}
