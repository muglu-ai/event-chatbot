<?php

namespace App\Services\Chat;

use App\Models\ChatLog;
use App\Models\LearnedQa;

class SuggestionService
{
    private array $knowledge;

    public function __construct()
    {
        $path = config('chatbot.knowledge_path');
        $this->knowledge = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function suggest(?string $query = '', int $limit = 5): array
    {
        $query = strtolower(trim($query ?? ''));
        $candidates = collect($this->getDefaultQuestions())
            ->merge($this->getPopularQuestions())
            ->merge($this->getLearnedQuestions())
            ->unique()
            ->values();

        if ($query === '') {
            return $candidates->take($limit)->values()->all();
        }

        return $candidates
            ->filter(fn ($q) => str_contains(strtolower($q), $query) || $this->fuzzyMatch($q, $query))
            ->sortByDesc(fn ($q) => $this->scoreMatch($q, $query))
            ->take($limit)
            ->values()
            ->all();
    }

    private function getDefaultQuestions(): array
    {
        return $this->knowledge['recommendedQuestions'] ?? [
            'When is SEMICON India 2026?',
            'Where is the venue?',
            'How do I register as a visitor?',
            'How do I apply for a visa?',
            'Who are the organizers?',
        ];
    }

    private function getPopularQuestions(): array
    {
        return ChatLog::query()
            ->where('status', 'answered')
            ->whereNotNull('improved_question')
            ->selectRaw('COALESCE(improved_question, question) as q, COUNT(*) as c')
            ->groupBy('q')
            ->orderByDesc('c')
            ->limit(10)
            ->pluck('q')
            ->all();
    }

    private function getLearnedQuestions(): array
    {
        return LearnedQa::query()
            ->orderByDesc('hit_count')
            ->limit(10)
            ->pluck('question')
            ->all();
    }

    private function fuzzyMatch(string $question, string $query): bool
    {
        $words = array_filter(explode(' ', $query));

        foreach ($words as $word) {
            if (strlen($word) >= 3 && str_contains(strtolower($question), $word)) {
                return true;
            }
        }

        return false;
    }

    private function scoreMatch(string $question, string $query): int
    {
        $q = strtolower($question);
        $score = 0;

        if (str_starts_with($q, $query)) {
            $score += 10;
        }

        foreach (explode(' ', $query) as $word) {
            if (strlen($word) >= 3 && str_contains($q, $word)) {
                $score += 3;
            }
        }

        return $score;
    }
}
