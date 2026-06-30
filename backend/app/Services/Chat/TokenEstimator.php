<?php

namespace App\Services\Chat;

class TokenEstimator
{
    public static function estimate(?string $text): int
    {
        if ($text === null || trim($text) === '') {
            return 0;
        }

        return max(1, (int) ceil(str_word_count(trim($text)) * 1.3));
    }

    public static function trimToBudget(string $text, int $maxTokens = 60): string
    {
        $text = trim($text);

        if (self::estimate($text) <= $maxTokens) {
            return $text;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [$text];
        $result = '';
        $tokens = 0;

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            $sentenceTokens = self::estimate($sentence);
            if ($tokens + $sentenceTokens > $maxTokens && $result !== '') {
                break;
            }

            $result = trim($result.' '.$sentence);
            $tokens += $sentenceTokens;
        }

        if ($result !== '') {
            return $result;
        }

        // Last resort: trim at word boundary without mid-word ellipsis
        $words = preg_split('/\s+/', $text) ?: [];
        $built = [];
        $tokens = 0;

        foreach ($words as $word) {
            $wordTokens = (int) ceil(strlen($word) / 4) + 1;
            if ($tokens + $wordTokens > $maxTokens && ! empty($built)) {
                break;
            }
            $built[] = $word;
            $tokens += $wordTokens;
        }

        $joined = implode(' ', $built);

        return rtrim($joined, ',;:').'.';
    }
}
