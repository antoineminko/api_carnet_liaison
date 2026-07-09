<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminInformation extends Model
{
    use HasFactory;

    protected $table = 'admin_informations';

    protected $fillable = [
        'eleve_id',
        'type',
        'titre',
        'contenu',
        'montant',
        'fichier_url',
        'is_read',
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }
}
