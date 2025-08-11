-- Quiz Funnel Retention Tracker Database Schema
-- MySQL 5.7+ compatible

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS quiz_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE quiz_tracker;

-- Quiz sessions table
CREATE TABLE IF NOT EXISTS quiz_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id VARCHAR(100) NOT NULL,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    user_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_quiz_id (quiz_id),
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Quiz events table
CREATE TABLE IF NOT EXISTS quiz_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    event_type ENUM('page_entry', 'slide_visit', 'answer_selection', 'quiz_completion', 'page_exit') NOT NULL,
    event_data JSON NOT NULL,
    timestamp BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_event_type (event_type),
    INDEX idx_timestamp (timestamp),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (session_id) REFERENCES quiz_sessions(session_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Quiz slides table (for slide metadata)
CREATE TABLE IF NOT EXISTS quiz_slides (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id VARCHAR(100) NOT NULL,
    slide_id VARCHAR(100) NOT NULL,
    slide_title VARCHAR(255) NOT NULL,
    slide_type ENUM('question', 'popup', 'loading', 'motivational', 'summary', 'info') NOT NULL,
    sequence_order INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_quiz_slide (quiz_id, slide_id),
    INDEX idx_quiz_id (quiz_id),
    INDEX idx_slide_id (slide_id),
    INDEX idx_sequence_order (sequence_order)
) ENGINE=InnoDB;

-- Quiz analytics cache table (for performance)
CREATE TABLE IF NOT EXISTS quiz_analytics_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id VARCHAR(100) NOT NULL,
    cache_key VARCHAR(255) NOT NULL,
    cache_data JSON NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cache_key (quiz_id, cache_key),
    INDEX idx_quiz_id (quiz_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;

-- Insert sample slide data for the Nebroo quiz
INSERT IGNORE INTO quiz_slides (quiz_id, slide_id, slide_title, slide_type, sequence_order) VALUES
('lead2', 'slide-1', 'This year, I\'m...', 'question', 1),
('lead2', 'slide-2', 'What do you want the most?', 'question', 2),
('lead2', 'slide-3', 'When did you notice you had a hearing problem?', 'question', 3),
('lead2', 'slide-4', 'How has your life changed because of hearing loss?', 'question', 4),
('lead2', 'slide-5', 'What happens when people speak?', 'question', 5),
('lead2', 'slide-6', 'How would you describe your hearing loss level?', 'question', 6),
('lead2', 'popup-1', '100,000 people have chosen Nebroo to hear clearly again', 'popup', 7),
('lead2', 'slide-7', 'What\'s the #1 reason why you haven\'t fixed your hearing?', 'question', 8),
('lead2', 'slide-8', 'How do your family feel about your problem?', 'question', 9),
('lead2', 'slide-9', 'Have you tried a prescription hearing aid?', 'question', 10),
('lead2', 'popup-2', 'Hearing loss doesn\'t have to ruin your life', 'popup', 11),
('lead2', 'slide-10', 'What are you most worried about?', 'question', 12),
('lead2', 'slide-11', 'What is most important in a hearing aid?', 'question', 13),
('lead2', 'loading-1', 'Analyzing your responses...', 'loading', 14),
('lead2', 'motivational-1', 'You\'re making a great decision!', 'motivational', 15),
('lead2', 'loading-2', 'Checking if the Nebroo PRO 2.0 are still available...', 'loading', 16),
('lead2', 'motivational-2', 'Availability Confirmed!', 'motivational', 17),
('lead2', 'loading-3', 'Calculating your discount...', 'loading', 18);

-- Create views for easier analytics queries
CREATE OR REPLACE VIEW quiz_funnel_data AS
SELECT 
    qs.quiz_id,
    qsl.slide_id,
    qsl.slide_title,
    qsl.slide_type,
    qsl.sequence_order,
    COUNT(DISTINCT qs.session_id) as users_reached,
    COUNT(DISTINCT CASE WHEN qe.event_type = 'slide_visit' THEN qs.session_id END) as slide_visits,
    COUNT(DISTINCT CASE WHEN qe.event_type = 'answer_selection' THEN qs.session_id END) as answer_selections
FROM quiz_sessions qs
LEFT JOIN quiz_slides qsl ON qs.quiz_id = qsl.quiz_id
LEFT JOIN quiz_events qe ON qs.session_id = qe.session_id 
    AND qe.event_type = 'slide_visit' 
    AND JSON_EXTRACT(qe.event_data, '$.slide_id') = qsl.slide_id
GROUP BY qs.quiz_id, qsl.slide_id, qsl.slide_title, qsl.slide_type, qsl.sequence_order
ORDER BY qs.quiz_id, qsl.sequence_order;

-- Create view for completion rates
CREATE OR REPLACE VIEW quiz_completion_rates AS
SELECT 
    qs.quiz_id,
    COUNT(DISTINCT qs.session_id) as total_sessions,
    COUNT(DISTINCT CASE WHEN qe.event_type = 'quiz_completion' THEN qs.session_id END) as completed_sessions,
    ROUND(
        (COUNT(DISTINCT CASE WHEN qe.event_type = 'quiz_completion' THEN qs.session_id END) / COUNT(DISTINCT qs.session_id)) * 100, 
        2
    ) as completion_rate
FROM quiz_sessions qs
LEFT JOIN quiz_events qe ON qs.session_id = qe.session_id AND qe.event_type = 'quiz_completion'
GROUP BY qs.quiz_id;

-- Create view for drop-off analysis
CREATE OR REPLACE VIEW quiz_drop_off_analysis AS
SELECT 
    qs.quiz_id,
    qsl.slide_id,
    qsl.slide_title,
    qsl.sequence_order,
    COUNT(DISTINCT qs.session_id) as users_reached,
    LAG(COUNT(DISTINCT qs.session_id)) OVER (PARTITION BY qs.quiz_id ORDER BY qsl.sequence_order) as previous_slide_users,
    COUNT(DISTINCT qs.session_id) - LAG(COUNT(DISTINCT qs.session_id)) OVER (PARTITION BY qs.quiz_id ORDER BY qsl.sequence_order) as drop_off_count,
    CASE 
        WHEN LAG(COUNT(DISTINCT qs.session_id)) OVER (PARTITION BY qs.quiz_id ORDER BY qsl.sequence_order) > 0 
        THEN ROUND(
            ((LAG(COUNT(DISTINCT qs.session_id)) OVER (PARTITION BY qs.quiz_id ORDER BY qsl.sequence_order) - COUNT(DISTINCT qs.session_id)) / 
             LAG(COUNT(DISTINCT qs.session_id)) OVER (PARTITION BY qs.quiz_id ORDER BY qsl.sequence_order)) * 100, 
            2
        )
        ELSE 0 
    END as drop_off_percentage
FROM quiz_sessions qs
JOIN quiz_slides qsl ON qs.quiz_id = qsl.quiz_id
JOIN quiz_events qe ON qs.session_id = qe.session_id 
    AND qe.event_type = 'slide_visit' 
    AND JSON_EXTRACT(qe.event_data, '$.slide_id') = qsl.slide_id
GROUP BY qs.quiz_id, qsl.slide_id, qsl.slide_title, qsl.sequence_order
ORDER BY qs.quiz_id, qsl.sequence_order;

-- Create indexes for better performance
CREATE INDEX idx_events_session_type ON quiz_events(session_id, event_type);
CREATE INDEX idx_events_timestamp_type ON quiz_events(timestamp, event_type);
CREATE INDEX idx_sessions_quiz_created ON quiz_sessions(quiz_id, created_at);

-- Create stored procedure for cleaning old data
DELIMITER //
CREATE PROCEDURE CleanOldData(IN days_to_keep INT)
BEGIN
    DECLARE cutoff_date TIMESTAMP;
    SET cutoff_date = DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    -- Delete old events
    DELETE FROM quiz_events 
    WHERE created_at < cutoff_date;
    
    -- Delete old sessions
    DELETE FROM quiz_sessions 
    WHERE created_at < cutoff_date;
    
    -- Delete expired cache
    DELETE FROM quiz_analytics_cache 
    WHERE expires_at < NOW();
    
    SELECT ROW_COUNT() as deleted_rows;
END //
DELIMITER ;

-- Create stored procedure for getting quiz analytics
DELIMITER //
CREATE PROCEDURE GetQuizAnalytics(IN p_quiz_id VARCHAR(100))
BEGIN
    -- Get basic stats
    SELECT 
        qs.quiz_id,
        COUNT(DISTINCT qs.session_id) as total_users,
        COUNT(DISTINCT CASE WHEN qe.event_type = 'quiz_completion' THEN qs.session_id END) as completed_users,
        ROUND(
            (COUNT(DISTINCT CASE WHEN qe.event_type = 'quiz_completion' THEN qs.session_id END) / COUNT(DISTINCT qs.session_id)) * 100, 
            2
        ) as completion_rate,
        AVG(CASE WHEN qe.event_type = 'quiz_completion' THEN JSON_EXTRACT(qe.event_data, '$.total_time') END) as avg_completion_time
    FROM quiz_sessions qs
    LEFT JOIN quiz_events qe ON qs.session_id = qe.session_id AND qe.event_type = 'quiz_completion'
    WHERE qs.quiz_id = p_quiz_id
    GROUP BY qs.quiz_id;
    
    -- Get funnel data
    SELECT 
        qsl.slide_id,
        qsl.slide_title,
        qsl.slide_type,
        qsl.sequence_order,
        COUNT(DISTINCT qs.session_id) as users_reached,
        LAG(COUNT(DISTINCT qs.session_id)) OVER (ORDER BY qsl.sequence_order) as previous_slide_users,
        CASE 
            WHEN LAG(COUNT(DISTINCT qs.session_id)) OVER (ORDER BY qsl.sequence_order) > 0 
            THEN LAG(COUNT(DISTINCT qs.session_id)) OVER (ORDER BY qsl.sequence_order) - COUNT(DISTINCT qs.session_id)
            ELSE 0 
        END as drop_off_count
    FROM quiz_sessions qs
    JOIN quiz_slides qsl ON qs.quiz_id = qsl.quiz_id
    LEFT JOIN quiz_events qe ON qs.session_id = qe.session_id 
        AND qe.event_type = 'slide_visit' 
        AND JSON_EXTRACT(qe.event_data, '$.slide_id') = qsl.slide_id
    WHERE qs.quiz_id = p_quiz_id
    GROUP BY qsl.slide_id, qsl.slide_title, qsl.slide_type, qsl.sequence_order
    ORDER BY qsl.sequence_order;
END //
DELIMITER ; 