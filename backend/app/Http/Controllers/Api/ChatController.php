<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatLog;
use App\Services\Chat\ChatOrchestrator;
use App\Services\Chat\ConversationMemoryService;
use App\Services\Chat\KnowledgeLearnerService;
use App\Services\Chat\QuestionImproverService;
use App\Services\Chat\SuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatOrchestrator $chat,
        private readonly ConversationMemoryService $memory,
        private readonly QuestionImproverService $improver,
        private readonly SuggestionService $suggestions,
        private readonly KnowledgeLearnerService $learner,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'sessionId' => ['nullable', 'uuid'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant,bot'],
            'history.*.content' => ['required_with:history', 'string', 'max:1000'],
        ]);

        $sessionId = $validated['sessionId'] ?? (string) Str::uuid();
        $clientHistory = $validated['history'] ?? [];

        if (! empty($clientHistory)) {
            $this->memory->syncFromClient($sessionId, $clientHistory);
        }

        $history = ! empty($clientHistory)
            ? $clientHistory
            : $this->memory->getHistory($sessionId);

        $improved = $this->improver->improve($validated['message'], $history);
        $question = $improved['improved'];

        $result = $this->chat->handle($question, $history);

        $this->memory->append($sessionId, 'user', $validated['message']);
        $this->memory->append($sessionId, 'assistant', $result->answer);

        $learned = $this->learner->learnFromExchange(
            $improved['original'],
            $question,
            $result->answer,
            $result->source,
            $result->matchedTopic
        );

        ChatLog::create([
            'session_id' => $sessionId,
            'original_question' => $improved['original'],
            'improved_question' => $question,
            'context_snapshot' => array_slice($history, -6),
            'was_improved' => $improved['wasImproved'],
            'learned' => $learned !== null,
            'question' => $improved['original'],
            'answer' => $result->answer,
            'tokens_used' => $result->tokensUsed,
            'source' => $result->source,
            'provider' => $result->provider,
            'status' => $result->status,
        ]);

        return response()->json([
            'answer' => $result->answer,
            'tokensUsed' => $result->tokensUsed,
            'source' => $result->source,
            'provider' => $result->provider,
            'sessionId' => $sessionId,
            'improvedQuestion' => $improved['wasImproved'] ? $question : null,
            'wasImproved' => $improved['wasImproved'],
            'suggestions' => $this->suggestions->suggest('', 4),
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->query('q', '');

        return response()->json([
            'suggestions' => $this->suggestions->suggest($query, 5),
        ]);
    }

    public function sessionHistory(string $sessionId): JsonResponse
    {
        if (! Str::isUuid($sessionId)) {
            return response()->json(['error' => 'Invalid session'], 422);
        }

        return response()->json([
            'sessionId' => $sessionId,
            'messages' => $this->memory->getHistory($sessionId),
            'suggestions' => $this->suggestions->suggest('', 5),
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'event' => 'SEMICON India 2026',
            'providers' => $this->chat->availableProviders(),
            'aiMode' => config('chatbot.ai_provider'),
            'memoryTurns' => config('chatbot.memory_turns'),
            'autoLearn' => config('chatbot.auto_learn'),
        ]);
    }
}
