<?php

namespace App\Services\Chat\Providers;

use App\Services\Chat\Contracts\AiProviderInterface;
use App\Services\Chat\Dto\ChatResult;
use App\Services\Chat\TokenEstimator;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'openai';
    }

    public function isAvailable(): bool
    {
        return ! empty(config('chatbot.openai.api_key'));
    }

    public function respond(string $question, string $contextPrompt, array $history = []): ChatResult
    {
        $messages = [['role' => 'system', 'content' => $contextPrompt]];

        foreach ($history as $turn) {
            $role = ($turn['role'] ?? 'user') === 'assistant' || ($turn['role'] ?? '') === 'bot' ? 'assistant' : 'user';
            if (! empty($turn['content'])) {
                $messages[] = ['role' => $role, 'content' => $turn['content']];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        $response = Http::withToken(config('chatbot.openai.api_key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('chatbot.openai.model'),
                'max_tokens' => config('chatbot.max_response_tokens', 80),
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI API error: '.$response->body());
        }

        $answer = trim($response->json('choices.0.message.content', ''));
        $answer = TokenEstimator::trimToBudget($answer, config('chatbot.max_response_tokens', 80));
        $usage = $response->json('usage');
        $tokens = ($usage['total_tokens'] ?? (
            TokenEstimator::estimate($question) +
            TokenEstimator::estimate($contextPrompt) +
            TokenEstimator::estimate($answer)
        ));

        return new ChatResult($answer, $tokens, 'ai', 'answered', $this->name());
    }
}
