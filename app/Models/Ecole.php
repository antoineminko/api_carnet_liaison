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
        'logo',
        'image_fond',
        'ville',
        'description',
        'email_admin',
        'password_admin',
    ];

    public function classes()
    {
        return $this->hasMany(Classe::class);
    }

    public function enseignants()
    {
        return $this->hasMany(Enseignant::class);
    }

    public function parents()
    {
        return $this->hasMany(ParentUser::class);
    }
}
