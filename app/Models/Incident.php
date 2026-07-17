<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'eleve_id',
        'enseignant_id',
        'classe_id',
        'type',
        'description',
        'date',
        'is_read',
        'read_at'
    ];

    protected $casts = [
        'date' => 'date',
        'is_read' => 'boolean',
        'read_at' => 'datetime'
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    public static function getTypeLabel($type)
    {
        $labels = [
            'retard_repete' => 'Retard répété',
            'absence_injustifiee' => 'Absence injustifiée',
            'indiscipline' => 'Indiscipline',
            'violence' => 'Violence',
            'insolence' => 'Insolence',
            'non_respect' => 'Non-respect du règlement',
            'devoirs_non_faits' => 'Devoirs non réalisés',
            'telephone' => 'Téléphone en classe',
            'degradation' => 'Dégradation du matériel',
            'perturbation' => 'Perturbation du cours',
            'autre' => 'Autre (avec description)'
        ];
        return $labels[$type] ?? $type;
    }
}
