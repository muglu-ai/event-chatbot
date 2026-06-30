<?php

namespace App\Services\Chat;

use App\Services\Chat\Contracts\AiProviderInterface;
use App\Services\Chat\Dto\ChatResult;
use App\Services\Chat\Providers\ClaudeProvider;
use App\Services\Chat\Providers\CursorCliProvider;
use App\Services\Chat\Providers\OpenAiProvider;

class ChatOrchestrator
{
    /** @var array<string, AiProviderInterface> */
    private array $providers;

    public function __construct(
        private readonly KnowledgeBaseService $knowledgeBase,
        ClaudeProvider $claude,
        OpenAiProvider $openAi,
        CursorCliProvider $cursor,
    ) {
        $this->providers = [
            'claude' => $claude,
            'openai' => $openAi,
            'cursor' => $cursor,
        ];
    }

    public function handle(string $message, array $history = []): ChatResult
    {
        $mode = strtolower(config('chatbot.ai_provider', 'auto'));

        if ($mode === 'kb') {
            return $this->knowledgeBase->handle($message, $history);
        }

        if (isset($this->providers[$mode])) {
            return $this->respondWithAi($this->providers[$mode], $message, $history);
        }

        $kbResult = $this->knowledgeBase->handle($message, $history);

        if ($kbResult->status === 'rejected') {
            return $kbResult;
        }

        if ($this->knowledgeBase->hasMatch($message)) {
            return $kbResult;
        }

        $aiProvider = $this->resolveFallbackProvider();
        if ($aiProvider === null) {
            return $kbResult;
        }

        try {
            return $this->respondWithAi($aiProvider, $message, $history);
        } catch (\Throwable) {
            return $kbResult;
        }
    }

    public function availableProviders(): array
    {
        $list = ['kb' => true];

        foreach ($this->providers as $name => $provider) {
            $list[$name] = $provider->isAvailable();
        }

        return $list;
    }

    private function respondWithAi(AiProviderInterface $provider, string $message, array $history): ChatResult
    {
        if (! $provider->isAvailable()) {
            throw new \RuntimeException("AI provider [{$provider->name()}] is not configured.");
        }

        $kbCheck = $this->knowledgeBase->handle($message, $history);
        if ($kbCheck->status === 'rejected') {
            return $kbCheck;
        }

        return $provider->respond(
            $message,
            $this->knowledgeBase->getContextPrompt($history),
            $history
        );
    }

    private function resolveFallbackProvider(): ?AiProviderInterface
    {
        foreach (['claude', 'openai', 'cursor'] as $name) {
            if ($this->providers[$name]->isAvailable()) {
                return $this->providers[$name];
            }
        }

        return null;
    }
}
