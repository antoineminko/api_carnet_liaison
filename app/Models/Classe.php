<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classe extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'code', 'ecole_id', 'prof_principal_id'];

    public function ecole()
    {
        return $this->belongsTo(Ecole::class);
    }

    public function profPrincipal()
    {
        return $this->belongsTo(Enseignant::class, 'prof_principal_id');
    }

    public function enseignants()
    {
        return $this->belongsToMany(Enseignant::class, 'classe_enseignant');
    }

    public function eleves()
    {
        return $this->hasMany(Eleve::class);
    }
}
