<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Eleve extends Model
{
    protected $fillable = [
        'nom',
        'prenom',
        'matricule',
        'classe_id',
        'code_secret',
        'qr_code',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($eleve) {
            if (empty($eleve->code_secret) && $eleve->classe_id) {
                // Fetch the classe and its ecole
                $classe = Classe::with('ecole')->find($eleve->classe_id);
                if ($classe && $classe->ecole) {
                    $ecoleCode = $classe->ecole->code_ecole;
                    $classeCode = $classe->code_classe;
                    // Generate a random 4 digit number
                    $numero = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $eleve->code_secret = "{$ecoleCode}-{$classeCode}-{$numero}";
                }
            }
        });
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    public function parents()
    {
        return $this->belongsToMany(ParentUser::class, 'eleve_parents', 'eleve_id', 'parent_id');
    }
}
