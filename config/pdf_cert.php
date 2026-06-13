<?php
// config/pdf_cert.php
// Pure PHP PDF certificate generator — no external libraries

require_once __DIR__ . '/cert_helper.php';

/**
 * Generate a PDF certificate.
 *
 * @param string $studentName
 * @param string $courseName
 * @param string $instructorName
 * @param string $date          Formatted date string (e.g. "June 11, 2026")
 * @param string $certificateHash  The unique certificate hash (used as cert ID + verification)
 * @return string Raw PDF content
 */
function generate_certificate_pdf(string $studentName, string $courseName, string $instructorName, string $date, string $certificateHash = ''): string {
    // A4 landscape: 842 x 595 points (72 dpi)
    $w = 842;
    $h = 595;
    $margin = 24;

    // Build verification URL
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $verifyUrl = $certificateHash ? "${scheme}://${host}/api/certificate_verify.php?hash={$certificateHash}" : '';
    $shortId = $certificateHash ? strtoupper(substr($certificateHash, 0, 16)) : '';

    $lines = [];
    $lines[] = '%PDF-1.4';
    $lines[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
    $lines[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj";

    // Page object
    $content = "q\n";
    // White background
    $content .= "1 1 1 rg 0 0 {$w} {$h} re f\n";

    // Gold outer border
    $content .= "0.82 0.71 0.22 RG 4 w {$margin} {$margin} " . ($w - 2*$margin) . ' ' . ($h - 2*$margin) . " re S\n";
    // Inner thin border
    $content .= "0.82 0.71 0.22 RG 1.5 w " . ($margin + 6) . ' ' . ($margin + 6) . ' ' . ($w - 2*$margin - 12) . ' ' . ($h - 2*$margin - 12) . " re S\n";

    // Corner decorations
    $cornerSize = 30;
    $pts = [
        [$margin + 12, $margin + 12, $margin + 12, $margin + 12 + $cornerSize],
        [$margin + 12, $margin + 12, $margin + 12 + $cornerSize, $margin + 12],
        [$w - $margin - 12, $margin + 12, $w - $margin - 12, $margin + 12 + $cornerSize],
        [$w - $margin - 12, $margin + 12, $w - $margin - 12 - $cornerSize, $margin + 12],
        [$margin + 12, $h - $margin - 12, $margin + 12, $h - $margin - 12 - $cornerSize],
        [$margin + 12, $h - $margin - 12, $margin + 12 + $cornerSize, $h - $margin - 12],
        [$w - $margin - 12, $h - $margin - 12, $w - $margin - 12, $h - $margin - 12 - $cornerSize],
        [$w - $margin - 12, $h - $margin - 12, $w - $margin - 12 - $cornerSize, $h - $margin - 12],
    ];
    $content .= "0.82 0.71 0.22 RG 2 w\n";
    for ($i = 0; $i < count($pts); $i += 2) {
        $content .= "{$pts[$i][0]} {$pts[$i][1]} m {$pts[$i+1][0]} {$pts[$i+1][1]} l S\n";
    }

    // Decorative line under title area
    $content .= "0.82 0.71 0.22 RG 1 w " . ($w/2 - 80) . ' 410 ' . ($w/2 + 80) . ' 410 re S' . "\n";

    // Helper: draw a centered line of text at y position
    function pdf_text(string $text, float $fontSize, array $rgb, float $y, float $pageWidth): string {
        $safe = escape_pdf_string($text);
        $r = $rgb[0]; $g = $rgb[1]; $b = $rgb[2];
        // Tm uses identity matrix: 1 0 0 1 x y — positions text baseline at (x, y)
        return "BT /F1 {$fontSize} Tf {$r} {$g} {$b} rg 1 0 0 1 " . ($pageWidth / 2) . " {$y} Tm ({$safe}) Tj ET\n";
    }

    // School name
    $content .= pdf_text('LYRA ACADEMY', 24, [0.4, 0.2, 0.05], 490, $w);

    // "Certificate of Completion"
    $content .= pdf_text('Certificate of Completion', 36, [0.82, 0.71, 0.22], 450, $w);

    // This is to certify that
    $content .= pdf_text('This is to certify that', 14, [0.2, 0.2, 0.2], 400, $w);

    // Student name
    $content .= pdf_text($studentName, 32, [0.4, 0.2, 0.05], 355, $w);

    // has successfully completed
    $content .= pdf_text('has successfully completed the course', 14, [0.2, 0.2, 0.2], 315, $w);

    // Course name
    $content .= pdf_text($courseName, 22, [0.4, 0.2, 0.05], 275, $w);

    // Date line
    $content .= pdf_text("Date: {$date}", 11, [0.2, 0.2, 0.2], 225, $w);

    // Signature line
    $sigY = 170;
    $content .= "0.2 0.2 0.2 RG 1 w " . ($w/2 - 60) . " {$sigY} " . ($w/2 + 60) . " {$sigY} re S\n";
    $content .= pdf_text($instructorName, 10, [0.2, 0.2, 0.2], $sigY - 18, $w);
    $content .= pdf_text('Instructor', 9, [0.4, 0.4, 0.4], $sigY - 32, $w);

    // Certificate ID + verification URL at bottom
    if ($shortId) {
        $content .= pdf_text("Certificate ID: {$shortId}", 7, [0.5, 0.5, 0.5], 50, $w);
        $content .= pdf_text("Verify at: {$verifyUrl}", 6, [0.6, 0.6, 0.6], 38, $w);
    } else {
        $content .= pdf_text('This certificate was issued electronically by Lyra Academy Music School.', 7, [0.6, 0.6, 0.6], 42, $w);
    }

    $content .= "Q\n";

    $streamLen = strlen($content);
    $lines[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$w} {$h}] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj";
    $lines[] = "4 0 obj\n<< /Length {$streamLen} >>\nstream\n{$content}\nendstream\nendobj";

    // Font: Helvetica Bold
    $lines[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj";

    // Cross-reference table
    $offsets = [];
    $offset = 0;
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i] . "\n";
        if (preg_match('/^(\d+) \d+ obj/', $lines[$i], $m)) {
            $num = intval($m[1]);
            $offsets[$num] = $offset;
        }
        $offset += strlen($line);
    }

    // Recalculate offsets with the actual positions
    $output = '';
    $currentOffset = 0;
    $objOffsets = [];

    foreach ($lines as $i => $line) {
        if (preg_match('/^(\d+) \d+ obj/', $line, $m)) {
            $objOffsets[intval($m[1])] = $currentOffset;
        }
        $output .= $line . "\n";
        $currentOffset += strlen($line) + 1;
    }

    $startXref = $currentOffset;
    $output .= "xref\n";
    $maxObj = 5;
    $output .= "0 " . ($maxObj + 1) . "\n";
    $output .= sprintf("%010d 65535 f \n", 0);
    for ($i = 1; $i <= $maxObj; $i++) {
        $offset = $objOffsets[$i] ?? 0;
        $output .= sprintf("%010d 00000 n \n", $offset);
    }

    $output .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\n";
    $output .= "startxref\n{$startXref}\n%%EOF\n";

    return $output;
}

function escape_pdf_string(string $s): string {
    $s = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    $s = preg_replace('/[\\x00-\\x1f\\x7f-\\xff]/', '', $s);
    return $s;
}
