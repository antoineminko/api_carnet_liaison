<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Eleve extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom', 'prenom', 'matricule', 'classe_id', 'code_secret', 'qr_code', 'photo',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
    {
        if ($this->photo) {
            return rtrim(env('APP_URL'), '/') . '/storage/' . $this->photo;
        }
        return null;
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class)->with(['ecole', 'profPrincipal', 'enseignants']);
    }

    public function parents()
    {
        return $this->belongsToMany(ParentUser::class, 'eleve_parents', 'eleve_id', 'parent_id')
            ->withPivot('relation', 'is_verified')
            ->withTimestamps();
    }

    public function adminInfos()
    {
        return $this->hasMany(AdminInformation::class);
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }
}
