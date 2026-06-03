<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallSignaling extends Model
{
    use HasFactory;

    protected $table = 'call_signaling';

    protected $fillable = [
        'call_id',
        'type',
        'sdp',
        'sdp_mid',
        'sdp_m_line_index',
        'candidate',
        'processed',
    ];

    protected $casts = [
        'processed' => 'boolean',
        'sdp_m_line_index' => 'integer',
    ];

    public function call()
    {
        return $this->belongsTo(Call::class);
    }
}
