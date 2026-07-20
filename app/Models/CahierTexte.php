<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CahierTexte extends Model
{
    use HasFactory;

    protected $fillable = [
        'classe_id',
        'enseignant_id',
        'titre',
        'matiere',
        'date_cours',
        'contenu_realise',
        'resume_cours',
        'exercices_donnes',
    ];

    protected $casts = [
        'date_cours' => 'date',
    ];

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function devoirs()
    {
        return $this->hasMany(Devoir::class, 'cahier_texte_id');
    }
}
