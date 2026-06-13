-- ============================================================================
-- Lyra Academy Music LMS — Schema Rollback v2
-- Reverses all changes made by migration_v2.sql
-- Destructive: drops new tables, indexes, and constraint changes.
-- Run ONLY if you need to revert to the original schema.
-- ============================================================================

-- ── Drop new tables (order respects FK dependencies) ──────────────────────────
DROP TABLE IF EXISTS lesson_participation;
DROP TABLE IF EXISTS quiz_answers;
DROP TABLE IF EXISTS quiz_attempts;
DROP TABLE IF EXISTS quiz_question_options;
DROP TABLE IF EXISTS quiz_questions;
DROP TABLE IF EXISTS quizzes;
DROP TABLE IF EXISTS class_sessions;
DROP TABLE IF EXISTS audit_logs;

-- ── Drop indexes added by migration ───────────────────────────────────────────
DROP INDEX IF EXISTS idx_submissions_student ON submissions;
DROP INDEX IF EXISTS idx_submissions_status ON submissions;
DROP INDEX IF EXISTS idx_chat_receiver ON chat_messages;
DROP INDEX IF EXISTS idx_chat_read ON chat_messages;
DROP INDEX IF EXISTS idx_certificates_student ON certificates;
DROP INDEX IF EXISTS idx_attendance_student ON attendance;
DROP INDEX IF EXISTS idx_attendance_status ON attendance;
DROP INDEX IF EXISTS idx_materials_type ON materials;
DROP INDEX IF EXISTS idx_materials_uploader ON materials;
DROP INDEX IF EXISTS idx_assignments_due ON assignments;
DROP INDEX IF EXISTS idx_enrollments_student ON enrollments;
DROP INDEX IF EXISTS idx_enrollments_course ON enrollments;
DROP INDEX IF EXISTS idx_uploads_type ON user_uploads;
DROP INDEX IF EXISTS idx_uploads_uploaded ON user_uploads;
DROP INDEX IF EXISTS idx_instructor_on_course ON instructor_assignments;

-- ── Remove submissions unique key ─────────────────────────────────────────────
ALTER TABLE submissions DROP INDEX uk_submissions_assignment_student;

-- ── Restore original FK: materials.uploaded_by CASCADE → CASCADE ─────────────
ALTER TABLE materials
  DROP FOREIGN KEY materials_ibfk_2,
  ADD CONSTRAINT materials_ibfk_2 FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE;

-- ── Restore original FK: submissions.graded_by → instructors(user_id) ────────
-- First, ensure no orphan graded_by values exist that reference a user who is
-- not an instructor
UPDATE submissions s
LEFT JOIN users u ON s.graded_by = u.id AND u.role = 'instructor'
SET s.graded_by = NULL
WHERE s.graded_by IS NOT NULL AND u.id IS NULL;

ALTER TABLE submissions
  DROP FOREIGN KEY submissions_ibfk_3,
  ADD CONSTRAINT submissions_ibfk_3 FOREIGN KEY (graded_by) REFERENCES instructors(user_id) ON DELETE SET NULL;
