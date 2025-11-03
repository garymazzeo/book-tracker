# Security Configuration Guide

## Config File Storage Options (Best to Worst)

### Option 1: Outside Web Root (RECOMMENDED) ⭐

**Best practice:** Store `config.php` outside your web root directory.

**Directory Structure:**
```
/home/username/
├── config/
│   └── config.php          ← Outside web root (secure)
└── public_html/            ← Web root
    └── book-tracker/
        ├── books.php
        ├── dashboard.php
        └── includes/
```

**How to set up:**
1. Create a `config` directory one level above your web root
2. Upload `config.php` there via FTP
3. The application will automatically find it at `../config/config.php`

**Security:** ✅ Maximum - File is not accessible via web browser

---

### Option 2: In Project Root with .htaccess Protection

**If you can't access outside web root:**

**Directory Structure:**
```
public_html/book-tracker/
├── config.php              ← In project root (protected by .htaccess)
├── .htaccess              ← Protects config.php
├── books.php
└── includes/
```

**How to set up:**
1. Upload `config.php` to the same directory as your other files
2. Ensure `.htaccess` is uploaded (it blocks direct access)
3. The application will find it automatically

**Security:** ✅ Good - Protected by .htaccess, but still in web root

---

### Option 3: In includes/ Directory (Not Recommended)

**Only use if other options aren't possible:**

The application will fall back to looking in `includes/config.php`, but this is the least secure option.

**Security:** ⚠️ Moderate - Relies only on PHP not executing output

---

## FTP Upload Instructions

### Secure Method:
1. **Upload config.php** to your server via FTP using **SFTP** (not plain FTP) if possible
2. **Set file permissions** to 600 (owner read/write only):
   ```bash
   chmod 600 config.php
   ```
3. **Verify .htaccess is uploaded** - This adds an extra layer of protection

### File Permissions:
- `config.php`: `600` (read/write for owner only)
- `.htaccess`: `644` (readable by web server)
- Other PHP files: `644` (readable by web server)

---

## Additional Security Measures

### 1. Database User Permissions
Create a database user with **minimal privileges**:
```sql
CREATE USER 'book_tracker_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON book_tracker.* TO 'book_tracker_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Use Environment Variables (Advanced)
If your server supports it, you can use environment variables:
```php
define('DB_PASS', getenv('DB_PASSWORD') ?: 'fallback');
```

### 3. Regular Backups
- Backup your database regularly
- Keep encrypted backups of config.php in a secure location

### 4. HTTPS
- Always use HTTPS for your site
- Set `session.cookie_secure = 1` in config.php when using HTTPS

---

## Testing Security

After uploading, test that config.php is protected:

1. Try accessing: `https://yoursite.com/config.php`
   - Should return 403 Forbidden or similar error
   - Should NOT show your database credentials

2. Check server error logs if access is denied - this confirms .htaccess is working

---

## If Your Server Doesn't Support .htaccess

If you're on a server that doesn't support .htaccess (some shared hosting):

1. **Use Option 1** (outside web root) - This is still the most secure
2. **Add this to config.php** at the very top:
   ```php
   <?php
   if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
       die('Direct access not allowed');
   }
   ```

3. **Contact your host** to enable .htaccess or ask about protecting files

---

## Quick Checklist

- [ ] Config file uploaded outside web root OR with .htaccess protection
- [ ] File permissions set to 600 for config.php
- [ ] .htaccess file uploaded and working
- [ ] Tested that config.php cannot be accessed directly
- [ ] Database user has minimal required privileges
- [ ] Using HTTPS (if available)
- [ ] Config file is in .gitignore (already done)

