<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enseignant extends Model
{
    use HasFactory;

    protected $fillable = [
        'prenom',
        'nom',
        'matiere',
        'email',
        'telephone',
        'password',
        'ecole_id',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
    ];

    public function ecole()
    {
        return $this->belongsTo(Ecole::class);
    }

    public function classes()
    {
        return $this->hasMany(Classe::class, 'prof_principal_id');
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }
}
