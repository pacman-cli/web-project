-- database/migration_student_stats.sql
-- Add GPA, Practice Hours, and Scholarship Status to the students profile table

ALTER TABLE students 
ADD COLUMN gpa DECIMAL(3,2) DEFAULT 3.92,
ADD COLUMN practice_hours INT DEFAULT 142,
ADD COLUMN scholarship_status VARCHAR(50) DEFAULT 'Active Merit';
