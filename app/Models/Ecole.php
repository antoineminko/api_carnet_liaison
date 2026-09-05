<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Ecole extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'nom',
        'code',
        'acronyme',
        'annee_scolaire',
        'nb_classes',
        'nb_profs',
        'nb_eleves',
        'logo',
        'image_fond',
        'ville',
        'description',
        'email_admin',
        'telephone',
        'password_admin',
    ];
   
    protected $hidden = ['password_admin'];

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
