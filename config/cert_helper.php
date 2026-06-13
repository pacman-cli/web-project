<?php
// config/cert_helper.php
// Shared certificate utilities — single source of truth for salt, hash, eligibility

/**
 * Load the certificate signing salt.
 * Checks for a local override in config/cert.local.php first.
 */
function cert_get_salt(): string {
    $salt = 'LyraAcademySecretSalt2026';
    $configFile = __DIR__ . '/cert.local.php';
    if (file_exists($configFile)) {
        $override = include $configFile;
        if (is_array($override) && !empty($override['cert_salt'])) {
            $salt = $override['cert_salt'];
        }
    }
    return $salt;
}

/**
 * Generate a deterministic certificate hash for a student+course pair.
 * Uses HMAC-SHA256 with the cert salt — no time() component.
 */
function cert_generate_hash(int $studentId, int $courseId): string {
    $salt = cert_get_salt();
    return hash_hmac('sha256', "$studentId:$courseId", $salt);
}

/**
 * Calculate certificate eligibility for a student in a course.
 *
 * @return array{progress: float, attendance: float, eligible: bool}
 */
function cert_get_eligibility(PDO $pdo, int $studentId, int $courseId): array {
    // Assignment completion
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE course_id = :cid");
    $stmt->execute(['cid' => $courseId]);
    $total = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        WHERE a.course_id = :cid AND s.student_id = :sid
    ");
    $stmt->execute(['cid' => $courseId, 'sid' => $studentId]);
    $submitted = (int) $stmt->fetchColumn();

    $progress = $total > 0 ? round(($submitted / $total) * 100) : 100;

    // Attendance rate
    $stmt = $pdo->prepare("
        SELECT status FROM attendance a
        JOIN schedules s ON a.schedule_id = s.id
        WHERE a.student_id = :sid AND s.course_id = :cid
    ");
    $stmt->execute(['sid' => $studentId, 'cid' => $courseId]);
    $records = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $present = 0;
    $excused = 0;
    foreach ($records as $r) {
        if ($r === 'present') $present++;
        elseif ($r === 'excused') $excused++;
    }

    $totalSessions = count($records);
    $attendance = 100;
    if ($totalSessions > 0) {
        $denom = $totalSessions - $excused;
        if ($denom > 0) {
            $attendance = round(($present / $denom) * 100);
        }
    }

    return [
        'progress'   => $progress,
        'attendance' => $attendance,
        'eligible'   => ($progress >= 90 && $attendance >= 80),
    ];
}
