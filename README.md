# PHP Admin Dashboard

This is a PHP-based admin dashboard that replicates the UI shown in the reference image. The dashboard includes various features like employee management, payroll, attendance tracking, and reporting.

## Setup Instructions

1. Make sure you have a web server with PHP support (like XAMPP, WAMP, or MAMP) installed on your computer.
2. Place all the files in your web server's document root directory (e.g., `htdocs` for XAMPP).
3. Start your web server and navigate to `http://localhost/PHP/` in your web browser.
4. You will be redirected to the login page.

## Login Credentials

- Username: `admin`
- Password: `admin123`

## Features

- **Dashboard Overview**: Shows key metrics like employee count, attendance, total salary, and alerts.
- **Employee Management**: View, add, edit, and delete employee records.
- **Payroll System**: Calculate and manage employee salaries.
- **Attendance Tracking**: Monitor employee attendance and leaves.
- **Reporting**: Generate and export various reports.
- **User Account Management**: Manage user accounts and permissions.
- **Settings**: Configure system settings.

## File Structure

- `index.php` - Main dashboard page
- `login.php` - Login page
- `logout.php` - Logout functionality
- `nhan-vien.php` - Employee management page
- `css/style.css` - Main stylesheet
- `js/script.js` - JavaScript functionality

## Notes

This is a frontend demonstration with minimal backend functionality. In a production environment, you would need to:

1. Implement proper database connectivity
2. Add robust authentication and authorization
3. Implement proper form validation and data processing
4. Add error handling and logging

## Security Considerations

For a production environment, consider:

1. Using password hashing (e.g., `password_hash()` and `password_verify()`)
2. Implementing CSRF protection
3. Using prepared statements for database queries
4. Setting up proper session management
5. Implementing input validation and sanitization
#   B T L _ W E B - P H P  
 