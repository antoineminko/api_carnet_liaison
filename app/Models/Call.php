<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'caller_id',
        'caller_type',
        'receiver_id',
        'receiver_type',
        'type',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'rejection_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
