<?php

namespace App\Services\Chat;

use App\Services\Chat\Dto\ChatResult;

class KnowledgeBaseService
{
    private array $knowledge;

    private const OFF_TOPIC_TERMS = [
        'weather', 'cricket', 'football', 'movie', 'recipe', 'stock', 'bitcoin',
        'politics', 'election', 'celebrity', 'joke', 'poem', 'homework', 'math problem',
        'python code', 'write code', 'translate', 'restaurant', 'pizza', 'dating',
    ];

    private const GENERIC_KEYWORDS = ['what is', 'when', 'where', 'how', 'about', 'help'];

    private const GENERIC_EVENT_TERMS = [
        'semicon', 'india', '2026', '2025', 'event', 'conference', 'expo', 'semi',
    ];

    public function __construct(
        private readonly KnowledgeLearnerService $learner,
        private readonly AnswerComposerService $composer,
    ) {
        $path = config('chatbot.knowledge_path');
        $this->knowledge = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function getContextPrompt(array $history = []): string
    {
        $event = $this->knowledge['event'];
        $facts = collect($this->allEntries())
            ->map(fn ($e) => $e['answer'])
            ->take(6)
            ->implode(' ');

        $prompt = sprintf(
            'SEMICON India 2026 assistant. Event: %s, %s, %s. Facts: %s. Answer ONLY about this event in under 60 words. Off-topic: say please ask about the event.',
            $event['dates'],
            $event['venue'],
            $event['theme'],
            $facts
        );

        if (! empty($history)) {
            $memory = app(ConversationMemoryService::class)->buildContextPrompt($history);
            $prompt .= "\n\n".$memory;
        }

        return $prompt;
    }

    public function handle(string $message, array $history = []): ChatResult
    {
        $question = trim($message);
        $inputTokens = TokenEstimator::estimate($question);

        if ($question === '') {
            $answer = 'Please type a question about SEMICON India 2026.';

            return new ChatResult($answer, $inputTokens + TokenEstimator::estimate($answer), 'system');
        }

        if (! $this->isEventRelated($question, $history)) {
            $answer = $this->knowledge['offTopicMessage'];

            return new ChatResult(
                $answer,
                $inputTokens + TokenEstimator::estimate($answer),
                'filter',
                'rejected',
                'kb'
            );
        }

        $match = $this->findBestMatch($question);
        if ($match) {
            $answer = $this->composer->compose(
                $match['id'],
                $question,
                $match['answer'],
                $history
            );

            $answer = TokenEstimator::trimToBudget(
                $answer,
                config('chatbot.max_response_tokens', 80)
            );

            return new ChatResult(
                $answer,
                $inputTokens + TokenEstimator::estimate($answer),
                'kb',
                'answered',
                'kb',
                $match['id']
            );
        }

        $answer = $this->knowledge['noMatchMessage'];

        return new ChatResult(
            $answer,
            $inputTokens + TokenEstimator::estimate($answer),
            'kb',
            'answered',
            'kb'
        );
    }

    public function hasMatch(string $message): bool
    {
        return $this->findBestMatch($message) !== null;
    }

    private function allEntries(): array
    {
        return array_merge($this->knowledge['entries'], $this->learner->getLearnedEntries());
    }

    private function isOffTopic(string $query): bool
    {
        $q = $this->normalize($query);

        foreach (self::OFF_TOPIC_TERMS as $term) {
            if (str_contains($q, $term)) {
                return true;
            }
        }

        return false;
    }

    private function isEventRelated(string $query, array $history = []): bool
    {
        $q = $this->normalize($query);
        if ($q === '' || $this->isOffTopic($q)) {
            return false;
        }

        if (! empty($history)) {
            return true;
        }

        $eventTerms = collect($this->allEntries())
            ->flatMap(fn ($e) => $e['keywords'])
            ->merge([
                'semicon', 'semiconductor', 'chip', 'silicon', 'microelectronics',
                'yashobhoomi', 'new delhi', 'semi', 'ism', 'meity', 'expo',
                'conference', 'event', 'september', 'sept', '2026', '2025',
                'portal', 'semiconindia',
            ])
            ->map(fn ($t) => strtolower($t))
            ->unique();

        foreach ($eventTerms as $term) {
            if (str_contains($q, $term)) {
                return true;
            }
        }

        $patterns = [
            '/\b(when|where|how)\b.*\b(event|visit|attend|go|register)\b/',
            '/\b(register|registration|ticket|visa|exhibit|venue|hotel)\b/',
            '/\bsemicon\b/',
            '/\bwhat is\b.*\b(semicon|event|conference|expo)\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $q)) {
                return true;
            }
        }

        return false;
    }

    private function findBestMatch(string $query): ?array
    {
        $q = $this->normalize($query);
        $best = null;
        $bestRank = 0;

        foreach ($this->allEntries() as $entry) {
            $score = $this->scoreEntry($q, $entry);
            $matched = array_filter($entry['keywords'], fn ($k) => str_contains($q, strtolower($k)));
            $specific = array_filter($matched, fn ($k) => ! in_array(strtolower($k), self::GENERIC_KEYWORDS, true));
            $specificity = empty($specific) ? 0 : max(array_map('strlen', $specific));
            $topicBoost = $this->topicBoost($q, $entry);
            $rank = ($score * 1000) + ($specificity * 10) + count($specific) + $topicBoost;

            if ($rank > $bestRank) {
                $bestRank = $rank;
                $best = $entry;
            }
        }

        if ($best === null || $bestRank === 0) {
            return null;
        }

        $hasSpecific = collect($best['keywords'])->contains(
            fn ($k) => ! in_array(strtolower($k), self::GENERIC_KEYWORDS, true)
                && ! in_array(strtolower($k), self::GENERIC_EVENT_TERMS, true)
                && str_contains($q, strtolower($k))
        );

        $score = $this->scoreEntry($q, $best);
        if (! $hasSpecific && $score < 2) {
            return null;
        }

        return $best;
    }

    private function scoreEntry(string $q, array $entry): int
    {
        $score = 0;

        foreach ($entry['keywords'] as $keyword) {
            $k = strtolower($keyword);
            if (! str_contains($q, $k)) {
                continue;
            }
            $score += str_contains($k, ' ') ? 4 : (strlen($k) >= 6 ? 3 : (strlen($k) >= 4 ? 2 : 1));
        }

        return $score;
    }

    private function topicBoost(string $q, array $entry): int
    {
        $id = strtolower($entry['id'] ?? '');

        if ($id !== '' && str_contains($q, str_replace('_', ' ', $id))) {
            return 5000;
        }

        $boost = 0;

        foreach ($entry['keywords'] as $keyword) {
            $k = strtolower($keyword);
            if (in_array($k, self::GENERIC_KEYWORDS, true)
                || in_array($k, self::GENERIC_EVENT_TERMS, true)
                || strlen($k) < 4) {
                continue;
            }
            if (str_contains($q, $k)) {
                $boost = max($boost, 4000 + (strlen($k) * 10));
            }
        }

        return $boost;
    }

    private function normalize(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', ' ', $text) ?? $text;

        return preg_replace('/\s+/', ' ', trim($text)) ?? '';
    }
}
