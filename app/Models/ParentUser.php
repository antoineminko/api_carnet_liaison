<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentUser extends Model
{
    use HasFactory;

    protected $table = 'parent_users';

    protected $fillable = [
        'nom', 'prenom', 'email', 'password', 'telephone', 'fcm_token',
    ];

    protected $hidden = ['password'];

    public function enfants()
    {
        return $this->belongsToMany(Eleve::class, 'eleve_parents', 'parent_id', 'eleve_id')
                    ->withPivot('relation')
                    ->withTimestamps();
    }
}
