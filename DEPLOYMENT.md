# Deployment Guide

This guide covers automated deployment from GitHub to your VPS server.

## Option 1: GitHub Actions (Recommended) ⭐

Automatically deploys when you push to the `main` branch.

### Setup Steps

#### 1. Generate SSH Key Pair on Your VPS

On your VPS server, run:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/github_deploy_key -N ""
```

Or if ed25519 isn't available:

```bash
ssh-keygen -t rsa -b 4096 -f ~/.ssh/github_deploy_key -N ""
```

#### 2. Add Public Key to Authorized Keys

```bash
cat ~/.ssh/github_deploy_key.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

#### 3. Get the Private Key

Display the private key (you'll copy this to GitHub):

```bash
cat ~/.ssh/github_deploy_key
```

**Copy the entire output** including `-----BEGIN` and `-----END` lines.

#### 4. Configure GitHub Secrets

In your GitHub repository:

1. Go to **Settings** → **Secrets and variables** → **Actions**
2. Click **New repository secret** and add these secrets:

   - **`VPS_HOST`**: Your VPS IP or domain (e.g., `192.168.1.100` or `yourdomain.com`)
   - **`VPS_USER`**: SSH username (e.g., `root` or `deploy`)
   - **`VPS_DEPLOY_PATH`**: Full path to your web root (e.g., `/var/www/book-tracker` or `/home/username/public_html/book-tracker`)
   - **`VPS_SSH_KEY`**: Paste the entire private key from step 3

#### 5. Test the Deployment

1. Make a small change and push to `main` branch
2. Go to **Actions** tab in GitHub to see the deployment
3. Check your VPS to verify files are updated

### How It Works

- **Triggers**: Automatically runs on push to `main` branch
- **Manual Trigger**: You can also trigger manually from Actions tab
- **Safety**:
  - Excludes `config.php` (you upload this manually once)
  - Creates backups before deploying
  - Only deploys files, doesn't touch sensitive config

### Troubleshooting

**Deployment fails with "Permission denied"**

- Check SSH key permissions: `chmod 600 ~/.ssh/github_deploy_key`
- Verify public key is in `~/.ssh/authorized_keys`
- Check user has write permissions to deploy path

**Files not updating**

- Check the Actions log for errors
- Verify `VPS_DEPLOY_PATH` is correct
- Ensure web server has read permissions

---

## Option 2: Webhook-Based Deployment (Alternative)

If GitHub Actions doesn't work for your setup, you can use a webhook.

### Setup on VPS

Create a deployment script on your VPS:

```bash
# /home/username/deploy.sh
#!/bin/bash
cd /path/to/book-tracker
git pull origin main
```

Make it executable:

```bash
chmod +x /home/username/deploy.sh
```

### GitHub Webhook Setup

1. In GitHub repo: **Settings** → **Webhooks** → **Add webhook**
2. Payload URL: `https://yourdomain.com/deploy.php` (or use a secret endpoint)
3. Content type: `application/json`
4. Secret: Generate a random string
5. Events: Select "Just the push event"

### Create Webhook Handler

Create `deploy.php` on your server (outside web root ideally):

```php
<?php
$secret = 'your-webhook-secret-here';
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

$payload = file_get_contents('php://input');
$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    die('Invalid signature');
}

$data = json_decode($payload, true);
if ($data['ref'] === 'refs/heads/main') {
    exec('cd /path/to/book-tracker && git pull origin main 2>&1', $output);
    echo json_encode(['status' => 'success', 'output' => $output]);
}
```

**Security Note**: This requires your repo to be cloned on the server and accessible via git. Consider using a deploy key with limited permissions.

---

## Option 3: Simple rsync Script (Manual but Easy)

Create a local deployment script you run manually:

```bash
#!/bin/bash
# deploy.sh (run from your local machine)

VPS_HOST="your-vps-ip"
VPS_USER="your-username"
VPS_PATH="/path/to/book-tracker"

# Exclude sensitive files
rsync -avz --delete \
  --exclude='.git' \
  --exclude='config.php' \
  --exclude='.gitignore' \
  --exclude='*.md' \
  --exclude='cron/log.txt' \
  ./ $VPS_USER@$VPS_HOST:$VPS_PATH/

echo "Deployment complete!"
```

Usage:

```bash
chmod +x deploy.sh
./deploy.sh
```

---

## Important Notes

### Config File

- **Never commit `config.php`** - it's in `.gitignore`
- **First time setup**: Upload `config.php` manually via SFTP
- **After deployment**: Config file remains untouched on server

### File Permissions

After deployment, ensure proper permissions:

```bash
find /path/to/book-tracker -type f -name '*.php' -exec chmod 644 {} \;
find /path/to/book-tracker -type d -exec chmod 755 {} \;
chmod 600 /path/to/book-tracker/config.php
```

### Database Migrations

If you update the database schema:

1. Export new schema: `mysqldump -u user -p book_tracker > schema.sql`
2. Or manually run SQL updates on server
3. The deployment doesn't handle database changes automatically

### Cron Job

The cron job setup is independent of deployment. You still need to:

1. Set up cron on server manually (see SETUP.md)
2. Ensure `cron/daily-check.php` has proper permissions

---

## Security Best Practices

1. **Use SSH keys, not passwords** for GitHub Actions
2. **Rotate SSH keys periodically** (every 6-12 months)
3. **Use deploy-specific user** with limited permissions (not root)
4. **Restrict SSH access** to specific IPs if possible
5. **Keep backups** - the GitHub Action creates backups automatically
6. **Monitor deployments** - check GitHub Actions logs regularly

---

## Quick Comparison

| Method         | Automatic | Setup Complexity | Security   |
| -------------- | --------- | ---------------- | ---------- |
| GitHub Actions | ✅ Yes    | Medium           | ⭐⭐⭐⭐⭐ |
| Webhook        | ✅ Yes    | Medium-High      | ⭐⭐⭐     |
| rsync Script   | ❌ Manual | Low              | ⭐⭐⭐⭐   |

**Recommendation**: Use GitHub Actions for automated, secure deployments.
