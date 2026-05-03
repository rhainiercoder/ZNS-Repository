<?php
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../db.php";

$user = require_role(["receptionist"]);
$role = $user["role"];
$active = "dashboard";

function h($v){ return htmlspecialchars((string)$v); }

date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');
$next24 = date('Y-m-d H:i:s', time() + 24*60*60);

$groups = [];
$stmt = $conn->prepare("
  SELECT
    a.appointment_date,
    a.appointment_time,
    a.status,
    u.name AS patient_name,
    s.name AS service,
    d.name AS dentist_name
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

foreach ($rows as $r) {
  $label = $r['dentist_name'] ?: 'Unassigned Dentist';
  $groups[$label][] = $r;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Hello Receptionist Dashboard</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../partials/sidebar.php"; ?>

<main class="main">
  <div class="pageHead">
    <h1 class="pageHead__title"> Receptionist <?php echo h($user["name"]); ?> Dashboard</h1>
  </div>

  <section class="card" style="background:#e9f7ff; margin-bottom:14px;">
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a class="btn btn--dark" href="/qm/pages/receptionist/transactions.php">Manage Transactions</a>
      <a class="btn" href="/qm/pages/receptionist/print_schedule.php" target="_blank">Print Dentist Schedule (Next 24h)</a>
    </div>
  </section>

  <section class="card" style="background:#e9f7ff;">
    <h2 class="sectionTitle">Upcoming Appointments (Next 24 Hours)</h2>

    <?php if (!$groups): ?>
      <div style="font-weight:900; opacity:.75;">No upcoming appointments.</div>
    <?php else: ?>
      <?php foreach ($groups as $dentist => $items): ?>
        <div class="card" style="background:#fff; margin:12px 0; padding:12px;">
          <div style="font-weight:1000; color:#0b2f4f;"><?php echo h($dentist); ?></div>

          <div class="table" style="margin-top:10px;">
            <div class="table__row table__row--head" style="grid-template-columns: 1.1fr .8fr .6fr;">
              <div>Patient / Service</div>
              <div>Date/Time</div>
              <div>Status</div>
            </div>

            <?php foreach ($items as $r): ?>
              <div class="table__row" style="grid-template-columns: 1.1fr .8fr .6fr;">
                <div style="font-weight:900; color:#0b2f4f;">
                  <?php echo h($r['patient_name']); ?>
                  <div style="font-size:12px; font-weight:800; opacity:.75;"><?php echo h($r['service']); ?></div>
                </div>
                <div class="table__muted"><?php echo h($r['appointment_date']); ?> <?php echo h(substr($r['appointment_time'],0,5)); ?></div>
                <div style="font-weight:900;"><?php echo h($r['status']); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</main>
</body>
</html>