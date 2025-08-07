<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\QuizTrackingController;
use App\Http\Controllers\Api\QuizAnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Quiz Tracking API Routes
Route::prefix('tracking')->group(function () {
    Route::post('/session/start', [QuizTrackingController::class, 'startSession']);
    Route::post('/slide/visit', [QuizTrackingController::class, 'recordSlideVisit']);
    Route::post('/session/complete', [QuizTrackingController::class, 'completeSession']);
});

// Quiz Analytics API Routes
Route::prefix('analytics')->group(function () {
    Route::get('/quizzes', [QuizAnalyticsController::class, 'getAllQuizzes']);
    Route::get('/quiz/{quizId}', [QuizAnalyticsController::class, 'getQuizAnalytics']);
    Route::get('/quiz/{quizId}/funnel', [QuizAnalyticsController::class, 'getRetentionFunnel']);
    Route::get('/quiz/{quizId}/dropoffs', [QuizAnalyticsController::class, 'getDropOffAnalysis']);
    Route::get('/quiz/{quizId}/slides', [QuizAnalyticsController::class, 'getSlideAnalytics']);
    Route::get('/quiz/{quizId}/sessions', [QuizAnalyticsController::class, 'getSessionStats']);
}); 