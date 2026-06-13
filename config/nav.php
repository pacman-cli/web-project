<?php
/**
 * config/nav.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Centralized Navigation System for Lyra Academy LMS
 *
 * Defines all role-based sidebar menus, icons, and routing.
 * Ensures consistent page ordering and active state highlighting.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$LMS_SIDEBARS = [
    'admin' => [
        'label' => 'Admin Portal',
        'icon'  => 'admin_panel_settings',
        'color' => '#003d9b',
        'nav'   => [
            ['href' => '/02_Admin_Dashboard/index.php',       'icon' => 'dashboard',     'label' => 'Dashboard'],
            ['href' => '/01_Instructor_Management/index.php', 'icon' => 'school',        'label' => 'Instructors'],
            ['href' => '/03_Instrument_Categories/index.php', 'icon' => 'piano',         'label' => 'Instruments'],
            ['href' => '/05_Course_Management/index.php',     'icon' => 'library_music', 'label' => 'Courses'],
            ['href' => '/07_Instructor_Assignments/index.php', 'icon' => 'assignment',    'label' => 'Assignments'],
            ['href' => '/11_Enrollment_Requests/index.php',   'icon' => 'person_add',    'label' => 'Enrollments'],
            ['href' => '/15_Reports_Analytics/index.php',     'icon' => 'analytics',     'label' => 'Reports'],
        ],
    ],
    'instructor' => [
        'label' => 'Instructor Portal',
        'icon'  => 'school',
        'color' => '#1a6b3c',
        'nav'   => [
            ['href' => '/17_Instructor_Dashboard/index.php', 'icon' => 'dashboard',      'label' => 'Dashboard'],
            ['href' => '/16_Lesson_Materials/index.php',     'icon' => 'folder_open',    'label' => 'Materials'],
            ['href' => '/30_Instructor_Messages/index.php',  'icon' => 'chat',           'label' => 'Messages'],
            ['href' => '/18_Class_Schedules/index.php',      'icon' => 'calendar_month', 'label' => 'Schedules'],
            ['href' => '/19_My_Courses/index.php',           'icon' => 'library_music',  'label' => 'My Courses'],
            ['href' => '/22_Attendance/index.php',           'icon' => 'how_to_reg',     'label' => 'Attendance'],
            ['href' => '/23_Assignments/index.php',          'icon' => 'assignment',     'label' => 'Assignments'],
            ['href' => '/24_Instructor_Quizzes/index.php',    'icon' => 'quiz',           'label' => 'Quizzes'],
            ['href' => '/25_Recording_Reviews/index.php',    'icon' => 'rate_review',    'label' => 'Reviews'],
            ['href' => '/27_Course_Students/index.php',      'icon' => 'groups',         'label' => 'Students'],
            ['href' => '/28_Bulk_Certificates/index.php',    'icon' => 'workspace_premium','label' => 'Certificates'],
        ],
    ],
    'student' => [
        'label' => 'Student Portal',
        'icon'  => 'menu_book',
        'color' => '#6a1a8c',
        'nav'   => [
            ['href' => '/40_Student_Dashboard/index.php',     'icon' => 'dashboard',       'label' => 'Dashboard'],
            ['href' => '/33_Student_Messages/index.php',      'icon' => 'chat',            'label' => 'Messages'],
            ['href' => '/41_Student_Schedules/index.php',     'icon' => 'calendar_month',  'label' => 'Schedules'],
            ['href' => '/16_Lesson_Materials/index.php',      'icon' => 'folder_open',     'label' => 'Materials'],
            ['href' => '/34_Student_Certificates/index.php',  'icon' => 'workspace_premium','label' => 'Certificates'],
            ['href' => '/35_Student_Attendance/index.php',    'icon' => 'how_to_reg',      'label' => 'Attendance'],
            ['href' => '/36_Student_Recordings/index.php',    'icon' => 'mic',             'label' => 'Recordings'],
            ['href' => '/37_Student_Assignments_1/index.php',  'icon' => 'assignment',      'label' => 'My Submissions'],
            ['href' => '/38_Student_Quizzes/index.php',       'icon' => 'quiz',            'label' => 'Quizzes'],
            ['href' => '/39_Student_My_Courses/index.php',    'icon' => 'library_music',   'label' => 'My Courses'],
        ],
    ],
    'guest' => [
        'label' => 'Lyra Academy',
        'icon'  => 'public',
        'color' => '#5b4800',
        'nav'   => [
            ['href' => '/43_Public_Homepage/index.php',       'icon' => 'home',            'label' => 'Home'],
            ['href' => '/42_Public_Course_Catalog/index.php', 'icon' => 'library_music',  'label' => 'Courses'],
            ['href' => '/44_Instrument_Categories/index.php', 'icon' => 'piano',           'label' => 'Instruments'],
            ['href' => '/46_About_Us/index.php',              'icon' => 'info',            'label' => 'About'],
            ['href' => '/47_Contact_Us/index.php',            'icon' => 'mail',            'label' => 'Contact'],
        ],
    ]
];
