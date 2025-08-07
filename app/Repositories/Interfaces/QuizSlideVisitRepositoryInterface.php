<?php

namespace App\Repositories\Interfaces;

use App\Models\QuizSlideVisit;

interface QuizSlideVisitRepositoryInterface
{
    /**
     * Record a slide visit.
     */
    public function recordSlideVisit(array $data): QuizSlideVisit;

    /**
     * Get slide visit analytics for a quiz.
     */
    public function getSlideAnalytics(string $quizId): array;

    /**
     * Get drop-off analysis for a quiz.
     */
    public function getDropOffAnalysis(string $quizId): array;

    /**
     * Get retention funnel data.
     */
    public function getRetentionFunnel(string $quizId): array;
} 