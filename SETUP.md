# Book Tracker Setup Instructions

## Database Setup

1. Create a MySQL database:

   ```sql
   CREATE DATABASE book_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Import the schema:

   ```bash
   mysql -u your_db_user -p book_tracker < sql/schema.sql
   ```

3. If you have an existing database, run the migration to add admin support:

   ```bash
   mysql -u your_db_user -p book_tracker < sql/migration_add_admin.sql
   ```

4. Make your first user an admin (replace with your email):
   ```sql
   UPDATE users SET is_admin = TRUE WHERE email = 'your-email@example.com';
   ```

## Configuration

### Config File Security

**IMPORTANT:** The `config.php` file contains sensitive database credentials. See `SECURITY.md` for detailed security options.

**Recommended:** Store `config.php` outside your web root directory (most secure).

**Alternative:** Upload `config.php` to the project root - it will be protected by `.htaccess`.

### Setup Steps:

1. Copy `config.php.example` to `config.php`
2. Edit `config.php` and update:

   - `DB_HOST` - Your MySQL host (usually 'localhost')
   - `DB_NAME` - Database name (default: 'book_tracker')
   - `DB_USER` - Your MySQL username
   - `DB_PASS` - Your MySQL password
   - `FROM_EMAIL` - Email address for notifications
   - `FROM_NAME` - Name for email notifications
   - `SITE_URL` - Your website URL

3. Upload `config.php` via SFTP (more secure than FTP) to:

   - **Best:** One level above web root: `../config/config.php`
   - **Good:** Same directory as other files (protected by .htaccess)

4. Set file permissions: `chmod 600 config.php` (owner read/write only)

5. If using HTTPS, set `session.cookie_secure` to 1 in `config.php`

6. Verify `.htaccess` is uploaded (protects config files)

## Web Server Setup

1. Point your web server document root to this directory
2. Ensure PHP has:
   - PDO with MySQL support
   - `file_get_contents()` enabled (for API calls)
   - `mail()` function enabled (or configure SMTP)

## Cron Job Setup

Set up a daily cron job to check book availability:

```bash
crontab -e
```

Add this line (adjust the path to your installation):

```
0 9 * * * /usr/bin/php /path/to/book-tracker/cron/daily-check.php >> /path/to/book-tracker/cron/log.txt 2>&1
```

This will check books every day at 9 AM. You can change the time by modifying the first three numbers (minute, hour, day).

## Admin Setup

To create your first admin account:

1. Register a regular account through the website
2. Connect to your database and run:
   ```sql
   UPDATE users SET is_admin = TRUE WHERE email = 'your-email@example.com';
   ```
3. Log out and log back in - you'll now see an "Admin" link in the navigation
4. Visit the Admin page to manage users and delete accounts

**Note:** Only admin users can access the admin panel and delete other user accounts.

## First Use

1. Navigate to your website
2. Register a new account
3. Start searching for books by ISBN
4. Books that are unavailable will be checked daily automatically
5. Set up your admin account (see Admin Setup above)

## File Permissions

Ensure the `cron/` directory is writable for log files:

```bash
chmod 755 cron/
```

## Troubleshooting

- Check `cron/log.txt` for daily check logs
- Verify database connection in `config.php`
- Ensure PHP error logging is enabled to debug issues
- Test email functionality by manually running the cron script
