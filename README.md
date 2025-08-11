# Quiz Funnel Retention Tracker - Backend

PHP/MySQL backend for the Quiz Funnel Retention Tracker system.

## Quick Start

1. **Database Setup**
```sql
CREATE DATABASE quiz_tracker;
USE quiz_tracker;
SOURCE database/simple-schema.sql;
```

2. **Configuration**
Edit `config/database.php` with your MySQL credentials.

3. **Start Server**
```bash
php -S localhost:8000
```

## API Endpoints

- **POST** `/api/track.php` - Event tracking
- **GET** `/api/analytics.php?quiz_id=lead2&type=full` - Analytics data

## Files

- `api/` - API endpoints
- `config/` - Database configuration  
- `database/` - Database schema
- `quiz-tracker.js` - Client tracking script 