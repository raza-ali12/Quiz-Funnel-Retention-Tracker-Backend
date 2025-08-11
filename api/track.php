<?php
/**
 * Quiz Tracking API Endpoint
 * Receives and stores quiz events from client-side tracking script
 */

// Enable CORS for cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Include database configuration
require_once '../config/database.php';

// Rate limiting configuration
$rateLimitConfig = [
    'max_requests' => 100, // Max requests per minute
    'window' => 60 // Time window in seconds
];

/**
 * Rate limiting function
 */
function checkRateLimit($ip, $config) {
    $db = getDB();
    
    // Clean old rate limit records
    $db->query("DELETE FROM quiz_analytics_cache WHERE cache_key LIKE 'rate_limit_%' AND expires_at < NOW()");
    
    $cacheKey = 'rate_limit_' . md5($ip);
    $currentTime = time();
    
    // Check existing rate limit record
    $existing = $db->fetchOne(
        "SELECT cache_data, expires_at FROM quiz_analytics_cache WHERE cache_key = ?",
        [$cacheKey]
    );
    
    if ($existing) {
        $data = json_decode($existing['cache_data'], true);
        $data['count']++;
        
        if ($data['count'] > $config['max_requests']) {
            return false; // Rate limit exceeded
        }
        
        // Update count
        $db->update('quiz_analytics_cache', 
            ['cache_data' => json_encode($data)], 
            'cache_key = ?', 
            [$cacheKey]
        );
    } else {
        // Create new rate limit record
        $data = ['count' => 1, 'created_at' => $currentTime];
        $db->insert('quiz_analytics_cache', [
            'quiz_id' => 'system',
            'cache_key' => $cacheKey,
            'cache_data' => json_encode($data),
            'expires_at' => date('Y-m-d H:i:s', $currentTime + $config['window'])
        ]);
    }
    
    return true;
}

/**
 * Validate and sanitize input data
 */
function validateInput($data) {
    $required = ['quiz_id', 'session_id', 'user_id', 'event'];
    
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }
    
    // Validate event structure
    if (!isset($data['event']['type']) || !isset($data['event']['timestamp'])) {
        return false;
    }
    
    // Validate event types
    $validEventTypes = ['page_entry', 'slide_visit', 'answer_selection', 'quiz_completion', 'page_exit'];
    if (!in_array($data['event']['type'], $validEventTypes)) {
        return false;
    }
    
    // Sanitize strings (using htmlspecialchars instead of deprecated FILTER_SANITIZE_STRING)
    $data['quiz_id'] = htmlspecialchars($data['quiz_id'], ENT_QUOTES, 'UTF-8');
    $data['session_id'] = htmlspecialchars($data['session_id'], ENT_QUOTES, 'UTF-8');
    $data['user_id'] = htmlspecialchars($data['user_id'], ENT_QUOTES, 'UTF-8');
    
    return $data;
}

/**
 * Store quiz session
 */
function storeSession($quizId, $sessionId, $userId) {
    $db = getDB();
    
    // Check if session already exists
    $existing = $db->fetchOne(
        "SELECT id FROM quiz_sessions WHERE session_id = ?",
        [$sessionId]
    );
    
    if (!$existing) {
        // Create new session
        $db->insert('quiz_sessions', [
            'quiz_id' => $quizId,
            'session_id' => $sessionId,
            'user_id' => $userId
        ]);
    }
}

/**
 * Store quiz event
 */
function storeEvent($sessionId, $event) {
    $db = getDB();
    
    // Check if session exists
    $session = $db->fetchOne(
        "SELECT id FROM quiz_sessions WHERE session_id = ?",
        [$sessionId]
    );
    
    if (!$session) {
        throw new Exception('Session not found');
    }
    
    // Store event
    $db->insert('quiz_events', [
        'session_id' => $sessionId,
        'event_type' => $event['type'],
        'event_data' => json_encode($event['data'] ?? []),
        'timestamp' => $event['timestamp']
    ]);
}

/**
 * Update slide metadata if needed
 */
function updateSlideMetadata($quizId, $event) {
    if ($event['type'] !== 'slide_visit' || !isset($event['data']['slide_id'])) {
        return;
    }
    
    $db = getDB();
    $slideId = $event['data']['slide_id'];
    $slideTitle = $event['data']['slide_title'] ?? '';
    $slideType = $event['data']['slide_type'] ?? 'question';
    $sequence = $event['data']['sequence'] ?? 0;
    
    // Check if slide exists
    $existing = $db->fetchOne(
        "SELECT id FROM quiz_slides WHERE quiz_id = ? AND slide_id = ?",
        [$quizId, $slideId]
    );
    
    if (!$existing) {
        // Insert new slide metadata
        $db->insert('quiz_slides', [
            'quiz_id' => $quizId,
            'slide_id' => $slideId,
            'slide_title' => $slideTitle,
            'slide_type' => $slideType,
            'sequence_order' => $sequence
        ]);
    }
}

// Main execution
try {
    // Get client IP for rate limiting
    $clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Check rate limit
    if (!checkRateLimit($clientIP, $rateLimitConfig)) {
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded']);
        exit();
    }
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate input
    $validatedData = validateInput($data);
    if (!$validatedData) {
        throw new Exception('Invalid input data');
    }
    
    $db = getDB();
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Store session
        storeSession($validatedData['quiz_id'], $validatedData['session_id'], $validatedData['user_id']);
        
        // Update slide metadata if it's a slide visit
        updateSlideMetadata($validatedData['quiz_id'], $validatedData['event']);
        
        // Store event
        storeEvent($validatedData['session_id'], $validatedData['event']);
        
        // Commit transaction
        $db->commit();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Event tracked successfully',
            'event_id' => $db->getConnection()->lastInsertId(),
            'timestamp' => time()
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log error (in production, use proper logging)
    error_log("Quiz tracking error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
} 