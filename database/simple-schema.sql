-- Quiz Funnel Retention Tracker Database Schema
-- Simplified version for local development

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

-- Insert slide data for the real Nebroo lead2 quiz (11 slides + 2 popups)
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
('lead2', 'popup-2', 'Hearing loss doesn\'t have to ruin your life', 'popup', 10),
('lead2', 'slide-9', 'Have you tried a prescription hearing aid?', 'question', 11),
('lead2', 'slide-10', 'What are you most worried about?', 'question', 12),
('lead2', 'slide-11', 'What is most important in a hearing aid?', 'question', 13); 