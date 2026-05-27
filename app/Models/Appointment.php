<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'enseignant_id',
        'parent_id',
        'eleve_id',
        'date_heure',
        'type', // 'physique' ou 'video'
        'lien_video',
        'statut', // 'en_attente', 'accepte', 'refuse', 'reporte'
        'motif',
    ];

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function parent()
    {
        return $this->belongsTo(ParentUser::class, 'parent_id');
    }

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }
}
