<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'messages',
        'turn_count',
    ];

    protected $casts = [
        'messages' => 'array',
        'turn_count' => 'integer',
    ];
}
