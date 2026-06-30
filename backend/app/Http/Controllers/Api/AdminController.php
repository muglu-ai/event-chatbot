<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function logs(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 100), 500);
        $offset = (int) $request->query('offset', 0);

        $logs = ChatLog::query()
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get([
                'id', 'session_id', 'original_question', 'improved_question',
                'question', 'answer', 'was_improved', 'learned',
                'tokens_used', 'source', 'provider', 'status', 'created_at',
            ]);

        return response()->json(['logs' => $logs]);
    }

    public function stats(): JsonResponse
    {
        $stats = ChatLog::query()
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('COALESCE(SUM(tokens_used), 0) as total_tokens')
            ->selectRaw('ROUND(COALESCE(AVG(tokens_used), 0)) as avg_tokens')
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count")
            ->selectRaw("SUM(CASE WHEN source = 'kb' THEN 1 ELSE 0 END) as kb_count")
            ->selectRaw("SUM(CASE WHEN source = 'ai' THEN 1 ELSE 0 END) as ai_count")
            ->selectRaw("SUM(CASE WHEN was_improved = 1 THEN 1 ELSE 0 END) as improved_count")
            ->selectRaw("SUM(CASE WHEN learned = 1 THEN 1 ELSE 0 END) as learned_count")
            ->first();

        return response()->json($stats);
    }
}
