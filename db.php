<?php
$host = getenv("DB_HOST") ?: "127.0.0.1";
$user = getenv("DB_USER") ?: "root";
$pass = getenv("DB_PASS") ?: "";
$name = getenv("DB_NAME") ?: "happy_teeth_db";
$port = (int)(getenv("DB_PORT") ?: 3306);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($host, $user, $pass, $name, $port);
$conn->set_charset("utf8mb4");
// clinic-day helper: returns the clinic "today" based on clinicStart
function get_clinic_date(string $clinicStart = '00:00', string $tz = null): string {
    $tz = $tz ?? date_default_timezone_get() ?: 'UTC';
    $tzObj = new DateTimeZone($tz);
    $now = new DateTime('now', $tzObj);

    // Validate clinicStart (expect HH:MM)
    if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $clinicStart, $m)) {
    $clinicStart = '00:00';
    $sh = 0; $sm = 0;
    } else {
        $sh = (int)$m[1];
        $sm = (int)$m[2];
    }

    $startToday = (clone $now)->setTime($sh, $sm, 0);

    if ($now < $startToday) {
        return $now->modify('-1 day')->format('Y-m-d');
    }
    return $now->format('Y-m-d');
}