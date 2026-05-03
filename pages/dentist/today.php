<?php
require_once __DIR__ . "/../../auth.php";
$user = require_role(["dentist"]);
$role = $user["role"];
$active = "today";

require_once __DIR__ . "/../../db.php";
function h($v){ return htmlspecialchars((string)$v); }

$today = date("Y-m-d");

$stmt = $conn->prepare("
  SELECT a.id, a.appointment_time, s.name AS service, u.name AS patient_name
  FROM appointments a
  JOIN users u ON u.id = a.patient_id
  JOIN services s ON s.id = a.service_id
  WHERE a.status = 'approved'
    AND a.appointment_date = ?
    AND a.dentist_id = ?
  ORDER BY a.appointment_time ASC
");
$stmt->bind_param("si", $today, $user["id"]);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Dentist - Today's Patient</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>

<main class="main">
  <div class="pageHead"><h1 class="pageHead__title">Today's Patient</h1></div>

  <section class="card dentistSection">
    <h2 class="sectionTitle">Approved for <?php echo h($today); ?></h2>

    <div class="table">
      <div class="table__row table__row--head" style="grid-template-columns: 1fr .6fr 1fr .7fr;">
        <div>Patient</div>
        <div>Time</div>
        <div style="text-align:right;">Service</div>
        <div style="text-align:right;">Action</div>
    </div>

      <?php foreach ($rows as $r): ?>
        <div class="table__row" style="grid-template-columns: 1fr .6fr 1fr .7fr;">
          <div style="font-weight:900; color:#0b2f4f;"><?php echo h($r["patient_name"]); ?></div>
          <div class="table__muted"><?php echo h(substr($r["appointment_time"], 0, 5)); ?></div>
          <div class="table__right"><?php echo h($r["service"]); ?></div>

          <div style="text-align:right;">
            <a class="btn btn--dark"
              href="/qm/pages/dentist/dental-records.php?appointment_id=<?php echo (int)$r["id"]; ?>">
              Add Record
            </a>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <div class="table__row">
          <div style="grid-column:1 / -1; font-weight:800; opacity:.7;">No approved appointments for today.</div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
</body>
</html>