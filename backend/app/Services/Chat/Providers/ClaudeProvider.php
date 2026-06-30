<?php

namespace App\Services\Chat\Providers;

use App\Services\Chat\Contracts\AiProviderInterface;
use App\Services\Chat\Dto\ChatResult;
use App\Services\Chat\TokenEstimator;
use Illuminate\Support\Facades\Http;

class ClaudeProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'claude';
    }

    public function isAvailable(): bool
    {
        return ! empty(config('chatbot.anthropic.api_key'));
    }

    public function respond(string $question, string $contextPrompt, array $history = []): ChatResult
    {
        $inputTokens = TokenEstimator::estimate($question) + TokenEstimator::estimate($contextPrompt);

        $messages = collect($history)
            ->map(fn ($m) => [
                'role' => ($m['role'] ?? 'user') === 'assistant' || ($m['role'] ?? '') === 'bot' ? 'assistant' : 'user',
                'content' => $m['content'] ?? '',
            ])
            ->filter(fn ($m) => $m['content'] !== '')
            ->values()
            ->all();

        $messages[] = ['role' => 'user', 'content' => $question];

        $response = Http::withHeaders([
            'x-api-key' => config('chatbot.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('chatbot.anthropic.model'),
            'max_tokens' => config('chatbot.max_response_tokens', 80),
            'system' => $contextPrompt,
            'messages' => $messages,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Claude API error: '.$response->body());
        }

        $answer = collect($response->json('content'))
            ->where('type', 'text')
            ->pluck('text')
            ->implode(' ');

        $answer = TokenEstimator::trimToBudget(trim($answer), config('chatbot.max_response_tokens', 80));
        $usage = $response->json('usage');
        $tokens = ($usage['input_tokens'] ?? $inputTokens) + ($usage['output_tokens'] ?? TokenEstimator::estimate($answer));

        return new ChatResult($answer, $tokens, 'ai', 'answered', $this->name());
    }
}
