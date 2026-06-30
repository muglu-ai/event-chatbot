<?php

namespace App\Services\Chat;

use App\Models\ChatLog;
use App\Models\LearnedQa;
use Illuminate\Support\Str;

class KnowledgeLearnerService
{
    public function learnFromExchange(
        string $originalQuestion,
        string $improvedQuestion,
        string $answer,
        string $source,
        ?string $topicId = null,
    ): ?LearnedQa {
        if (! config('chatbot.auto_learn', true)) {
            return null;
        }

        if ($source === 'filter' || strlen(trim($answer)) < 20) {
            return null;
        }

        $existing = LearnedQa::query()
            ->where('question', $improvedQuestion)
            ->orWhere('answer', $answer)
            ->first();

        if ($existing) {
            $existing->increment('hit_count');

            return $existing;
        }

        $similarCount = ChatLog::query()
            ->where('status', 'answered')
            ->where(function ($q) use ($originalQuestion, $improvedQuestion) {
                $q->where('question', 'like', '%'.Str::limit($originalQuestion, 40, '').'%')
                    ->orWhere('improved_question', $improvedQuestion);
            })
            ->count();

        $minHits = config('chatbot.learn_min_hits', 2);

        if ($source === 'kb' && $similarCount < $minHits) {
            return null;
        }

        if ($source === 'ai' || $similarCount >= $minHits) {
            return LearnedQa::create([
                'topic_id' => $topicId,
                'question' => $improvedQuestion,
                'answer' => TokenEstimator::trimToBudget($answer, config('chatbot.max_response_tokens', 80)),
                'keywords' => $this->extractKeywords($improvedQuestion),
                'hit_count' => max(1, $similarCount),
                'source' => $source === 'ai' ? 'ai' : 'auto',
            ]);
        }

        return null;
    }

    public function getLearnedEntries(): array
    {
        return LearnedQa::query()
            ->orderByDesc('hit_count')
            ->get()
            ->map(fn (LearnedQa $qa) => [
                'id' => 'learned_'.$qa->id,
                'keywords' => $qa->keywords ?? $this->extractKeywords($qa->question),
                'answer' => $qa->answer,
            ])
            ->all();
    }

    private function extractKeywords(string $question): array
    {
        $stopWords = [
            'the', 'a', 'an', 'is', 'are', 'for', 'to', 'do', 'i', 'how', 'what', 'when', 'where', 'about',
            'semicon', 'india', '2026', '2025', 'event', 'conference', 'expo',
        ];
        $words = preg_split('/\s+/', strtolower(preg_replace('/[^\w\s]/', '', $question) ?? '')) ?: [];

        return array_values(array_filter($words, fn ($w) => strlen($w) >= 3 && ! in_array($w, $stopWords, true)));
    }
}
