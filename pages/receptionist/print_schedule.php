<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["receptionist"]);
$role = $user["role"];
function h($v){ return htmlspecialchars((string)$v); }

date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');
$next24 = date('Y-m-d H:i:s', time() + 24*60*60);

$stmt = $conn->prepare("
  SELECT
    d.name AS dentist_name,
    u.name AS patient_name,
    s.name AS service,
    a.appointment_date,
    a.appointment_time,
    a.status
  FROM appointments a
  JOIN users u ON u.id = a.patient_id
  JOIN services s ON s.id = a.service_id
  LEFT JOIN users d ON d.id = a.dentist_id
  WHERE CONCAT(a.appointment_date, ' ', a.appointment_time) >= ?
    AND CONCAT(a.appointment_date, ' ', a.appointment_time) <= ?
    AND a.status IN ('approved','pending')
  ORDER BY d.name ASC, a.appointment_date ASC, a.appointment_time ASC
");
$stmt->bind_param("ss", $now, $next24);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Print Schedule (Next 24h)</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
  <style>
    @page { margin: 14mm; }
    body { background:#fff; color:#111; font-family: Arial, Helvetica, sans-serif; }
    .wrap { max-width: 900px; margin:0 auto; padding:16px; }
    .no-print { margin-bottom: 12px; display:flex; gap:8px; justify-content:flex-end; }
    @media print { .no-print { display:none !important; } }
    table { width:100%; border-collapse: collapse; margin-top:10px; }
    th, td { border:1px solid #ddd; padding:8px; font-size:13px; }
    th { background:#f3f6f9; text-align:left; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="no-print">
    <button class="btn btn--dark" onclick="window.print()">Print</button>
    <button class="btn" onclick="window.close()">Close</button>
  </div>

  <h2 style="margin:0;">Dentist Schedule (Next 24 Hours)</h2>
  <div style="font-weight:700; opacity:.75; margin-top:4px;">Generated: <?php echo h(date('Y-m-d H:i')); ?></div>

  <?php if (!$rows): ?>
    <div style="margin-top:12px; font-weight:900; opacity:.75;">No upcoming appointments.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Dentist</th>
          <th>Patient</th>
          <th>Service</th>
          <th>Date/Time</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo h($r['dentist_name'] ?: 'Unassigned'); ?></td>
            <td><?php echo h($r['patient_name']); ?></td>
            <td><?php echo h($r['service']); ?></td>
            <td><?php echo h($r['appointment_date']); ?> <?php echo h(substr($r['appointment_time'],0,5)); ?></td>
            <td><?php echo h($r['status']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
</body>
</html>