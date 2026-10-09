# Quizzy Setup Guide

Complete step-by-step setup instructions for the Quizzy Online Quiz System.

## Prerequisites

- XAMPP 7.4+ (or equivalent PHP 8.0+ and MySQL 8.0+ setup)
- Windows/Mac/Linux
- Modern web browser (Chrome, Firefox, Safari, Edge)

## Step 1: Start XAMPP Services

### Windows
1. Open XAMPP Control Panel
2. Click "Start" for Apache
3. Click "Start" for MySQL
4. Wait for both to show green indicators

### Mac/Linux
```bash
sudo /Applications/XAMPP/xamppfiles/bin/apachectl start
sudo /Applications/XAMPP/xamppfiles/bin/mysqld_safe
```

## Step 2: Create Database

### Option A: Using phpMyAdmin (GUI)
1. Open browser and go to `http://localhost/phpmyadmin`
2. Click "New" in left sidebar
3. Enter database name: `quizzy`
4. Set collation to `utf8mb4_unicode_ci`
5. Click "Create"
6. Select the `quizzy` database
7. Click "Import" tab
8. Choose file: `C:\xampp\htdocs\Quizzy\sql\quiz.sql`
9. Click "Import" button

### Option B: Using Command Line
```bash
# Open MySQL command line
mysql -u root -p

# If no password, just press Enter:
mysql -u root

# Then paste the schema from sql/quiz.sql
# Or run:
mysql -u root < "C:\xampp\htdocs\Quizzy\sql\quiz.sql"
```

## Step 3: Verify Installation

1. Open browser
2. Navigate to `http://localhost/Quizzy/`
3. You should see the Quizzy home page
4. Click "Login" to verify login page loads

## Step 4: Test Login

### Admin Login
1. Go to `http://localhost/Quizzy/login.php`
2. Email: `admin@quizzy.com`
3. Password: `admin123`
4. Click Login
5. You should be redirected to `/admin/users.php`

### Faculty Login
1. Go to `http://localhost/Quizzy/login.php`
2. Email: `faculty@quizzy.com`
3. Password: `faculty123`
4. Click Login
5. You should be redirected to `/faculty/questions.php`

### Student Login
1. Go to `http://localhost/Quizzy/login.php`
2. Email: `student@quizzy.com`
3. Password: `student123`
4. Click Login
5. You should be redirected to `/student/dashboard.php`

## Step 5: Initial Data Setup

### As Admin:
1. Go to "Manage Users" - verify sample users are present
2. Go to "Manage Subjects" - verify sample subject is present
3. Add more subjects as needed

### As Faculty:
1. Go to "Manage Questions"
2. Select the sample subject
3. Click "Add New Question"
4. Enter multiple choice questions with correct answer and difficulty
5. Create a Quiz from "Create Quiz" menu
6. Set start/end times for the quiz

### As Student:
1. Go to "My Quizzes"
2. View available quizzes
3. Click "Take Quiz" when quiz is active
4. Answer all questions and submit
5. View results immediately

## Troubleshooting

### Issue: Database Connection Failed

**Cause**: MySQL not running or wrong credentials

**Solution**:
1. Verify MySQL is running in XAMPP Control Panel
2. Check `config/db.php` has correct host, user, password
3. Verify database `quizzy` exists in phpMyAdmin
4. Restart MySQL service

### Issue: 404 Page Not Found

**Cause**: File path issue or mod_rewrite not enabled

**Solution**:
1. Verify all files are in `C:\xampp\htdocs\Quizzy\`
2. Access via `http://localhost/Quizzy/index.php` directly
3. Check Apache is running

### Issue: Session Not Working

**Cause**: Browser not accepting cookies or PHP session directory issue

**Solution**:
1. Clear browser cookies for localhost
2. Check browser privacy settings
3. Verify `session.save_path` in php.ini points to writable directory
4. Restart browser

### Issue: Styling Not Loading

**Cause**: CDN unavailable or CSS file path wrong

**Solution**:
1. Check internet connection (Bootstrap CDN requires it)
2. Open browser console (F12) and check for 404 errors
3. Verify `assets/css/style.css` exists locally

### Issue: Cannot Submit Quiz

**Cause**: Quiz already submitted or session expired

**Solution**:
1. Verify session cookie is set (browser dev tools)
2. Try logging in again
3. Make sure quiz hasn't already been submitted
4. Check `attempt_answers` table has records

## Database Verification

Open phpMyAdmin and verify these tables exist:
- [ ] users (3 sample records)
- [ ] subjects (1 sample record)
- [ ] questions (empty, wait for faculty to add)
- [ ] quizzes (empty, wait for faculty to create)
- [ ] attempts (empty, wait for student to take quiz)
- [ ] attempt_answers (empty, wait for student submission)

## File Permissions

Ensure PHP can write to:
- `php://tmp/` or system temp directory (for sessions)
- Database write operations (via MySQL)

Note: No files need write permissions except database.

## Performance Tips

1. **For Development**:
   - Keep localhost access fast
   - No network latency
   - Bootstrap CDN works fine

2. **For Production**:
   - Download Bootstrap files locally
   - Use database caching
   - Enable PHP opcache in php.ini
   - Set `PDO::ATTR_PERSISTENT => true` for connection pooling

## Email Configuration (Optional)

Currently, no email is configured. To add email notifications:
1. Install PHPMailer: `composer require phpmailer/phpmailer`
2. Add email config to `config/db.php`
3. Send emails from `notify_*` pages

## API Endpoints (Future)

Planned REST API endpoints (not yet implemented):
- GET /api/quizzes - List available quizzes
- POST /api/attempts - Start new attempt
- POST /api/attempts/{id}/answers - Submit answer
- POST /api/attempts/{id}/submit - Submit quiz
- GET /api/results/{id} - Get results

## Next Steps

1. **Add More Questions**: Faculty adds questions via UI
2. **Create Quizzes**: Faculty creates quizzes from questions
3. **Test As Student**: Student takes quiz and views results
4. **Monitor Results**: Faculty views results and statistics
5. **Manage Users**: Admin adds more users as needed

## Support

If you encounter issues:
1. Check this guide's Troubleshooting section
2. Verify all prerequisites are installed
3. Check XAMPP error logs in `xampp\apache\logs\error.log`
4. Check MySQL error logs in `xampp\mysql\data\mysql_error.log`
5. Check browser console (F12) for JavaScript errors

## Reset Database

To start over with fresh data:

### Via phpMyAdmin:
1. Select `quizzy` database
2. Click "Drop" or delete tables
3. Click "Import" and reimport `sql/quiz.sql`

### Via Command Line:
```bash
mysql -u root -e "DROP DATABASE quizzy;"
mysql -u root < "C:\xampp\htdocs\Quizzy\sql\quiz.sql"
```

## Security Checklist

- [ ] Change default admin password after first login
- [ ] Change default faculty password after first login
- [ ] Change default student password after first login
- [ ] Review user accounts regularly
- [ ] Backup database regularly
- [ ] Keep MySQL and PHP updated
- [ ] Review error logs for suspicious activity

---

**Last Updated**: 2026-10-09
**Version**: 1.0
**Project**: KZ-WEB-02
