<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\QuizRepositoryInterface;
use App\Repositories\Interfaces\QuizSessionRepositoryInterface;
use App\Repositories\Interfaces\QuizSlideVisitRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class QuizTrackingController extends Controller
{
    protected $quizRepository;
    protected $sessionRepository;
    protected $slideVisitRepository;

    public function __construct(
        QuizRepositoryInterface $quizRepository,
        QuizSessionRepositoryInterface $sessionRepository,
        QuizSlideVisitRepositoryInterface $slideVisitRepository
    ) {
        $this->quizRepository = $quizRepository;
        $this->sessionRepository = $sessionRepository;
        $this->slideVisitRepository = $slideVisitRepository;
    }

    public function startSession(Request $request): JsonResponse
    {
        $request->validate([
            'url_path' => 'required|string',
            'user_agent' => 'nullable|string',
            'ip_address' => 'nullable|string',
        ]);

        try {
            $urlPath = $request->input('url_path');
            $quizId = $this->extractQuizId($urlPath);
            
            // Get or create quiz
            $quiz = $this->quizRepository->getOrCreateQuiz($quizId, $urlPath);
            
            // Create session
            $sessionId = Str::uuid()->toString();
            $session = $this->sessionRepository->createSession([
                'quiz_id' => $quiz->id,
                'session_id' => $sessionId,
                'user_agent' => $request->input('user_agent'),
                'ip_address' => $request->input('ip_address'),
                'started_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'quiz_id' => $quizId,
                'message' => 'Session started successfully'
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start session: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                   ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                   ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }

    public function recordSlideVisit(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
            'slide_id' => 'required|string',
            'slide_title' => 'nullable|string',
            'slide_sequence' => 'required|integer|min:1',
            'time_spent_seconds' => 'nullable|integer|min:0',
            'slide_metadata' => 'nullable|array',
        ]);

        try {
            $session = $this->sessionRepository->getSessionBySessionId($request->input('session_id'));
            
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                       ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                       ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            }

            $visit = $this->slideVisitRepository->recordSlideVisit([
                'quiz_session_id' => $session->id,
                'slide_id' => $request->input('slide_id'),
                'slide_title' => $request->input('slide_title'),
                'slide_sequence' => $request->input('slide_sequence'),
                'visited_at' => now(),
                'time_spent_seconds' => $request->input('time_spent_seconds'),
                'slide_metadata' => $request->input('slide_metadata'),
            ]);

            // Update session with latest slide info
            $this->sessionRepository->updateSession($session->id, [
                'total_slides_visited' => $session->total_slides_visited + 1,
                'last_slide_sequence' => $request->input('slide_sequence'),
            ]);

            return response()->json([
                'success' => true,
                'visit_id' => $visit->id,
                'message' => 'Slide visit recorded successfully'
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record slide visit: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                   ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                   ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }

    public function completeSession(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        try {
            $session = $this->sessionRepository->getSessionBySessionId($request->input('session_id'));
            
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                       ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                       ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            }

            $this->sessionRepository->markSessionCompleted($session->session_id);

            return response()->json([
                'success' => true,
                'message' => 'Session completed successfully'
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete session: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                   ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                   ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }

    private function extractQuizId(string $urlPath): string
    {
        // Extract quiz ID from URL path (e.g., "/lead2" -> "lead2")
        $path = trim($urlPath, '/');
        return $path ?: 'default';
    }
}
