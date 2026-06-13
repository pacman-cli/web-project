-- Migration: Add resume_path to enrollments table
-- Allows students to upload a resume/CV when requesting course enrollment

ALTER TABLE enrollments
ADD COLUMN IF NOT EXISTS resume_path VARCHAR(255) NULL AFTER rejection_reason;
