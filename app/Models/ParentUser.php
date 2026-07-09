<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class ParentUser extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $table = 'parent_users';

    protected $fillable = [
        'nom', 'prenom', 'email', 'password', 'telephone', 'fcm_token', 'ecole_id',
    ];

    protected $hidden = ['password'];

    public function ecole()
    {
        return $this->belongsTo(Ecole::class);
    }

    public function eleves()
    {
        return $this->belongsToMany(Eleve::class, 'eleve_parents', 'parent_id', 'eleve_id')
            ->withPivot('relation', 'is_verified')
            ->withTimestamps();
    }
}
