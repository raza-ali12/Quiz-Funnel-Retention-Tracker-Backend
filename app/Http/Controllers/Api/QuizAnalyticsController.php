<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\QuizRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuizAnalyticsController extends Controller
{
    protected $quizRepository;

    public function __construct(QuizRepositoryInterface $quizRepository)
    {
        $this->quizRepository = $quizRepository;
    }

    public function getAllQuizzes(): JsonResponse
    {
        try {
            $quizzes = $this->quizRepository->getAllQuizzes();
            
            return response()->json([
                'success' => true,
                'data' => $quizzes
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch quizzes: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                   ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                   ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }

    public function getQuizAnalytics(string $quizId): JsonResponse
    {
        try {
            $analytics = $this->quizRepository->getQuizAnalytics($quizId);
            
            if (!$analytics) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quiz not found'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                       ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                       ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            }
            
            return response()->json([
                'success' => true,
                'data' => $analytics
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                   ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                   ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }

    public function getRetentionFunnel(string $quizId): JsonResponse
    {
        try {
            $funnel = $this->quizRepository->getRetentionFunnel($quizId);
            
            return response()->json([
                'success' => true,
                'data' => ['funnel' => $funnel]
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch retention funnel: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                   ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                   ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }

    public function getDropOffAnalysis(string $quizId): JsonResponse
    {
        try {
            $dropOffs = $this->quizRepository->getDropOffAnalysis($quizId);
            
            return response()->json([
                'success' => true,
                'data' => ['drop_offs' => $dropOffs]
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch drop-off analysis: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                   ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                   ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }

    public function getSlideAnalytics(string $quizId): JsonResponse
    {
        try {
            $slideAnalytics = $this->quizRepository->getSlideAnalytics($quizId);
            
            return response()->json([
                'success' => true,
                'data' => ['slides' => $slideAnalytics]
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch slide analytics: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                   ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                   ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }

    public function getSessionStats(string $quizId): JsonResponse
    {
        try {
            $sessionStats = $this->quizRepository->getSessionStats($quizId);
            
            return response()->json([
                'success' => true,
                'data' => ['sessions' => $sessionStats]
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch session stats: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                   ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                   ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }
}
