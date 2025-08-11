<?php
/**
 * Quiz Analytics API Endpoint
 * Provides analytics data for quiz funnel retention tracking
 */

// Enable CORS for cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Include database configuration
require_once '../config/database.php';

/**
 * Get cached analytics data
 */
function getCachedAnalytics($quizId, $cacheKey) {
    $db = getDB();
    
    $cached = $db->fetchOne(
        "SELECT cache_data FROM quiz_analytics_cache WHERE quiz_id = ? AND cache_key = ? AND expires_at > NOW()",
        [$quizId, $cacheKey]
    );
    
    return $cached ? json_decode($cached['cache_data'], true) : null;
}

/**
 * Cache analytics data
 */
function cacheAnalytics($quizId, $cacheKey, $data, $expiryMinutes = 15) {
    $db = getDB();
    
    $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));
    
    // Delete existing cache entry
    $db->delete('quiz_analytics_cache', 'quiz_id = ? AND cache_key = ?', [$quizId, $cacheKey]);
    
    // Insert new cache entry
    $db->insert('quiz_analytics_cache', [
        'quiz_id' => $quizId,
        'cache_key' => $cacheKey,
        'cache_data' => json_encode($data),
        'expires_at' => $expiresAt
    ]);
}

/**
 * Get basic quiz statistics
 */
function getQuizStats($quizId) {
    $db = getDB();
    
    // Get total users who actually started the quiz (visited first slide)
    $totalUsers = $db->fetchOne("
        SELECT COUNT(DISTINCT qs.session_id) as count
        FROM quiz_sessions qs
        JOIN quiz_events qe ON qs.session_id = qe.session_id 
        WHERE qs.quiz_id = ? 
        AND qe.event_type = 'slide_visit' 
        AND JSON_EXTRACT(qe.event_data, '$.slide_id') = 'slide-1'
    ", [$quizId]);
    
    $totalUsers = (int) $totalUsers['count'];
    
    // Get completed users
    $completedUsers = $db->fetchOne("
        SELECT COUNT(DISTINCT qs.session_id) as count
        FROM quiz_sessions qs
        JOIN quiz_events qe ON qs.session_id = qe.session_id 
        WHERE qs.quiz_id = ? 
        AND qe.event_type = 'quiz_completion'
    ", [$quizId]);
    
    $completedUsers = (int) $completedUsers['count'];
    
    $completionRate = $totalUsers > 0 ? round(($completedUsers / $totalUsers) * 100, 2) : 0;
    
    $stats = [
        'total_users' => $totalUsers,
        'completed_users' => $completedUsers,
        'completion_rate' => $completionRate
    ];
    
    // Get recent activity (last 24 hours)
    $recentActivity = $db->fetchOne("
        SELECT 
            COUNT(DISTINCT qs.session_id) as recent_users,
            COUNT(DISTINCT CASE WHEN qe.event_type = 'quiz_completion' THEN qs.session_id END) as recent_completions
        FROM quiz_sessions qs
        LEFT JOIN quiz_events qe ON qs.session_id = qe.session_id AND qe.event_type = 'quiz_completion'
        WHERE qs.quiz_id = ? AND qs.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ", [$quizId]);
    
    return array_merge($stats, $recentActivity);
}

/**
 * Get funnel data for quiz
 */
function getFunnelData($quizId) {
    $db = getDB();
    
    // Get all slides for the quiz
    $slides = $db->fetchAll("
        SELECT slide_id, slide_title, slide_type, sequence_order
        FROM quiz_slides 
        WHERE quiz_id = ? 
        ORDER BY sequence_order
    ", [$quizId]);
    
    $funnelData = [];
    $previousUsers = 0;
    
    foreach ($slides as $slide) {
        // Count users who reached this slide
        $usersReached = $db->fetchOne("
            SELECT COUNT(DISTINCT qs.session_id) as count
            FROM quiz_sessions qs
            JOIN quiz_events qe ON qs.session_id = qe.session_id 
            WHERE qs.quiz_id = ? 
            AND qe.event_type = 'slide_visit' 
            AND JSON_EXTRACT(qe.event_data, '$.slide_id') = ?
        ", [$quizId, $slide['slide_id']]);
        
        $usersReached = (int) $usersReached['count'];
        
        // Count users who exited on this slide
        $exitDropOff = $db->fetchOne("
            SELECT COUNT(DISTINCT qe2.session_id) as count
            FROM quiz_events qe2
            JOIN quiz_sessions qs2 ON qe2.session_id = qs2.session_id
            WHERE qs2.quiz_id = ? 
            AND qe2.event_type = 'page_exit' 
            AND JSON_UNQUOTE(JSON_EXTRACT(qe2.event_data, '$.last_slide')) = ?
        ", [$quizId, $slide['slide_id']]);
        
        $exitDropOff = (int) $exitDropOff['count'];
        
        // Use exit drop-off instead of progression drop-off
        $dropOff = $exitDropOff;
        $dropOffPercentage = $usersReached > 0 ? round(($dropOff / $usersReached) * 100, 2) : 0;
        
        $funnelData[] = [
            'slide_id' => $slide['slide_id'],
            'slide_title' => $slide['slide_title'],
            'slide_type' => $slide['slide_type'],
            'sequence' => (int) $slide['sequence_order'],
            'users_reached' => $usersReached,
            'drop_off' => $dropOff,
            'drop_off_percentage' => $dropOffPercentage,
            'retention_rate' => $previousUsers > 0 ? round(($usersReached / $previousUsers) * 100, 2) : 100
        ];
        
        $previousUsers = $usersReached;
    }
    
    return $funnelData;
}

/**
 * Get detailed drop-off analysis
 */
function getDropOffAnalysis($quizId) {
    $db = getDB();
    
    // Get all slides for the quiz
    $slides = $db->fetchAll("
        SELECT slide_id, slide_title, slide_type, sequence_order
        FROM quiz_slides 
        WHERE quiz_id = ? 
        ORDER BY sequence_order
    ", [$quizId]);
    
    $dropOffData = [];
    
    foreach ($slides as $slide) {
        // Count users who reached this slide (same logic as getFunnelData)
        $usersReached = $db->fetchOne("
            SELECT COUNT(DISTINCT qs.session_id) as count
            FROM quiz_sessions qs
            JOIN quiz_events qe ON qs.session_id = qe.session_id 
            WHERE qs.quiz_id = ? 
            AND qe.event_type = 'slide_visit' 
            AND JSON_EXTRACT(qe.event_data, '$.slide_id') = ?
        ", [$quizId, $slide['slide_id']]);
        
        $usersReached = (int) $usersReached['count'];
        
        // Count users who exited on this slide (same logic as getFunnelData)
        $exitDropOff = $db->fetchOne("
            SELECT COUNT(DISTINCT qe2.session_id) as count
            FROM quiz_events qe2
            JOIN quiz_sessions qs2 ON qe2.session_id = qs2.session_id
            WHERE qs2.quiz_id = ? 
            AND qe2.event_type = 'page_exit' 
            AND JSON_UNQUOTE(JSON_EXTRACT(qe2.event_data, '$.last_slide')) = ?
        ", [$quizId, $slide['slide_id']]);
        
        $exitDropOff = (int) $exitDropOff['count'];
        
        $dropOffPercentage = $usersReached > 0 ? round(($exitDropOff / $usersReached) * 100, 2) : 0;
        
        $dropOffData[] = [
            'slide_id' => $slide['slide_id'],
            'slide_title' => $slide['slide_title'],
            'slide_type' => $slide['slide_type'],
            'sequence_order' => (int) $slide['sequence_order'],
            'users_reached' => $usersReached,
            'drop_off_count' => $exitDropOff,
            'drop_off_percentage' => $dropOffPercentage
        ];
    }
    
    return $dropOffData;
}





/**
 * Get answer selection analytics
 */
function getAnswerAnalytics($quizId) {
    $db = getDB();
    
    // Get total users per slide first
    $slideUsers = $db->fetchAll("
        SELECT 
            JSON_EXTRACT(qe.event_data, '$.slide_id') as slide_id,
            COUNT(DISTINCT qe.session_id) as total_users
        FROM quiz_events qe
        JOIN quiz_sessions qs ON qe.session_id = qs.session_id
        WHERE qs.quiz_id = ? AND qe.event_type = 'slide_visit'
        GROUP BY JSON_EXTRACT(qe.event_data, '$.slide_id')
    ", [$quizId]);
    
    $slideUserCounts = [];
    foreach ($slideUsers as $slide) {
        $slideUserCounts[$slide['slide_id']] = $slide['total_users'];
    }
    
    // Get answer analytics
    $answers = $db->fetchAll("
        SELECT 
            JSON_EXTRACT(qe.event_data, '$.slide_id') as slide_id,
            JSON_EXTRACT(qe.event_data, '$.answer_value') as answer_value,
            JSON_EXTRACT(qe.event_data, '$.answer_text') as answer_text,
            COUNT(*) as selection_count
        FROM quiz_events qe
        JOIN quiz_sessions qs ON qe.session_id = qs.session_id
        WHERE qs.quiz_id = ? AND qe.event_type = 'answer_selection'
        GROUP BY JSON_EXTRACT(qe.event_data, '$.slide_id'), JSON_EXTRACT(qe.event_data, '$.answer_value')
    ", [$quizId]);
    
    // Calculate percentages
    foreach ($answers as &$answer) {
        $totalUsers = $slideUserCounts[$answer['slide_id']] ?? 1;
        $answer['selection_percentage'] = round(($answer['selection_count'] / $totalUsers) * 100, 2);
    }
    
    // Sort by slide_id and selection_count
    usort($answers, function($a, $b) {
        if ($a['slide_id'] !== $b['slide_id']) {
            return strcmp($a['slide_id'], $b['slide_id']);
        }
        return $b['selection_count'] - $a['selection_count'];
    });
    
    return $answers;
}

// Main execution
try {
    // Get quiz ID from query parameters
    $quizId = $_GET['quiz_id'] ?? null;
    
    if (!$quizId) {
        throw new Exception('Quiz ID is required');
    }
    
    // Sanitize quiz ID (using htmlspecialchars instead of deprecated FILTER_SANITIZE_STRING)
    $quizId = htmlspecialchars($quizId, ENT_QUOTES, 'UTF-8');
    
    // Get analytics type
    $type = $_GET['type'] ?? 'full';
    $cacheKey = "analytics_{$type}_" . date('Y-m-d-H');
    
    // Check cache first
    $cachedData = getCachedAnalytics($quizId, $cacheKey);
    if ($cachedData && $type !== 'realtime') {
        echo json_encode($cachedData);
        exit();
    }
    
    $db = getDB();
    
    // Prepare analytics data based on type
    $analyticsData = [
        'quiz_id' => $quizId,
        'generated_at' => date('Y-m-d H:i:s'),
        'cache_key' => $cacheKey
    ];
    
    switch ($type) {
        case 'stats':
            $analyticsData['stats'] = getQuizStats($quizId);
            break;
            
        case 'funnel':
            $analyticsData['funnel'] = getFunnelData($quizId);
            break;
            
        case 'dropoff':
            $analyticsData['drop_off_analysis'] = getDropOffAnalysis($quizId);
            break;
            

            

            
        case 'answers':
            $analyticsData['answer_analytics'] = getAnswerAnalytics($quizId);
            break;
            
        case 'realtime':
            // Real-time data (not cached)
            $analyticsData['stats'] = getQuizStats($quizId);
            $analyticsData['funnel'] = getFunnelData($quizId);
            break;
            
        case 'debug':
            // Debug data for page exit events
            $pageExitEvents = $db->fetchAll("
                SELECT 
                    JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.last_slide')) as last_slide,
                    COUNT(*) as count
                FROM quiz_events 
                WHERE event_type = 'page_exit' 
                AND session_id IN (SELECT session_id FROM quiz_sessions WHERE quiz_id = ?)
                GROUP BY JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.last_slide'))
                ORDER BY count DESC
            ", [$quizId]);
            
            $analyticsData['debug_page_exit_events'] = $pageExitEvents;
            break;
            
        default:
            // Full analytics (temporarily without answer analytics)
            $analyticsData['stats'] = getQuizStats($quizId);
            $analyticsData['funnel'] = getFunnelData($quizId);
            $analyticsData['drop_off_analysis'] = getDropOffAnalysis($quizId);
            // Temporarily disabled due to SQL GROUP BY issues
            // $analyticsData['answer_analytics'] = getAnswerAnalytics($quizId);
            break;
    }
    
    // Cache the data (except for realtime)
    if ($type !== 'realtime') {
        cacheAnalytics($quizId, $cacheKey, $analyticsData);
    }
    
    // Return analytics data
    echo json_encode($analyticsData);
    
} catch (Exception $e) {
    // Log error (in production, use proper logging)
    error_log("Quiz analytics error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
} 