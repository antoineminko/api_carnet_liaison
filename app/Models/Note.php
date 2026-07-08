<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'devoir_id',
        'eleve_id',
        'valeur',
        'trimestre',
        'commentaires',
    ];

    public function devoir()
    {
        return $this->belongsTo(Devoir::class);
    }

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }
}
