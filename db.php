<?php
$config = require __DIR__ . "/config.php";

// set app timezone early so PHP and MySQL session can be synced
$tz = $config['timezone'] ?? 'Asia/Manila';
date_default_timezone_set($tz);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $config["db_host"],
        $config["db_user"],
        $config["db_pass"],
        $config["db_name"],
        (int)$config["db_port"]
    );
    $conn->set_charset("utf8mb4");

    // Sync MySQL session timezone to PHP timezone offset (eg +08:00)
    // date('P') will reflect the timezone we just set above.
    try {
        $conn->query("SET time_zone = '" . date('P') . "'");
    } catch (Exception $e) {
        // ignore if the server user can't set time_zone
    }
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die("Database connection failed: " . $e->getMessage());
}

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