<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['ecole_id', 'enseignant_id', 'parent_id'];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
