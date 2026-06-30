<?php

namespace App\Services\Chat\Contracts;

use App\Services\Chat\Dto\ChatResult;

interface AiProviderInterface
{
    public function name(): string;

    public function isAvailable(): bool;

    public function respond(string $question, string $contextPrompt, array $history = []): ChatResult;
}
