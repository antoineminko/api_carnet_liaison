<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ecole_id',
        'enseignant_id',
        'parent_id',
        'eleve_id',
        'objet',
        'date_heure',
        'new_proposed_date',
        'type',
        'mode', 
        'lien_video',
        'statut', 
        'motif',
        'report_reason',
        'requester', 
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
