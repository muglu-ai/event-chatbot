<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearnedQa extends Model
{
    protected $fillable = [
        'topic_id',
        'question',
        'answer',
        'keywords',
        'hit_count',
        'source',
    ];

    protected $casts = [
        'keywords' => 'array',
        'hit_count' => 'integer',
    ];
}
