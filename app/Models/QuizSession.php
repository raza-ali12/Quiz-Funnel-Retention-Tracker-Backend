<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizSession extends Model
{
    protected $fillable = [
        'quiz_id',
        'session_id',
        'user_agent',
        'ip_address',
        'started_at',
        'completed_at',
        'is_completed',
        'total_slides_visited',
        'last_slide_sequence'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_completed' => 'boolean',
    ];

    /**
     * Get the quiz that owns the session.
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Get the slide visits for this session.
     */
    public function slideVisits(): HasMany
    {
        return $this->hasMany(QuizSlideVisit::class);
    }

    /**
     * Mark session as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get the last visited slide.
     */
    public function getLastVisitedSlide()
    {
        return $this->slideVisits()
            ->orderBy('slide_sequence', 'desc')
            ->first();
    }
}
