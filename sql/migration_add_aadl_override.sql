-- Migration: Add AADL record URL and manual unavailable override
-- Run this if you already have an existing database

ALTER TABLE `searches`
  ADD COLUMN `aadl_url` VARCHAR(500) DEFAULT NULL AFTER `cover_url`,
  ADD COLUMN `manual_unavailable` BOOLEAN DEFAULT FALSE AFTER `available`;

