-- Lyra Academy LMS — Full seed data
-- All passwords: admin123 (bcrypt hash)

-- ── Users ──────────────────────────────────────────────────────────────────────
INSERT INTO users (name, email, password_hash, role, status) VALUES
('System Admin',    'admin@lyra.edu',     '$2y$12$AxKaUn.njlvsZF0SeVQdvOzbUsTMk5vV0.6lpIoeDH94qyuoEz6Aa', 'admin',      'active'),
('Sarah Chen',      'sarah@lyra.edu',     '$2y$12$AxKaUn.njlvsZF0SeVQdvOzbUsTMk5vV0.6lpIoeDH94qyuoEz6Aa', 'instructor', 'active'),
('James Williams',  'james@lyra.edu',     '$2y$12$AxKaUn.njlvsZF0SeVQdvOzbUsTMk5vV0.6lpIoeDH94qyuoEz6Aa', 'instructor', 'active'),
('Emily Davis',     'emily@student.edu',  '$2y$12$AxKaUn.njlvsZF0SeVQdvOzbUsTMk5vV0.6lpIoeDH94qyuoEz6Aa', 'student',    'active'),
('Michael Brown',   'michael@student.edu','$2y$12$AxKaUn.njlvsZF0SeVQdvOzbUsTMk5vV0.6lpIoeDH94qyuoEz6Aa', 'student',    'active')
ON DUPLICATE KEY UPDATE name = name, password_hash = password_hash;

-- ── Instructor profiles ─────────────────────────────────────────────────────────
INSERT INTO instructors (user_id, bio, specialization, hourly_rate, hire_date) VALUES
((SELECT id FROM users WHERE email='sarah@lyra.edu'),
 'Pianist with 12 years of teaching experience. Juilliard graduate.',
 'Piano & Keyboard', 85.00, '2023-01-15'),
((SELECT id FROM users WHERE email='james@lyra.edu'),
 'Guitarist and music producer. Performed with regional orchestras.',
 'Guitar & Music Production', 75.00, '2023-03-20')
ON DUPLICATE KEY UPDATE bio = bio;

-- ── Student profiles ────────────────────────────────────────────────────────────
INSERT INTO students (user_id, date_of_birth, parent_name, parent_contact, experience_level, enrollment_date) VALUES
((SELECT id FROM users WHERE email='emily@student.edu'),
 '2005-06-15', 'Linda Davis', '555-0101', 'intermediate', '2024-09-01'),
((SELECT id FROM users WHERE email='michael@student.edu'),
 '2004-11-22', NULL, NULL, 'beginner', '2024-09-01')
ON DUPLICATE KEY UPDATE experience_level = experience_level;

-- ── Instruments ─────────────────────────────────────────────────────────────────
INSERT INTO instruments (name, description) VALUES
('Piano',    'Classical and modern piano'),
('Guitar',   'Acoustic and electric guitar'),
('Violin',   'Classical violin'),
('Drums',    'Percussion and drum kit'),
('Vocals',   'Singing and voice training')
ON DUPLICATE KEY UPDATE description = description;

-- ── Courses ─────────────────────────────────────────────────────────────────────
INSERT INTO courses (title, description, instrument_id, difficulty, price, status) VALUES
('Piano Fundamentals',   'Learn the basics of piano from scales to simple songs.', 1, 'beginner', 120.00, 'published'),
('Intermediate Piano',   'Expand your piano skills with chord progressions and theory.', 1, 'intermediate', 150.00, 'published'),
('Guitar for Beginners', 'Start your guitar journey with chords and strumming patterns.', 2, 'beginner', 100.00, 'published'),
('Music Production 101', 'Introduction to recording, mixing, and digital audio workstations.', 2, 'intermediate', 200.00, 'published')
ON DUPLICATE KEY UPDATE title = title;

-- ── Instructor assignments ──────────────────────────────────────────────────────
INSERT INTO instructor_assignments (instructor_id, course_id) VALUES
((SELECT id FROM users WHERE email='sarah@lyra.edu'), (SELECT id FROM courses WHERE title='Piano Fundamentals')),
((SELECT id FROM users WHERE email='sarah@lyra.edu'), (SELECT id FROM courses WHERE title='Intermediate Piano')),
((SELECT id FROM users WHERE email='james@lyra.edu'), (SELECT id FROM courses WHERE title='Guitar for Beginners')),
((SELECT id FROM users WHERE email='james@lyra.edu'), (SELECT id FROM courses WHERE title='Music Production 101'))
ON DUPLICATE KEY UPDATE assigned_at = CURRENT_TIMESTAMP;

-- ── Enrollments ─────────────────────────────────────────────────────────────────
INSERT INTO enrollments (student_id, course_id, status, reviewed_by, reviewed_at) VALUES
((SELECT id FROM users WHERE email='emily@student.edu'),  (SELECT id FROM courses WHERE title='Piano Fundamentals'),  'approved', (SELECT id FROM users WHERE email='admin@lyra.edu'), NOW()),
((SELECT id FROM users WHERE email='emily@student.edu'),  (SELECT id FROM courses WHERE title='Guitar for Beginners'), 'approved', (SELECT id FROM users WHERE email='admin@lyra.edu'), NOW()),
((SELECT id FROM users WHERE email='michael@student.edu'),(SELECT id FROM courses WHERE title='Piano Fundamentals'),  'approved', (SELECT id FROM users WHERE email='admin@lyra.edu'), NOW()),
((SELECT id FROM users WHERE email='michael@student.edu'),(SELECT id FROM courses WHERE title='Music Production 101'), 'pending', NULL, NULL)
ON DUPLICATE KEY UPDATE status = status;

-- ── Schedules ───────────────────────────────────────────────────────────────────
INSERT INTO schedules (course_id, instructor_id, day_of_week, start_time, end_time, location_type, location_detail) VALUES
((SELECT id FROM courses WHERE title='Piano Fundamentals'),  (SELECT id FROM users WHERE email='sarah@lyra.edu'), 'Monday',    '10:00:00', '11:30:00', 'physical', 'Room 101'),
((SELECT id FROM courses WHERE title='Piano Fundamentals'),  (SELECT id FROM users WHERE email='sarah@lyra.edu'), 'Wednesday', '10:00:00', '11:30:00', 'physical', 'Room 101'),
((SELECT id FROM courses WHERE title='Intermediate Piano'),  (SELECT id FROM users WHERE email='sarah@lyra.edu'), 'Tuesday',   '14:00:00', '15:30:00', 'online',   'https://zoom.us/j/example1'),
((SELECT id FROM courses WHERE title='Guitar for Beginners'),(SELECT id FROM users WHERE email='james@lyra.edu'), 'Tuesday',   '09:00:00', '10:30:00', 'physical', 'Room 102'),
((SELECT id FROM courses WHERE title='Guitar for Beginners'),(SELECT id FROM users WHERE email='james@lyra.edu'), 'Thursday',  '09:00:00', '10:30:00', 'physical', 'Room 102'),
((SELECT id FROM courses WHERE title='Music Production 101'),(SELECT id FROM users WHERE email='james@lyra.edu'), 'Friday',    '13:00:00', '15:00:00', 'online',   'https://zoom.us/j/example2')
ON DUPLICATE KEY UPDATE location_detail = location_detail;

-- ── Assignments ───────────────────────────────────────────────────────────────
INSERT INTO assignments (course_id, title, description, due_date, max_points) VALUES
((SELECT id FROM courses WHERE title='Piano Fundamentals'),  'Scale Practice Recording',   'Record yourself playing C major and G major scales, 2 octaves.',       '2026-07-01 23:59:59', 100),
((SELECT id FROM courses WHERE title='Piano Fundamentals'),  'Chord Progression Essay',     'Write 300 words on I-IV-V-I chord progressions in popular music.',   '2026-07-15 23:59:59', 50),
((SELECT id FROM courses WHERE title='Guitar for Beginners'), 'First Song Performance',      'Record yourself playing "Twinkle Twinkle Little Star" with chords.', '2026-07-10 23:59:59', 100),
((SELECT id FROM courses WHERE title='Music Production 101'), 'Mix Assignment 1',            'Submit a 2-track mix using the provided stems.',                      '2026-07-20 23:59:59', 100)
ON DUPLICATE KEY UPDATE title = title;

-- ── Submissions ───────────────────────────────────────────────────────────────
INSERT INTO submissions (assignment_id, student_id, file_path, submission_text, points_earned, feedback, status, graded_by, graded_at) VALUES
((SELECT id FROM assignments WHERE title='Scale Practice Recording'  AND course_id=(SELECT id FROM courses WHERE title='Piano Fundamentals')),
 (SELECT user_id FROM students WHERE user_id=(SELECT id FROM users WHERE email='emily@student.edu')),
 '/uploads/submissions/scale_practice_emily.mp3', NULL, 92, 'Excellent timing and even tempo. Watch the transition between octaves.', 'graded',
 (SELECT id FROM users WHERE email='sarah@lyra.edu'), '2026-06-10 14:30:00'),

((SELECT id FROM assignments WHERE title='Scale Practice Recording'  AND course_id=(SELECT id FROM courses WHERE title='Piano Fundamentals')),
 (SELECT user_id FROM students WHERE user_id=(SELECT id FROM users WHERE email='michael@student.edu')),
 '/uploads/submissions/scale_practice_michael.mp3', NULL, NULL, NULL, 'submitted', NULL, NULL),

((SELECT id FROM assignments WHERE title='Chord Progression Essay'   AND course_id=(SELECT id FROM courses WHERE title='Piano Fundamentals')),
 (SELECT user_id FROM students WHERE user_id=(SELECT id FROM users WHERE email='emily@student.edu')),
 '/uploads/submissions/chord_essay_emily.pdf', 'I-IV-V-I is the backbone of pop music. Songs like Let It Be and No Woman No Cry rely on this progression...', 45, 'Great analysis with real-world examples.', 'graded',
 (SELECT id FROM users WHERE email='sarah@lyra.edu'), '2026-06-12 10:00:00'),

((SELECT id FROM assignments WHERE title='First Song Performance'   AND course_id=(SELECT id FROM courses WHERE title='Guitar for Beginners')),
 (SELECT user_id FROM students WHERE user_id=(SELECT id FROM users WHERE email='emily@student.edu')),
 '/uploads/submissions/twinkle_emily.mp4', NULL, 88, 'Good chord changes. Practice keeping a steady strumming rhythm.', 'graded',
 (SELECT id FROM users WHERE email='james@lyra.edu'), '2026-06-11 16:45:00'),

((SELECT id FROM assignments WHERE title='Mix Assignment 1'          AND course_id=(SELECT id FROM courses WHERE title='Music Production 101')),
 (SELECT user_id FROM students WHERE user_id=(SELECT id FROM users WHERE email='michael@student.edu')),
 '/uploads/submissions/mix1_michael.wav', NULL, NULL, NULL, 'submitted', NULL, NULL)
ON DUPLICATE KEY UPDATE status = status;

-- ── Materials ─────────────────────────────────────────────────────────────────
INSERT INTO materials (course_id, title, file_path, file_type, uploaded_by) VALUES
((SELECT id FROM courses WHERE title='Piano Fundamentals'),  'C Major Scale Guide (PDF)',  '/uploads/materials/c_major_scale.pdf',   'pdf',     (SELECT id FROM users WHERE email='sarah@lyra.edu')),
((SELECT id FROM courses WHERE title='Piano Fundamentals'),  'Scale Practice Backing Track','/uploads/materials/scale_backing.mp3',  'audio',   (SELECT id FROM users WHERE email='sarah@lyra.edu')),
((SELECT id FROM courses WHERE title='Guitar for Beginners'), 'Open Chords Cheat Sheet',    '/uploads/materials/open_chords.pdf',     'pdf',     (SELECT id FROM users WHERE email='james@lyra.edu')),
((SELECT id FROM courses WHERE title='Guitar for Beginners'), 'Strumming Patterns Video',   '/uploads/materials/strumming.mp4',       'video',   (SELECT id FROM users WHERE email='james@lyra.edu')),
((SELECT id FROM courses WHERE title='Music Production 101'), 'DAW Setup Tutorial',         '/uploads/materials/daw_setup.pdf',       'pdf',     (SELECT id FROM users WHERE email='james@lyra.edu'))
ON DUPLICATE KEY UPDATE title = title;

-- ── Chat Messages ─────────────────────────────────────────────────────────────
INSERT INTO chat_messages (course_id, sender_id, receiver_id, message_text, is_read) VALUES
((SELECT id FROM courses WHERE title='Piano Fundamentals'),
 (SELECT id FROM users WHERE email='emily@student.edu'),
 (SELECT id FROM users WHERE email='sarah@lyra.edu'),
 'Hi Sarah, I have a question about the C major scale practice.', FALSE),
((SELECT id FROM courses WHERE title='Piano Fundamentals'),
 (SELECT id FROM users WHERE email='sarah@lyra.edu'),
 (SELECT id FROM users WHERE email='emily@student.edu'),
 'Sure Emily! Make sure you keep your wrists relaxed and use proper fingering.', TRUE),
((SELECT id FROM courses WHERE title='Piano Fundamentals'),
 (SELECT id FROM users WHERE email='emily@student.edu'),
 (SELECT id FROM users WHERE email='sarah@lyra.edu'),
 'Thank you! Should I practice with a metronome?', FALSE),
((SELECT id FROM courses WHERE title='Guitar for Beginners'),
 (SELECT id FROM users WHERE email='emily@student.edu'),
 (SELECT id FROM users WHERE email='james@lyra.edu'),
 'James, when should I start learning barre chords?', TRUE),
((SELECT id FROM courses WHERE title='Guitar for Beginners'),
 (SELECT id FROM users WHERE email='james@lyra.edu'),
 (SELECT id FROM users WHERE email='emily@student.edu'),
 'After you are comfortable with open chords, we can move to barre chords next week.', TRUE)
ON DUPLICATE KEY UPDATE message_text = message_text;

-- ── Quizzes ───────────────────────────────────────────────────────────────────
INSERT INTO quizzes (course_id, title, description, time_limit_minutes, passing_score, max_attempts, status, created_by) VALUES
((SELECT id FROM courses WHERE title='Piano Fundamentals'),  'Piano Basics Quiz',      'Test your knowledge of scales, chords, and basic theory.', 15, 70, 3, 'published', (SELECT id FROM users WHERE email='sarah@lyra.edu')),
((SELECT id FROM courses WHERE title='Guitar for Beginners'), 'Guitar Fundamentals Quiz','Covers open chords, strumming patterns, and reading tabs.', 20, 70, 2, 'published', (SELECT id FROM users WHERE email='james@lyra.edu'))
ON DUPLICATE KEY UPDATE title = title;

-- ── Quiz Questions ────────────────────────────────────────────────────────────
INSERT INTO quiz_questions (quiz_id, question_text, question_type, points, order_index) VALUES
((SELECT id FROM quizzes WHERE title='Piano Basics Quiz'),      'How many keys does a standard piano have?',                   'multiple_choice', 10, 1),
((SELECT id FROM quizzes WHERE title='Piano Basics Quiz'),      'What is the correct fingering for the first note of a C major scale (right hand)?', 'multiple_choice', 10, 2),
((SELECT id FROM quizzes WHERE title='Piano Basics Quiz'),      'A whole note equals how many quarter notes?',                  'multiple_choice', 10, 3),
((SELECT id FROM quizzes WHERE title='Guitar Fundamentals Quiz'), 'How many strings does a standard guitar have?',               'multiple_choice', 10, 1),
((SELECT id FROM quizzes WHERE title='Guitar Fundamentals Quiz'), 'What does the E minor chord require on a standard tuning?',  'multiple_choice', 10, 2);

-- ── Quiz Question Options ─────────────────────────────────────────────────────
-- Question 1: How many keys?
INSERT INTO quiz_question_options (question_id, option_text, is_correct, order_index) VALUES
((SELECT id FROM quiz_questions WHERE question_text='How many keys does a standard piano have?' AND quiz_id=(SELECT id FROM quizzes WHERE title='Piano Basics Quiz')),
 '76', FALSE, 1),
((SELECT id FROM quiz_questions WHERE question_text='How many keys does a standard piano have?' AND quiz_id=(SELECT id FROM quizzes WHERE title='Piano Basics Quiz')),
 '88', TRUE, 2),
((SELECT id FROM quiz_questions WHERE question_text='How many keys does a standard piano have?' AND quiz_id=(SELECT id FROM quizzes WHERE title='Piano Basics Quiz')),
 '92', FALSE, 3),
((SELECT id FROM quiz_questions WHERE question_text='How many keys does a standard piano have?' AND quiz_id=(SELECT id FROM quizzes WHERE title='Piano Basics Quiz')),
 '61', FALSE, 4);

-- Question 2: Fingering for C major first note
INSERT INTO quiz_question_options (question_id, option_text, is_correct, order_index) VALUES
((SELECT id FROM quiz_questions WHERE question_text LIKE '%correct fingering%' AND quiz_id=(SELECT id FROM quizzes WHERE title='Piano Basics Quiz')),
 'Thumb (1)', TRUE, 1),
((SELECT id FROM quiz_questions WHERE question_text LIKE '%correct fingering%' AND quiz_id=(SELECT id FROM quizzes WHERE title='Piano Basics Quiz')),
 'Index (2)', FALSE, 2),
((SELECT id FROM quiz_questions WHERE question_text LIKE '%correct fingering%' AND quiz_id=(SELECT id FROM quizzes WHERE title='Piano Basics Quiz')),
 'Middle (3)', FALSE, 3);

-- Question 3: Whole note = how many quarter notes
INSERT INTO quiz_question_options (question_id, option_text, is_correct, order_index) VALUES
((SELECT id FROM quiz_questions WHERE question_text LIKE '%whole note equals%' AND quiz_id=(SELECT id FROM quizzes WHERE title='Piano Basics Quiz')),
 '1', FALSE, 1),
((SELECT id FROM quiz_questions WHERE question_text LIKE '%whole note equals%' AND quiz_id=(SELECT id FROM quizzes WHERE title='Piano Basics Quiz')),
 '2', FALSE, 2),
((SELECT id FROM quiz_questions WHERE question_text LIKE '%whole note equals%' AND quiz_id=(SELECT id FROM quizzes WHERE title='Piano Basics Quiz')),
 '4', TRUE, 3),
((SELECT id FROM quiz_questions WHERE question_text LIKE '%whole note equals%' AND quiz_id=(SELECT id FROM quizzes WHERE title='Piano Basics Quiz')),
 '8', FALSE, 4);

-- Question 4: How many strings on guitar
INSERT INTO quiz_question_options (question_id, option_text, is_correct, order_index) VALUES
((SELECT id FROM quiz_questions WHERE question_text='How many strings does a standard guitar have?' AND quiz_id=(SELECT id FROM quizzes WHERE title='Guitar Fundamentals Quiz')),
 '4', FALSE, 1),
((SELECT id FROM quiz_questions WHERE question_text='How many strings does a standard guitar have?' AND quiz_id=(SELECT id FROM quizzes WHERE title='Guitar Fundamentals Quiz')),
 '6', TRUE, 2),
((SELECT id FROM quiz_questions WHERE question_text='How many strings does a standard guitar have?' AND quiz_id=(SELECT id FROM quizzes WHERE title='Guitar Fundamentals Quiz')),
 '8', FALSE, 3);

-- Question 5: E minor chord
INSERT INTO quiz_question_options (question_id, option_text, is_correct, order_index) VALUES
((SELECT id FROM quiz_questions WHERE question_text LIKE '%E minor chord require%' AND quiz_id=(SELECT id FROM quizzes WHERE title='Guitar Fundamentals Quiz')),
 'Open low E, B on 2nd fret, open G, D on 2nd fret, open B, open high E', TRUE, 1),
((SELECT id FROM quiz_questions WHERE question_text LIKE '%E minor chord require%' AND quiz_id=(SELECT id FROM quizzes WHERE title='Guitar Fundamentals Quiz')),
 'Barre across all strings at 2nd fret', FALSE, 2),
((SELECT id FROM quiz_questions WHERE question_text LIKE '%E minor chord require%' AND quiz_id=(SELECT id FROM quizzes WHERE title='Guitar Fundamentals Quiz')),
 'Open low E, A on 2nd fret, D on 2nd fret, open G, B on 1st fret, open high E', FALSE, 3);
