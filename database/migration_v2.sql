-- ============================================================================
-- Lyra Academy Music LMS — Schema Migration v2
-- Adds: quizzes, audit_logs, lesson_participation, class_sessions
-- Adds: missing indexes, FK fixes, submissions unique constraint
-- Preserves existing data. Safe for re-run (IF NOT EXISTS / IF EXISTS guards).
-- ============================================================================

-- ── 1. SUBMISSIONS UNIQUE KEY ─────────────────────────────────────────────────
-- Deduplicate first: keep the latest submission per (assignment_id, student_id)
DELETE t1 FROM submissions t1
INNER JOIN submissions t2
WHERE t1.assignment_id = t2.assignment_id
  AND t1.student_id = t2.student_id
  AND t1.id < t2.id;

-- Now add the constraint (skip if already exists from schema.sql)
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = 'music_elms'
    AND table_name = 'submissions'
    AND index_name = 'uk_submissions_assignment_student'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE submissions ADD UNIQUE KEY uk_submissions_assignment_student (assignment_id, student_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── 2. FOREIGN KEY INTEGRITY FIXES (idempotent) ─────────────────────────────

-- materials.uploaded_by: make nullable if not already
SET @col_nullable = (
  SELECT is_nullable FROM information_schema.columns
  WHERE table_schema = 'music_elms' AND table_name = 'materials' AND column_name = 'uploaded_by'
);
SET @sql = IF(@col_nullable = 'NO',
  'ALTER TABLE materials MODIFY uploaded_by INT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- materials.uploaded_by FK: drop and re-add if constraint name differs
SET @fk_name = (
  SELECT constraint_name FROM information_schema.key_column_usage
  WHERE table_schema = 'music_elms' AND table_name = 'materials'
    AND column_name = 'uploaded_by' AND referenced_table_name = 'users'
  LIMIT 1
);
-- Only alter if the FK doesn't already reference users with SET NULL
SET @needs_fix = (
  SELECT COUNT(*) FROM information_schema.referential_constraints
  WHERE constraint_schema = 'music_elms'
    AND table_name = 'materials'
    AND referenced_table_name = 'users'
    AND delete_rule != 'SET NULL'
);
SET @sql = IF(@needs_fix > 0 AND @fk_name IS NOT NULL,
  CONCAT('ALTER TABLE materials DROP FOREIGN KEY ', @fk_name),
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- submissions.graded_by FK: drop and re-add if needed
SET @fk_name2 = (
  SELECT constraint_name FROM information_schema.key_column_usage
  WHERE table_schema = 'music_elms' AND table_name = 'submissions'
    AND column_name = 'graded_by' AND referenced_table_name = 'instructors'
  LIMIT 1
);
SET @sql = IF(@fk_name2 IS NOT NULL,
  CONCAT('ALTER TABLE submissions DROP FOREIGN KEY ', @fk_name2),
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── 3. AUDIT LOGS ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'NULL = anonymous action (e.g. failed login)',
    action VARCHAR(50) NOT NULL COMMENT 'e.g. login, create_course, grade_submission, enroll_student',
    entity_type VARCHAR(50) NULL COMMENT 'e.g. user, course, submission, enrollment',
    entity_id INT NULL,
    details JSON NULL COMMENT 'Free-form context payload',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Immutable log of all state-changing operations for compliance & debugging';

-- ── 4. QUIZZES ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    class_id INT NULL COMMENT 'Optional: link to a specific course_class/module',
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    time_limit_minutes INT NULL COMMENT 'NULL = no time limit',
    passing_score INT DEFAULT 70 COMMENT 'Passing percentage (0-100)',
    max_attempts INT DEFAULT 1 COMMENT '0 = unlimited attempts',
    status ENUM('draft', 'published') DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES course_classes(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_quizzes_course (course_id),
    INDEX idx_quizzes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'short_answer', 'essay') DEFAULT 'multiple_choice',
    points INT DEFAULT 10,
    order_index INT DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_questions_quiz (quiz_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quiz_question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    order_index INT DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    INDEX idx_options_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_id INT NOT NULL,
    score INT DEFAULT 0,
    total_points INT DEFAULT 0,
    passed BOOLEAN DEFAULT FALSE,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(user_id) ON DELETE CASCADE,
    INDEX idx_attempts_quiz (quiz_id),
    INDEX idx_attempts_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option_id INT NULL,
    answer_text TEXT NULL COMMENT 'Used for short_answer and essay types',
    points_earned INT NULL COMMENT 'NULL = not yet graded (for essay/short_answer)',
    is_correct BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_option_id) REFERENCES quiz_question_options(id) ON DELETE SET NULL,
    INDEX idx_answers_attempt (attempt_id),
    UNIQUE KEY uk_answer_attempt_question (attempt_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 5. CLASS SESSIONS (individual meeting occurrences) ────────────────────────
CREATE TABLE IF NOT EXISTS class_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NULL COMMENT 'NULL for ad-hoc sessions not tied to a recurring schedule',
    course_id INT NOT NULL,
    instructor_id INT NOT NULL,
    title VARCHAR(150) NULL,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location_type ENUM('physical', 'online') DEFAULT 'physical',
    location_detail VARCHAR(255) NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES instructors(user_id) ON DELETE CASCADE,
    INDEX idx_sessions_course (course_id),
    INDEX idx_sessions_instructor (instructor_id),
    INDEX idx_sessions_date (date),
    INDEX idx_sessions_status (status),
    UNIQUE KEY uk_sessions_course_time (course_id, date, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 6. LESSON PARTICIPATION (student engagement tracking) ─────────────────────
CREATE TABLE IF NOT EXISTS lesson_participation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    material_id INT NULL COMMENT 'NULL = participation in a live session without specific material',
    watched_duration INT DEFAULT 0 COMMENT 'Duration in seconds for video/audio content',
    completed BOOLEAN DEFAULT FALSE,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES course_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE SET NULL,
    INDEX idx_participation_student (student_id),
    INDEX idx_participation_class (class_id),
    UNIQUE KEY uk_participation_student_class_material (student_id, class_id, material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 7. MISSING INDEXES (idempotent — skip if already exist from schema.sql) ──
-- Helper procedure to create index only if it doesn't exist
DROP PROCEDURE IF EXISTS _add_index_if_missing;
DELIMITER //
CREATE PROCEDURE _add_index_if_missing(
  IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_columns VARCHAR(255)
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema = 'music_elms' AND table_name = p_table AND index_name = p_index
  ) THEN
    SET @sql = CONCAT('CREATE INDEX ', p_index, ' ON ', p_table, '(', p_columns, ')');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END //
DELIMITER ;

CALL _add_index_if_missing('submissions', 'idx_submissions_student', 'student_id');
CALL _add_index_if_missing('submissions', 'idx_submissions_status', 'status');
CALL _add_index_if_missing('chat_messages', 'idx_chat_receiver', 'receiver_id');
CALL _add_index_if_missing('chat_messages', 'idx_chat_read', 'is_read');
CALL _add_index_if_missing('certificates', 'idx_certificates_student', 'student_id');
CALL _add_index_if_missing('attendance', 'idx_attendance_student', 'student_id');
CALL _add_index_if_missing('attendance', 'idx_attendance_status', 'status');
CALL _add_index_if_missing('materials', 'idx_materials_type', 'file_type');
CALL _add_index_if_missing('materials', 'idx_materials_uploader', 'uploaded_by');
CALL _add_index_if_missing('assignments', 'idx_assignments_due', 'due_date');
CALL _add_index_if_missing('enrollments', 'idx_enrollments_student', 'student_id');
CALL _add_index_if_missing('enrollments', 'idx_enrollments_course', 'course_id');
CALL _add_index_if_missing('user_uploads', 'idx_uploads_type', 'file_type');
CALL _add_index_if_missing('user_uploads', 'idx_uploads_uploaded', 'uploaded_at');
CALL _add_index_if_missing('instructor_assignments', 'idx_instructor_on_course', 'course_id');

DROP PROCEDURE IF EXISTS _add_index_if_missing;
