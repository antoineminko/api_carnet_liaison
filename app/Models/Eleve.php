<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Eleve extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    public function parents()
    {
        return $this->belongsToMany(ParentUser::class, 'eleve_parents', 'eleve_id', 'parent_id')
                    ->withPivot('relation')
                    ->withTimestamps();
    }
}
