# Quizzy - Online Quiz System

A comprehensive online quiz system with auto-evaluation, built with PHP 8.x, MySQL 8, and Bootstrap 5.3.

## Project Details

- **Project**: KZ-WEB-02
- **Stack**: PHP 8.x with PDO, MySQL 8, Bootstrap 5.3, vanilla JavaScript
- **Base Path**: `/Quizzy`

## Installation

### Prerequisites
- XAMPP (or similar PHP/MySQL stack)
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Web browser (modern)

### Setup Instructions

1. **Create Database**
   - Open phpMyAdmin at `http://localhost/phpmyadmin`
   - Import the SQL schema from `sql/quiz.sql`
   - Or run: `mysql -u root < sql/quiz.sql`

2. **Configure Database Connection**
   - Database configuration is in `config/db.php`
   - Default settings: `localhost:3306`, database `quizzy`, user `root`, password empty
   - Update these values if your MySQL setup differs

3. **Start the Server**
   - Start XAMPP Apache and MySQL services
   - Navigate to `http://localhost/Quizzy`

## Project Structure

```
Quizzy/
├── config/
│   └── db.php              # Database configuration and PDO setup
├── includes/
│   ├── auth.php            # Authentication helpers
│   ├── header.php          # Common HTML header with navbar
│   └── footer.php          # Common HTML footer
├── assets/
│   ├── css/
│   │   └── style.css       # Global styling
│   └── js/
│       └── (future)
├── admin/
│   ├── users.php           # Manage users (create, delete)
│   └── subjects.php        # Manage subjects
├── faculty/
│   ├── questions.php       # Create and manage questions
│   ├── create_quiz.php     # Create new quiz
│   ├── results.php         # View quiz results and statistics
│   └── attempt_detail.php  # View individual attempt details
├── student/
│   ├── dashboard.php       # View available and completed quizzes
│   ├── attempt.php         # Take a quiz
│   ├── submit_attempt.php  # Process quiz submission and evaluate
│   └── result.php          # View quiz results
├── sql/
│   └── quiz.sql            # Complete database schema
├── index.php               # Home page
├── login.php               # User login
├── profile.php             # User profile and password change
├── logout.php              # Session logout
└── README.md               # This file
```

## Default Login Credentials

### Admin
- Email: `admin@quizzy.com`
- Password: `admin123`

### Faculty
- Email: `faculty@quizzy.com`
- Password: `faculty123`

### Student
- Email: `student@quizzy.com`
- Password: `student123`
- Roll No: `CS2024001`

## Database Schema

### Users
- Supports three roles: admin, faculty, student
- Passwords are hashed with bcrypt (PASSWORD_DEFAULT)
- Optional roll_no field for students

### Subjects
- Owned by faculty members
- Contain multiple questions
- Faculty can view and manage subjects they own

### Questions
- Multiple choice (A, B, C, D) format
- Difficulty levels: easy, medium, hard
- Belong to subjects

### Quizzes
- Created by faculty from questions in their subjects
- Fixed time window (start_time and end_time)
- Configurable marks and negative marks
- Auto-selects specified number of random questions

### Attempts
- One attempt per student per quiz
- Tracks question order (randomized for each student)
- Tracks option mapping (shuffled options per question)
- Auto-calculated score on submission

### Attempt Answers
- Records each student's answer to each question
- Tracks correctness for evaluation

## Features

### Admin
- Create and manage user accounts
- Assign roles (admin, faculty, student)
- Create and manage subjects
- Assign subjects to faculty members

### Faculty
- Create and manage questions for their subjects
- Create quizzes from available questions
- View results and statistics
- Review individual student attempts

### Student
- View available quizzes
- Take quizzes with auto timer
- View results immediately after submission
- Review answers and corrections

## Security Features

- PDO prepared statements for all DB queries (prevents SQL injection)
- Password hashing with bcrypt
- Session-based authentication
- Role-based access control
- HTML output escaping with htmlspecialchars()
- CSRF-safe design (POST for mutations, GET for reads)

## Styling

- **Primary Color**: #4f46e5 (Indigo)
- **Framework**: Bootstrap 5.3
- **Icons**: Bootstrap Icons 1.10.0
- **Responsive Design**: Mobile-friendly layouts
- **Print Styles**: Optimized for printing quiz results

## Key Implementation Details

### Quiz Taking Flow
1. Student selects quiz from dashboard
2. System creates attempt record with randomized question order
3. Student answers questions with auto-timer
4. On submission, system:
   - Records all answers
   - Evaluates each answer against correct option
   - Calculates score based on marks_correct and marks_negative
   - Stores score in attempt record
5. Student views detailed results with review

### Option Shuffling
- Options A, B, C, D are shuffled per question per student
- Option mapping stored as JSON in attempt record
- Allows secure randomization without storing raw correct answers

### Auto-Evaluation
- Automatic comparison of student answer vs correct answer
- Immediate score calculation
- Support for partial credit (negative marks for wrong answers)

## API-like Database Queries

All page operations use prepared PDO statements:
- User authentication
- Quiz listing with status filtering
- Question retrieval and ordering
- Score calculation and storage
- Result aggregation and statistics

## Troubleshooting

### Database Connection Error
- Verify MySQL is running
- Check database name, user, and password in `config/db.php`
- Ensure database is created and schema is imported

### Session Issues
- Clear browser cookies if login persists
- Check PHP session configuration

### Styling Issues
- Verify Bootstrap CDN is accessible
- Check `assets/css/style.css` is loaded correctly

## Future Enhancements

- Analytics dashboard with charts
- Question statistics (difficulty, pass rate)
- Leaderboards
- CSV export for results
- Question bank search and filtering
- Quiz templates
- Scheduled quiz notifications

## License

Internal project for Kaizen Infinities

## Support

For issues or questions, contact the development team.
