<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatLog extends Model
{
    protected $fillable = [
        'session_id',
        'original_question',
        'improved_question',
        'context_snapshot',
        'was_improved',
        'learned',
        'question',
        'answer',
        'tokens_used',
        'source',
        'provider',
        'status',
    ];

    protected $casts = [
        'tokens_used' => 'integer',
        'context_snapshot' => 'array',
        'was_improved' => 'boolean',
        'learned' => 'boolean',
    ];
}
