# Route Mapping Table

| Role | Surface | Route | Status |
|---|---|---|---|
| Public | Home | `/43_Public_Homepage/index.php` | Active |
| Public | Courses | `/42_Public_Course_Catalog/index.php` | Active |
| Public | Instruments | `/44_Instrument_Categories/index.php` | Active |
| Public | About | `/46_About_Us/index.php` | Active |
| Public | Contact | `/47_Contact_Us/index.php` | Active |
| Public | Course detail | `/45_Public_Course_Detail/index.php?course_id={id}` | Active |
| Auth | Login | `/auth/login.php` | Active |
| Auth | Register | `/auth/register.php` | Active |
| Admin | Dashboard | `/02_Admin_Dashboard/index.php` | Active |
| Admin | Instructors | `/01_Instructor_Management/index.php` | Active |
| Admin | Instruments | `/03_Instrument_Categories/index.php` | Active |
| Admin | Courses | `/05_Course_Management/index.php` | Active |
| Admin | Assignments | `/07_Instructor_Assignments/index.php` | Active |
| Admin | Enrollments | `/11_Enrollment_Requests/index.php` | Active |
| Admin | Reports | `/15_Reports_Analytics/index.php` | Active |
| Instructor | Dashboard | `/17_Instructor_Dashboard/index.php` | Active |
| Instructor | Materials | `/16_Lesson_Materials/index.php` | Active |
| Instructor | Messages | `/30_Instructor_Messages/index.php` | Active |
| Instructor | Schedules | `/18_Class_Schedules/index.php` | Active |
| Instructor | My Courses | `/19_My_Courses/index.php` | Active |
| Instructor | Attendance | `/22_Attendance/index.php` | Active |
| Instructor | Assignments | `/23_Assignments/index.php` | Active |
| Instructor | Quizzes | `/24_Instructor_Quizzes/index.php` | Active |
| Instructor | Reviews | `/25_Recording_Reviews/index.php` | Active |
| Instructor | Students | `/27_Course_Students/index.php` | Active |
| Instructor | Certificates | `/28_Bulk_Certificates/index.php` | Active |
| Student | Dashboard | `/40_Student_Dashboard/index.php` | Active |
| Student | Messages | `/33_Student_Messages/index.php` | Active |
| Student | Schedules | `/41_Student_Schedules/index.php` | New |
| Student | Materials | `/16_Lesson_Materials/index.php` | Active |
| Student | Certificates | `/34_Student_Certificates/index.php` | Active |
| Student | Attendance | `/35_Student_Attendance/index.php` | Active |
| Student | Recordings | `/36_Student_Recordings/index.php` | Active |
| Student | Quizzes | `/38_Student_Quizzes/index.php` | Active |
| Student | My Courses | `/39_Student_My_Courses/index.php` | Active |

## Fixed route notes

- Student recordings feedback now uses the assignment’s real `course_id` and `assignment_id` instead of hard-coded `course_id=1`.
- Student course cards now open the dual-role materials screen at `/16_Lesson_Materials/index.php`.
- Public navbar now exposes all public pages, not just Home and Courses.
