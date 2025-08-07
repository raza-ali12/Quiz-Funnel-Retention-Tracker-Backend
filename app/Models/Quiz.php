<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    protected $fillable = [
        'quiz_id',
        'title',
        'description',
        'url_path',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the sessions for this quiz.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }

    /**
     * Get analytics for this quiz.
     */
    public function getAnalytics()
    {
        $totalSessions = $this->sessions()->count();
        $completedSessions = $this->sessions()->where('is_completed', true)->count();
        
        $slideAnalytics = $this->sessions()
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

        return [
            'quiz_id' => $this->quiz_id,
            'title' => $this->title,
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'completion_rate' => $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 2) : 0,
            'slide_analytics' => $slideAnalytics,
        ];
    }
}
