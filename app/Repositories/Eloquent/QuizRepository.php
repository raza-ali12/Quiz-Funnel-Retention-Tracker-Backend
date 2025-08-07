<?php

namespace App\Repositories\Eloquent;

use App\Models\Quiz;
use App\Repositories\Interfaces\QuizRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB; // Added this import for DB facade

class QuizRepository implements QuizRepositoryInterface
{
    public function getAllQuizzes(): Collection
    {
        return Quiz::withCount(['sessions as total_sessions', 'sessions as completed_sessions' => function ($query) {
            $query->where('is_completed', true);
        }])->get()->map(function ($quiz) {
            $quiz->completion_rate = $quiz->total_sessions > 0 ? 
                round(($quiz->completed_sessions / $quiz->total_sessions) * 100, 2) : 0;
            return $quiz;
        });
    }

    public function getOrCreateQuiz(string $quizId, string $urlPath): Quiz
    {
        return Quiz::firstOrCreate(
            ['quiz_id' => $quizId],
            [
                'title' => "Quiz: {$quizId}",
                'url_path' => $urlPath,
                'is_active' => true
            ]
        );
    }

    public function getQuizAnalytics(string $quizId): ?array
    {
        $quiz = Quiz::where('quiz_id', $quizId)->first();
        if (!$quiz) {
            return null;
        }

        $totalSessions = $this->getAllSessions($quizId)->count();
        $completedSessions = $this->getAllSessions($quizId)->where('is_completed', true)->count();
        
        // Get slide analytics with real-time user counts
        $slideAnalytics = $this->getSlideAnalytics($quizId);
        
        // Get current active users (sessions started in last 30 minutes)
        $activeUsers = $this->getAllSessions($quizId)
            ->where('started_at', '>=', now()->subMinutes(30))
            ->count();

        return [
            'quiz_id' => $quiz->quiz_id,
            'title' => $quiz->title,
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'completion_rate' => $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 2) : 0,
            'active_users' => $activeUsers,
            'slide_analytics' => $slideAnalytics,
        ];
    }

    public function getRetentionFunnel(string $quizId): Collection
    {
        $quiz = Quiz::where('quiz_id', $quizId)->first();
        
        if (!$quiz) {
            return collect();
        }

        return $quiz->sessions()
            ->join('quiz_slide_visits', 'quiz_sessions.id', '=', 'quiz_slide_visits.quiz_session_id')
            ->selectRaw('
                slide_id,
                slide_title,
                slide_sequence,
                COUNT(DISTINCT quiz_sessions.id) as visit_count
            ')
            ->groupBy('slide_id', 'slide_title', 'slide_sequence')
            ->orderBy('slide_sequence')
            ->get();
    }

    public function getDropOffAnalysis(string $quizId): Collection
    {
        $quiz = Quiz::where('quiz_id', $quizId)->first();
        
        if (!$quiz) {
            return collect();
        }

        $slideVisits = $quiz->sessions()
            ->join('quiz_slide_visits', 'quiz_sessions.id', '=', 'quiz_slide_visits.quiz_session_id')
            ->selectRaw('
                slide_sequence,
                COUNT(DISTINCT quiz_sessions.id) as visit_count
            ')
            ->groupBy('slide_sequence')
            ->orderBy('slide_sequence')
            ->get();

        $dropOffs = collect();
        $previousCount = 0;

        foreach ($slideVisits as $visit) {
            if ($previousCount > 0) {
                $dropOff = $previousCount - $visit->visit_count;
                $dropOffRate = ($dropOff / $previousCount) * 100;
                
                $dropOffs->push([
                    'from_slide' => $visit->slide_sequence - 1,
                    'to_slide' => $visit->slide_sequence,
                    'drop_off_count' => $dropOff,
                    'drop_off_rate' => round($dropOffRate, 2)
                ]);
            }
            $previousCount = $visit->visit_count;
        }

        return $dropOffs;
    }

    public function getSlideAnalytics(string $quizId): Collection
    {
        $quiz = Quiz::where('quiz_id', $quizId)->first();
        if (!$quiz) {
            return collect();
        }

        // Define all possible slides for this quiz (based on the actual quiz structure)
        $allSlides = [
            ['id' => 'welcome', 'title' => 'Welcome to Our Quiz!', 'sequence' => 1],
            ['id' => 'business-type', 'title' => 'What type of business do you run?', 'sequence' => 2],
            ['id' => 'revenue', 'title' => 'What is your current monthly revenue?', 'sequence' => 3],
            ['id' => 'goals', 'title' => 'What are your main business goals?', 'sequence' => 4],
            ['id' => 'marketing-budget', 'title' => 'What is your marketing budget?', 'sequence' => 5],
            ['id' => 'team-size', 'title' => 'How large is your team?', 'sequence' => 6],
            ['id' => 'industry', 'title' => 'What industry are you in?', 'sequence' => 7],
            ['id' => 'current-tools', 'title' => 'What tools are you currently using?', 'sequence' => 8],
            ['id' => 'timeline', 'title' => 'What is your timeline?', 'sequence' => 9],
            ['id' => 'pain-points', 'title' => 'What are your main pain points?', 'sequence' => 10],
            ['id' => 'contact', 'title' => 'Contact Information', 'sequence' => 11],
        ];

        // Get all slide visits for this quiz with session info
        $slideVisits = DB::table('quiz_slide_visits')
            ->join('quiz_sessions', 'quiz_slide_visits.quiz_session_id', '=', 'quiz_sessions.id')
            ->where('quiz_sessions.quiz_id', $quiz->id)
            ->select([
                'slide_id',
                'slide_title',
                'slide_sequence',
                'quiz_sessions.session_id',
                'quiz_sessions.started_at',
                'quiz_slide_visits.visited_at',
                'quiz_sessions.is_completed'
            ])
            ->orderBy('slide_sequence')
            ->get();

        // Group visits by slide
        $visitsBySlide = $slideVisits->groupBy('slide_sequence');

        // Create analytics for ALL slides
        $slideStats = collect($allSlides)->map(function ($slide) use ($visitsBySlide) {
            $sequence = $slide['sequence'];
            $visits = $visitsBySlide->get($sequence, collect());
            
            $totalVisits = $visits->count();
            $uniqueUsers = $visits->unique('session_id')->count();
            
            // Calculate current active users (visited in last 5 minutes)
            $activeUsers = $visits->where('visited_at', '>=', now()->subMinutes(5))->unique('session_id')->count();
            
            // Get the most recent visit for timestamp
            $lastVisited = $visits->max('visited_at');
            
            return [
                'slide_id' => $slide['id'],
                'slide_title' => $slide['title'],
                'slide_sequence' => $sequence,
                'total_visits' => $totalVisits,
                'unique_users' => $uniqueUsers,
                'active_users' => $activeUsers,
                'last_visited' => $lastVisited
            ];
        });

        return $slideStats;
    }

    public function getSessionStats(string $quizId): array
    {
        $quiz = Quiz::where('quiz_id', $quizId)->first();
        if (!$quiz) {
            return [];
        }

        $sessions = $this->getAllSessions($quizId);
        
        return [
            'total_sessions' => $sessions->count(),
            'completed_sessions' => $sessions->where('is_completed', true)->count(),
            'active_sessions' => $sessions->where('started_at', '>=', now()->subMinutes(30))->count(),
            'avg_session_duration' => $sessions->whereNotNull('completed_at')->avg('completed_at'),
        ];
    }

    private function getAllSessions(string $quizId)
    {
        $quiz = Quiz::where('quiz_id', $quizId)->first();
        if (!$quiz) {
            return collect();
        }

        return $quiz->sessions();
    }
} 