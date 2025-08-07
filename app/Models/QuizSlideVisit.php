<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizSlideVisit extends Model
{
    protected $fillable = [
        'quiz_session_id',
        'slide_id',
        'slide_title',
        'slide_sequence',
        'visited_at',
        'time_spent_seconds',
        'slide_metadata'
    ];

    protected $casts = [
        'visited_at' => 'datetime',
        'slide_metadata' => 'array',
    ];

    /**
     * Get the session that owns the slide visit.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }

    /**
     * Get the quiz through the session.
     */
    public function quiz()
    {
        return $this->session->quiz;
    }
}
