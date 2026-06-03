<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'reporter_id',
        'reporter_type',
        'reported_id',
        'reported_type',
        'reason',
        'description',
        'evidence',
        'status',
        'admin_notes',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Obtenir le label du motif de signalement
     */
    public static function getReasonLabel(string $reason): string
    {
        return match ($reason) {
            'harassment' => 'Harcèlement',
            'inappropriate_content' => 'Propos inappropriés',
            'spam' => 'Spam',
            'fake_account' => 'Faux compte',
            'other' => 'Autre',
            default => $reason,
        };
    }

    /**
     * Obtenir le label du statut
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'En attente',
            'in_review' => 'En cours d\'examen',
            'resolved' => 'Résolu',
            'rejected' => 'Rejeté',
            default => $status,
        };
    }
}
