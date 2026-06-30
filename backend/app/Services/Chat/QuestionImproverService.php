<?php

namespace App\Services\Chat;

class QuestionImproverService
{
    private const FOLLOW_UP_PATTERNS = [
        '/^(what about|how about|and)\s+(visa|registration|venue|hotels?|travel|program|exhibit)/i' => [
            'visa' => 'How do I apply for a visa for SEMICON India 2026?',
            'registration' => 'How do I register as a visitor for SEMICON India 2026?',
            'venue' => 'Where is the SEMICON India 2026 venue?',
            'hotel' => 'What hotels are recommended near SEMICON India 2026?',
            'hotels' => 'What hotels are recommended near SEMICON India 2026?',
            'travel' => 'How do I plan travel for SEMICON India 2026?',
            'program' => 'What is the SEMICON India 2026 program agenda?',
            'exhibit' => 'How can my company exhibit at SEMICON India 2026?',
        ],
        '/^(when|where|how)\??$/i' => [
            'when' => 'When is SEMICON India 2026?',
            'where' => 'Where is SEMICON India 2026 held?',
            'how' => 'How do I register for SEMICON India 2026?',
        ],
    ];

    public function improve(string $question, array $history = []): array
    {
        $original = trim($question);
        $improved = $original;
        $wasImproved = false;

        if ($original === '') {
            return compact('original', 'improved', 'wasImproved');
        }

        $improved = $this->expandShorthand($improved, $history, $wasImproved);
        $improved = $this->applyContextFollowUp($improved, $history, $wasImproved);
        $improved = $this->normalizeQuestion($improved, $wasImproved);

        if (strcasecmp($improved, $original) !== 0) {
            $wasImproved = true;
        }

        return [
            'original' => $original,
            'improved' => $improved,
            'wasImproved' => $wasImproved,
        ];
    }

    private function expandShorthand(string $question, array $history, bool &$wasImproved): string
    {
        $q = trim($question);

        foreach (self::FOLLOW_UP_PATTERNS as $pattern => $map) {
            if (! preg_match($pattern, $q, $matches)) {
                continue;
            }

            $key = strtolower($matches[count($matches) - 1] ?? '');
            if (isset($map[$key])) {
                $wasImproved = true;

                return $map[$key];
            }
        }

        if (preg_match('/^(when|where|how)\??$/i', $q, $m)) {
            $wasImproved = true;
            $map = [
                'when' => 'When is SEMICON India 2026?',
                'where' => 'Where is SEMICON India 2026 held?',
                'how' => 'How do I register for SEMICON India 2026?',
            ];

            return $map[strtolower($m[1])] ?? $q;
        }

        if (preg_match('/^(dates?|venue|register|visa|hotels?)\??$/i', $q, $m)) {
            $wasImproved = true;
            $topic = strtolower(rtrim($m[1], 's'));

            return match ($topic) {
                'date' => 'When is SEMICON India 2026?',
                'venue' => 'Where is the SEMICON India 2026 venue?',
                'register' => 'How do I register for SEMICON India 2026?',
                'visa' => 'How do I get a visa for SEMICON India 2026?',
                'hotel' => 'What hotels are near SEMICON India 2026?',
                default => $q,
            };
        }

        return $q;
    }

    private function applyContextFollowUp(string $question, array $history, bool &$wasImproved): string
    {
        if (count($history) < 2) {
            return $question;
        }

        $q = strtolower($question);
        $isShortFollowUp = strlen($question) < 30 && preg_match('/^(what about|how about|and|also|that|it|there)\b/i', $question);

        if (! $isShortFollowUp) {
            return $question;
        }

        $lastBot = collect($history)->reverse()->first(fn ($m) => ($m['role'] ?? '') === 'assistant' || ($m['role'] ?? '') === 'bot');

        if (! $lastBot) {
            return $question;
        }

        $lastAnswer = strtolower($lastBot['content'] ?? '');

        if (str_contains($lastAnswer, 'registration') || str_contains($lastAnswer, 'register')) {
            if (str_contains($q, 'visa') || str_contains($q, 'invitation')) {
                $wasImproved = true;

                return 'How do I get a visa invitation letter after registering for SEMICON India 2026?';
            }
        }

        if (str_contains($q, 'that') || str_contains($q, 'it')) {
            $lastUser = collect($history)->reverse()->first(fn ($m) => ($m['role'] ?? '') === 'user');
            if ($lastUser && ! empty($lastUser['content'])) {
                $wasImproved = true;

                return 'Tell me more about: '.$lastUser['content'];
            }
        }

        return $question;
    }

    private function normalizeQuestion(string $question, bool &$wasImproved): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($question)) ?? $question;

        if (! str_ends_with($normalized, '?') && strlen($normalized) > 10) {
            $starters = ['when', 'where', 'how', 'what', 'who', 'can', 'is', 'are', 'do', 'does'];
            $first = strtolower(strtok($normalized, ' ') ?: '');

            if (in_array($first, $starters, true)) {
                $normalized .= '?';
                $wasImproved = true;
            }
        }

        if (str_word_count($normalized) >= 5) {
            return $normalized;
        }

        if (! preg_match('/semicon|event|2026/i', $normalized) && strlen($normalized) < 50) {
            $eventTerms = ['register', 'registration', 'visa', 'venue', 'exhibit', 'hotel', 'program', 'date'];
            foreach ($eventTerms as $term) {
                if (stripos($normalized, $term) !== false) {
                    $wasImproved = true;

                    return rtrim($normalized, '?').' for SEMICON India 2026?';
                }
            }
        }

        return $normalized;
    }
}
