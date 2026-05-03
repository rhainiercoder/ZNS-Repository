<?php
require_once __DIR__ . "/../../auth.php";
$user = require_role(["patient"]);
$role = $user["role"];
$active = "dental-records";

require_once __DIR__ . "/../../db.php";
function h($v){ return htmlspecialchars((string)$v); }

// Load patient records (latest first)
$service_id = (int)($_GET["service_id"] ?? 0);
$serviceName = "All Services";
if ($service_id > 0) {
  $stmt2 = $conn->prepare("SELECT name FROM services WHERE id = ? LIMIT 1");
  $stmt2->bind_param("i", $service_id);
  $stmt2->execute();
  $row2 = $stmt2->get_result()->fetch_assoc();
  if ($row2) $serviceName = $row2["name"];
}
$stmt = $conn->prepare("
  SELECT
    dr.id,
    dr.created_at,
    dr.diagnosis,
    dr.treatment,
    dr.prescription,
    dr.notes,
    a.appointment_date,
    a.appointment_time,
    s.name AS service,
    d.name AS dentist_name
  FROM dental_records dr
  JOIN appointments a ON a.id = dr.appointment_id
  JOIN services s ON s.id = a.service_id
  LEFT JOIN users d ON d.id = dr.dentist_id
  WHERE dr.patient_id = ?
    AND (? = 0 OR a.service_id = ?)
  ORDER BY dr.created_at DESC
");
$stmt->bind_param("iii", $user["id"], $service_id, $service_id);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>My Dental Records - ZNS Dental Clinic</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>

<main class="main">
  <div class="pageHead">
    <h1 class="pageHead__title">My Dental Records</h1>
  </div>

  <section class="card" style="background:#e9f7ff;">
    <div class="table">
    <div class="table__row table__row--head" style="grid-template-columns: 1.2fr .9fr .6fr .7fr;">
      <div>Service / Dentist</div>
      <div>Date/Time</div>
      <div style="text-align:right;">Created</div>
      <div style="text-align:right;">Action</div>
    </div>

      <?php foreach ($records as $r): ?>
      <div class="table__row" style="grid-template-columns: 1.2fr .9fr .6fr .7fr;">
      <div>
        <div style="font-weight:900; color:#0b2f4f;"><?php echo h($r["service"]); ?></div>
        <div style="font-size:12px; font-weight:900; opacity:.75;">
          Dentist: <?php echo h($r["dentist_name"] ?: "—"); ?>
        </div>

        <?php if (!empty($r["diagnosis"])): ?>
          <div style="margin-top:6px; font-weight:800; opacity:.75;">
            <b>Dx:</b> <?php echo h($r["diagnosis"]); ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($r["treatment"])): ?>
          <div style="margin-top:6px; font-weight:800; opacity:.75;">
            <b>Tx:</b> <?php echo h($r["treatment"]); ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="table__muted">
        <?php echo h($r["appointment_date"]); ?> <?php echo h(substr($r["appointment_time"], 0, 5)); ?>
      </div>

      <div class="table__right" style="font-weight:900;">
        <?php echo h(date("Y-m-d", strtotime($r["created_at"]))); ?>
      </div>

      <div class="table__right">
        <a class="btn"
          href="/qm/pages/patient/print_dental_record.php?id=<?php echo (int)$r['id']; ?>"
          target="_blank" rel="noopener">
          View / Print
        </a>
      </div>
    </div>

            <?php if (!empty($r["diagnosis"])): ?>
              <div style="margin-top:6px; font-weight:800; opacity:.75;">
                <b>Dx:</b> <?php echo h($r["diagnosis"]); ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($r["treatment"])): ?>
              <div style="margin-top:6px; font-weight:800; opacity:.75;">
                <b>Tx:</b> <?php echo h($r["treatment"]); ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="table__muted">
            <?php echo h($r["appointment_date"]); ?> <?php echo h(substr($r["appointment_time"],0,5)); ?>
          </div>

          <div class="table__right" style="font-weight:900;">
            <?php echo h(date("Y-m-d", strtotime($r["created_at"]))); ?>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (!$records): ?>
        <div class="table__row">
          <div style="grid-column:1 / -1; font-weight:900; opacity:.75;">
            No dental records yet.
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
</body>
</html>