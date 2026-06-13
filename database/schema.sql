-- Music School LMS (Lyra Academy) - Relational MySQL Database Schema
-- Source of truth. Version v2 (quizzes, audit_logs, participation, class_sessions)

-- 1. Users Table (Core authentication and role identification)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'instructor', 'student') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Instructors Profile Table (1:1 Relation with users)
CREATE TABLE IF NOT EXISTS instructors (
    user_id INT PRIMARY KEY,
    bio TEXT,
    specialization VARCHAR(150),
    hourly_rate DECIMAL(8, 2) DEFAULT 0.00,
    hire_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Students Profile Table (1:1 Relation with users)
CREATE TABLE IF NOT EXISTS students (
    user_id INT PRIMARY KEY,
    date_of_birth DATE,
    parent_name VARCHAR(100) NULL,
    parent_contact VARCHAR(20) NULL,
    experience_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    enrollment_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Instrument Categories
CREATE TABLE IF NOT EXISTS instruments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Courses Table
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    instrument_id INT,
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    price DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('draft', 'published') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (instrument_id) REFERENCES instruments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Course Syllabus/Modules Table (1:N Course relationship)
CREATE TABLE IF NOT EXISTS course_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    order_index INT DEFAULT 0,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Instructor Course Assignments (N:M relation)
CREATE TABLE IF NOT EXISTS instructor_assignments (
    instructor_id INT NOT NULL,
    course_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (instructor_id, course_id),
    FOREIGN KEY (instructor_id) REFERENCES instructors(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Course Enrollments (Pending / Approved / Rejected)
CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY (student_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Weekly Schedules (Zoom and physical room location support)
CREATE TABLE IF NOT EXISTS schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    instructor_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location_type ENUM('physical', 'online') DEFAULT 'physical',
    location_detail VARCHAR(255) NOT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES instructors(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Student Attendance Tracking
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('present', 'absent', 'excused') NOT NULL,
    date DATE NOT NULL,
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(user_id) ON DELETE CASCADE,
    UNIQUE KEY (schedule_id, student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Course Materials (File attachments)
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type ENUM('pdf', 'audio', 'video', 'document') NOT NULL,
    uploaded_by INT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Course Assignments
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    due_date DATETIME NOT NULL,
    max_points INT DEFAULT 100,
    file_path VARCHAR(255) NULL,
    file_name VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Student Submissions (Supports dynamic media uploads)
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    submission_text TEXT NULL,
    points_earned INT NULL,
    feedback TEXT NULL,
    status ENUM('submitted', 'graded') DEFAULT 'submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    graded_by INT NULL,
    graded_at TIMESTAMP NULL,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uk_submissions_assignment_student (assignment_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Course Ratings and Feedback
CREATE TABLE IF NOT EXISTS ratings_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY (student_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Student-Instructor Chat Messages (With Course Threads)
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_text TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Issued Certificates (PDF Generation Tracking)
CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    certificate_hash VARCHAR(64) UNIQUE NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY (student_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. User Uploads Table (Unified File Upload Registry)
CREATE TABLE IF NOT EXISTS user_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type ENUM('audio', 'video', 'document') NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. Audit Logs (Immutable state-change record)
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'NULL for anonymous actions',
    action VARCHAR(50) NOT NULL COMMENT 'e.g. login, create_course, grade_submission',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Immutable audit trail for compliance & debugging';

-- 19. Quizzes
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    class_id INT NULL COMMENT 'Optional: link to a specific course_class/module',
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    time_limit_minutes INT NULL COMMENT 'NULL = no time limit',
    passing_score INT DEFAULT 70 COMMENT 'Percentage (0-100)',
    max_attempts INT DEFAULT 1 COMMENT '0 = unlimited',
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

-- 20. Quiz Questions
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

-- 21. Quiz Question Options (for multiple_choice and true_false)
CREATE TABLE IF NOT EXISTS quiz_question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    order_index INT DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    INDEX idx_options_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 22. Quiz Attempts (per-student per-quiz)
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

-- 23. Quiz Answers (per-question per-attempt)
CREATE TABLE IF NOT EXISTS quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option_id INT NULL,
    answer_text TEXT NULL COMMENT 'For short_answer and essay types',
    points_earned INT NULL COMMENT 'NULL = not yet graded (essay/short_answer)',
    is_correct BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_option_id) REFERENCES quiz_question_options(id) ON DELETE SET NULL,
    INDEX idx_answers_attempt (attempt_id),
    UNIQUE KEY uk_answer_attempt_question (attempt_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 24. Class Sessions (individual meeting occurrences)
CREATE TABLE IF NOT EXISTS class_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NULL COMMENT 'NULL for ad-hoc sessions',
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

-- 25. Lesson Participation (student engagement tracking)
CREATE TABLE IF NOT EXISTS lesson_participation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    material_id INT NULL COMMENT 'NULL = participation without specific material',
    watched_duration INT DEFAULT 0 COMMENT 'Duration in seconds',
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


-- ============================================================================
-- PERFORMANCE INDEXES
-- ============================================================================
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_courses_instrument ON courses(instrument_id);
CREATE INDEX IF NOT EXISTS idx_enrollments_status ON enrollments(status);
CREATE INDEX IF NOT EXISTS idx_enrollments_student ON enrollments(student_id);
CREATE INDEX IF NOT EXISTS idx_enrollments_course ON enrollments(course_id);
CREATE INDEX IF NOT EXISTS idx_schedules_course ON schedules(course_id);
CREATE INDEX IF NOT EXISTS idx_schedules_instructor ON schedules(instructor_id);
CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance(date);
CREATE INDEX IF NOT EXISTS idx_attendance_student ON attendance(student_id);
CREATE INDEX IF NOT EXISTS idx_attendance_status ON attendance(status);
CREATE INDEX IF NOT EXISTS idx_materials_course ON materials(course_id);
CREATE INDEX IF NOT EXISTS idx_materials_type ON materials(file_type);
CREATE INDEX IF NOT EXISTS idx_materials_uploader ON materials(uploaded_by);
CREATE INDEX IF NOT EXISTS idx_assignments_course ON assignments(course_id);
CREATE INDEX IF NOT EXISTS idx_assignments_due ON assignments(due_date);
CREATE INDEX IF NOT EXISTS idx_submissions_assignment ON submissions(assignment_id);
CREATE INDEX IF NOT EXISTS idx_submissions_student ON submissions(student_id);
CREATE INDEX IF NOT EXISTS idx_submissions_status ON submissions(status);
CREATE INDEX IF NOT EXISTS idx_chat_course_thread ON chat_messages(course_id, sender_id, receiver_id);
CREATE INDEX IF NOT EXISTS idx_chat_receiver ON chat_messages(receiver_id);
CREATE INDEX IF NOT EXISTS idx_chat_read ON chat_messages(is_read);
CREATE INDEX IF NOT EXISTS idx_certificates_student ON certificates(student_id);
CREATE INDEX IF NOT EXISTS idx_user_uploads_user ON user_uploads(user_id);
CREATE INDEX IF NOT EXISTS idx_uploads_type ON user_uploads(file_type);
CREATE INDEX IF NOT EXISTS idx_uploads_uploaded ON user_uploads(uploaded_at);
CREATE INDEX IF NOT EXISTS idx_instructor_on_course ON instructor_assignments(course_id);
