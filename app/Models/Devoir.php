<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Devoir extends Model
{
    use HasFactory;

    protected $fillable = [
        'classe_id',
        'enseignant_id',
        'matiere',
        'type',
        'titre',
        'description',
        'date_remise',
    ];

    protected $casts = [
        'date_remise' => 'date',
    ];

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class);
    }
}
