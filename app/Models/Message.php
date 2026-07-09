<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'sender_type', 'sender_id', 'content', 'fichier_url', 'is_read'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
