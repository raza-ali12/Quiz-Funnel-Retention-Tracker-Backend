# Quiz Tracker Backend

The Laravel API backend for the Quiz Funnel Retention Tracker. This handles all the tracking data, analytics, and provides the API endpoints for the frontend.

## What This Does

This Laravel app receives tracking events from quiz pages and provides analytics data. It's built with the repository pattern for clean, maintainable code.

## Quick Start

### Prerequisites
- PHP 8.1+
- Composer
- SQLite (included, no setup needed)

### Installation
```bash
cd quiz-tracker-backend
composer install
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

That's it! The API will be running at `http://127.0.0.1:8000`

## API Endpoints

### Tracking Endpoints

**Start Session**
```
POST /api/tracking/session/start
```
Creates a new quiz session when someone enters your quiz.

**Record Slide Visit**
```
POST /api/tracking/slide/visit
```
Logs when someone visits a slide. Includes slide metadata and timing.

**Complete Session**
```
POST /api/tracking/session/complete
```
Marks a session as completed when someone finishes the quiz.

### Analytics Endpoints

**Get Quiz Analytics**
```
GET /api/analytics/quiz/{quizId}
```
Returns comprehensive analytics for a specific quiz.

**List All Quizzes**
```
GET /api/analytics/quizzes
```
Shows all quizzes with basic stats.

**Get Retention Funnel**
```
GET /api/analytics/quiz/{quizId}/funnel
```
Returns data for creating retention funnel charts.

**Get Drop-off Analysis**
```
GET /api/analytics/quiz/{quizId}/dropoffs
```
Shows where users are dropping off.

## Database Structure

### Tables

**quizzes**
- Stores quiz metadata (ID, title, URL path)
- One record per quiz

**quiz_sessions**
- Individual user sessions
- Links to quiz, stores session ID, timing
- Tracks completion status

**quiz_slide_visits**
- Detailed slide visit data
- Links to session, stores slide metadata
- Includes timing and user behavior data

### Key Fields

Each slide visit captures:
- **slide_id** - Unique identifier for the slide
- **slide_title** - Human-readable slide name
- **slide_sequence** - Order/position in the quiz
- **time_spent_seconds** - How long they stayed
- **slide_metadata** - Additional data (JSON)

## Configuration

### Database
Uses SQLite by default for easy setup. The database file is created automatically.

### CORS
CORS is enabled for cross-origin requests from the frontend.

### API Routes
All API routes are in `routes/api.php` and automatically prefixed with `/api/`.

## Analytics Data

### What You Get

**Session Data**
- Total sessions started
- Completed sessions
- Completion rate percentage

**Slide Analytics**
- Users per slide (total and active)
- Drop-off between slides
- Time spent on each slide

**Real-time Features**
- Active users (last 5 minutes)
- Live session tracking
- Real-time analytics updates


## Repository Pattern

The app uses the repository pattern for clean data access:

### Interfaces
- `QuizRepositoryInterface`
- `QuizSessionRepositoryInterface`
- `QuizSlideVisitRepositoryInterface`

### Implementations
- `QuizRepository`
- `QuizSessionRepository`
- `QuizSlideVisitRepository`

This makes the code testable and maintainable.

## Error Handling

The API includes proper error handling:
- Validation errors return 422 status
- Not found errors return 404 status
- Server errors return 500 status
- All responses include helpful error messages

## Performance

- **Efficient queries** with proper indexing
- **Lazy loading** for relationships
- **Caching** ready (can be added easily)
- **Database optimization** for large datasets

## Security

- **Input validation** on all endpoints
- **SQL injection protection** via Eloquent ORM
- **CORS configuration** for frontend access
- **No sensitive data** stored

## Testing

### Manual Testing
```bash
# Test session start
curl -X POST http://127.0.0.1:8000/api/tracking/session/start \
  -H "Content-Type: application/json" \
  -d '{"url_path":"/test","user_agent":"test-agent"}'

# Test analytics
curl http://127.0.0.1:8000/api/analytics/quizzes
```

### Automated Testing
Add tests in the `tests/` directory:
- Feature tests for API endpoints
- Unit tests for repositories
- Integration tests for full workflows

## Deployment

### Production Setup
1. Change database to MySQL/PostgreSQL
2. Set up proper environment variables
3. Configure web server (Apache/Nginx)
4. Set up SSL certificates
5. Configure caching (Redis recommended)

### Environment Variables
```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=quiz_tracker
DB_USERNAME=your-username
DB_PASSWORD=your-password
```

## Monitoring

### Logs
Check Laravel logs in `storage/logs/` for:
- API request logs
- Error tracking
- Performance monitoring

### Database Monitoring
- Monitor table sizes
- Check query performance
- Set up database backups

## Troubleshooting

### Common Issues

**API not responding**
- Check if Laravel server is running
- Verify port 8000 is available
- Check logs for errors

**Database errors**
- Run `php artisan migrate` to create tables
- Check database file permissions
- Verify SQLite is working

**CORS issues**
- Frontend URL not allowed
- Check CORS configuration
- Verify request headers

### Debug Mode
Set `APP_DEBUG=true` in `.env` for detailed error messages.

