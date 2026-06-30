<?php

namespace App\Services\Chat\Dto;

class ChatResult
{
    public function __construct(
        public readonly string $answer,
        public readonly int $tokensUsed,
        public readonly string $source,
        public readonly string $status = 'answered',
        public readonly ?string $provider = null,
        public readonly ?string $matchedTopic = null,
    ) {}
}
