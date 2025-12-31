-- Migration: Add is_admin field to users table
-- Run this if you already have an existing database

ALTER TABLE `users` ADD COLUMN `is_admin` BOOLEAN DEFAULT FALSE AFTER `password_hash`;

-- To make your first user an admin, run:
-- UPDATE users SET is_admin = TRUE WHERE email = 'your-admin-email@example.com';

