<?php

namespace App\Services\Chat;

use App\Models\ChatSession;

class ConversationMemoryService
{
    public function getHistory(string $sessionId, ?int $maxTurns = null): array
    {
        $maxTurns = $maxTurns ?? config('chatbot.memory_turns', 6);
        $session = ChatSession::find($sessionId);

        if (! $session || empty($session->messages)) {
            return [];
        }

        return array_slice($session->messages, -($maxTurns * 2));
    }

    public function append(string $sessionId, string $role, string $content): array
    {
        $session = ChatSession::firstOrCreate(
            ['id' => $sessionId],
            ['messages' => [], 'turn_count' => 0]
        );

        $messages = $session->messages ?? [];
        $messages[] = ['role' => $role, 'content' => $content];

        $maxMessages = config('chatbot.memory_turns', 6) * 2;
        if (count($messages) > $maxMessages) {
            $messages = array_slice($messages, -$maxMessages);
        }

        $turnCount = $session->turn_count + ($role === 'user' ? 1 : 0);

        $session->update([
            'messages' => $messages,
            'turn_count' => $turnCount,
        ]);

        return $messages;
    }

    public function buildContextPrompt(array $history): string
    {
        if (empty($history)) {
            return '';
        }

        $lines = collect($history)
            ->map(fn ($m) => ucfirst($m['role']).': '.$m['content'])
            ->implode("\n");

        return "Recent conversation:\n".$lines."\n\nUse this context for follow-up questions.";
    }

    public function syncFromClient(string $sessionId, array $clientHistory): void
    {
        if (empty($clientHistory)) {
            return;
        }

        $maxMessages = config('chatbot.memory_turns', 6) * 2;
        $messages = array_slice($clientHistory, -$maxMessages);

        ChatSession::updateOrCreate(
            ['id' => $sessionId],
            [
                'messages' => $messages,
                'turn_count' => (int) ceil(count(array_filter($messages, fn ($m) => ($m['role'] ?? '') === 'user')) / 1),
            ]
        );
    }
}
