<?php

namespace App\Repositories\Eloquent;

use App\Models\QuizSession;
use App\Repositories\Interfaces\QuizSessionRepositoryInterface;

class QuizSessionRepository implements QuizSessionRepositoryInterface
{
    public function createSession(array $data): QuizSession
    {
        return QuizSession::create($data);
    }

    public function findBySessionId(string $sessionId): ?QuizSession
    {
        return QuizSession::where('session_id', $sessionId)->first();
    }

    /**
     * Alias for findBySessionId for backward compatibility
     */
    public function getSessionBySessionId(string $sessionId): ?QuizSession
    {
        return $this->findBySessionId($sessionId);
    }

    public function updateSession(int $sessionId, array $data): bool
    {
        $session = QuizSession::find($sessionId);
        
        if (!$session) {
            return false;
        }

        $session->update($data);
        return true;
    }

    public function markSessionCompleted(string $sessionId): bool
    {
        $session = $this->findBySessionId($sessionId);
        
        if (!$session) {
            return false;
        }

        $session->markAsCompleted();
        return true;
    }

    public function getSessionStats(string $quizId): array
    {
        $sessions = QuizSession::whereHas('quiz', function ($query) use ($quizId) {
            $query->where('quiz_id', $quizId);
        });

        $totalSessions = $sessions->count();
        $completedSessions = $sessions->where('is_completed', true)->count();
        $avgSlidesVisited = $sessions->avg('total_slides_visited');
        $avgSessionDuration = $sessions->whereNotNull('completed_at')
            ->get()
            ->avg(function ($session) {
                return $session->started_at->diffInSeconds($session->completed_at);
            });

        return [
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'completion_rate' => $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 2) : 0,
            'avg_slides_visited' => round($avgSlidesVisited ?? 0, 2),
            'avg_session_duration_seconds' => round($avgSessionDuration ?? 0, 2),
        ];
    }
} 