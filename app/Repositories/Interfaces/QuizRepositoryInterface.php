<?php

namespace App\Repositories\Interfaces;

use App\Models\Quiz;
use Illuminate\Support\Collection;

interface QuizRepositoryInterface
{
    public function getAllQuizzes(): Collection;
    
    public function getOrCreateQuiz(string $quizId, string $urlPath): Quiz;
    
    public function getQuizAnalytics(string $quizId): ?array;
    
    public function getRetentionFunnel(string $quizId): Collection;
    
    public function getDropOffAnalysis(string $quizId): Collection;
    
    public function getSlideAnalytics(string $quizId): Collection;
    
    public function getSessionStats(string $quizId): array;
} 