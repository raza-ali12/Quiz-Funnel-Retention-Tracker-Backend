<?php

namespace App\Repositories\Eloquent;

use App\Models\QuizSlideVisit;
use App\Repositories\Interfaces\QuizSlideVisitRepositoryInterface;
use Illuminate\Support\Facades\DB;

class QuizSlideVisitRepository implements QuizSlideVisitRepositoryInterface
{
    public function recordSlideVisit(array $data): QuizSlideVisit
    {
        return QuizSlideVisit::create($data);
    }

    public function getSlideAnalytics(string $quizId): array
    {
        return QuizSlideVisit::join('quiz_sessions', 'quiz_slide_visits.quiz_session_id', '=', 'quiz_sessions.id')
            ->join('quizzes', 'quiz_sessions.quiz_id', '=', 'quizzes.id')
            ->where('quizzes.quiz_id', $quizId)
            ->selectRaw('
                slide_id,
                slide_title,
                slide_sequence,
                COUNT(DISTINCT quiz_sessions.id) as visit_count,
                AVG(time_spent_seconds) as avg_time_spent
            ')
            ->groupBy('slide_id', 'slide_title', 'slide_sequence')
            ->orderBy('slide_sequence')
            ->get()
            ->toArray();
    }

    public function getDropOffAnalysis(string $quizId): array
    {
        $slideVisits = $this->getSlideAnalytics($quizId);
        
        if (empty($slideVisits)) {
            return [];
        }

        $dropOffs = [];
        $previousCount = null;

        foreach ($slideVisits as $slide) {
            $currentCount = $slide['visit_count'];
            
            if ($previousCount !== null) {
                $dropOff = $previousCount - $currentCount;
                $dropOffRate = $previousCount > 0 ? round(($dropOff / $previousCount) * 100, 2) : 0;
                
                $dropOffs[] = [
                    'from_slide' => $slideVisits[array_search($slide, $slideVisits) - 1]['slide_id'] ?? 'start',
                    'to_slide' => $slide['slide_id'],
                    'drop_off_count' => $dropOff,
                    'drop_off_rate' => $dropOffRate,
                    'remaining_users' => $currentCount,
                ];
            }
            
            $previousCount = $currentCount;
        }

        return $dropOffs;
    }

    public function getRetentionFunnel(string $quizId): array
    {
        $slideVisits = $this->getSlideAnalytics($quizId);
        
        if (empty($slideVisits)) {
            return [];
        }

        $totalSessions = QuizSlideVisit::join('quiz_sessions', 'quiz_slide_visits.quiz_session_id', '=', 'quiz_sessions.id')
            ->join('quizzes', 'quiz_sessions.quiz_id', '=', 'quizzes.id')
            ->where('quizzes.quiz_id', $quizId)
            ->distinct('quiz_sessions.id')
            ->count('quiz_sessions.id');

        $funnel = [];
        
        foreach ($slideVisits as $slide) {
            $retentionRate = $totalSessions > 0 ? round(($slide['visit_count'] / $totalSessions) * 100, 2) : 0;
            
            $funnel[] = [
                'slide_id' => $slide['slide_id'],
                'slide_title' => $slide['slide_title'],
                'slide_sequence' => $slide['slide_sequence'],
                'visit_count' => $slide['visit_count'],
                'retention_rate' => $retentionRate,
                'avg_time_spent' => round($slide['avg_time_spent'] ?? 0, 2),
            ];
        }

        return $funnel;
    }
} 