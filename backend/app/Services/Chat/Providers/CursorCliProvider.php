<?php

namespace App\Services\Chat\Providers;

use App\Services\Chat\Contracts\AiProviderInterface;
use App\Services\Chat\Dto\ChatResult;
use App\Services\Chat\TokenEstimator;
use Illuminate\Support\Facades\Process;

class CursorCliProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'cursor';
    }

    public function isAvailable(): bool
    {
        return (bool) config('chatbot.cursor_cli.enabled');
    }

    public function respond(string $question, string $contextPrompt, array $history = []): ChatResult
    {
        $historyText = collect($history)
            ->map(fn ($m) => ucfirst($m['role'] ?? 'user').': '.($m['content'] ?? ''))
            ->implode("\n");

        $prompt = $contextPrompt."\n\n".$historyText."\n\nUser: ".$question;
        $command = config('chatbot.cursor_cli.command');
        $timeout = config('chatbot.cursor_cli.timeout', 60);

        $result = Process::timeout($timeout)->run(
            $command.' '.escapeshellarg($prompt)
        );

        if (! $result->successful()) {
            throw new \RuntimeException('Cursor CLI failed: '.$result->errorOutput());
        }

        $answer = TokenEstimator::trimToBudget(
            trim($result->output()),
            config('chatbot.max_response_tokens', 80)
        );

        $tokens = TokenEstimator::estimate($prompt) + TokenEstimator::estimate($answer);

        return new ChatResult($answer, $tokens, 'ai', 'answered', $this->name());
    }
}
