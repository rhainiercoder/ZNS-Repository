<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . "/../db.php";

$themeBlue = '#2f63e0';

// FullCalendar sends ?start=YYYY-MM-DD&end=YYYY-MM-DD
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

$sql = "
  SELECT a.id, a.appointment_date, a.appointment_time, a.status,
         u.name AS patient_name, s.name AS service_name
  FROM appointments a
  LEFT JOIN users u ON u.id = a.patient_id
  LEFT JOIN services s ON s.id = a.service_id
  WHERE a.status IN ('approved','pending')
";

$params = [];
$types = "";

if ($start && $end) {
  $sql .= " AND a.appointment_date BETWEEN ? AND ? ";
  $types = "ss";
  $params = [$start, $end];
}

$sql .= " ORDER BY a.appointment_date, a.appointment_time LIMIT 1000";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$events = [];
while ($row = $res->fetch_assoc()) {
  $date = $row['appointment_date'];
  $time = $row['appointment_time'];

  // If time is NULL/empty, default to 09:00 to make it visible
  if (!$time) $time = '09:00:00';

  // Ensure HH:MM:SS
  $time = strlen($time) === 5 ? ($time . ':00') : $time;

  $startISO = $date . 'T' . substr($time, 0, 8);

  $title = ($row['service_name'] ? $row['service_name'] . ' — ' : '')
         . ($row['patient_name'] ?: 'Patient');

  $events[] = [
    'id' => (int)$row['id'],
    'title' => $title,
    'start' => $startISO,
    'backgroundColor' => $themeBlue,
    'borderColor' => $themeBlue,
    'textColor' => '#ffffff',
    'url' => "pages/admin/appointments.php?appointment_id=" . (int)$row['id'],
  ];
}

echo json_encode($events, JSON_UNESCAPED_UNICODE);