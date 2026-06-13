-- Migration: Add file attachment support to assignments table
-- Run: mysql -u root music_elms < database/migration_assignment_attachments.sql

ALTER TABLE assignments 
ADD COLUMN file_path VARCHAR(255) NULL AFTER max_points,
ADD COLUMN file_name VARCHAR(255) NULL AFTER file_path;