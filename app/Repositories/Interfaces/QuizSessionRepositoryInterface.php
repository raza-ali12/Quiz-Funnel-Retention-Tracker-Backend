<?php

namespace App\Repositories\Interfaces;

use App\Models\QuizSession;

interface QuizSessionRepositoryInterface
{
    /**
     * Create a new quiz session.
     */
    public function createSession(array $data): QuizSession;

    /**
     * Find a session by session ID.
     */
    public function findBySessionId(string $sessionId): ?QuizSession;

    /**
     * Get session by session ID (alias for findBySessionId).
     */
    public function getSessionBySessionId(string $sessionId): ?QuizSession;

    /**
     * Update session data.
     */
    public function updateSession(int $sessionId, array $data): bool;

    /**
     * Update session completion status.
     */
    public function markSessionCompleted(string $sessionId): bool;

    /**
     * Get session statistics.
     */
    public function getSessionStats(string $quizId): array;
} 