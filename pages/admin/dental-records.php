<?php
require_once __DIR__ . "/../../auth.php";
$user = require_role(["admin"]);
$role = $user["role"];
$active = "dental-records";

require_once __DIR__ . "/../../db.php";
function h($v){ return htmlspecialchars((string)$v); }

$q = trim($_GET["q"] ?? "");

// Optional search by patient/dentist/service
if ($q !== "") {
  $like = "%".$q."%";
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
      p.name AS patient_name,
      d.name AS dentist_name
    FROM dental_records dr
    JOIN appointments a ON a.id = dr.appointment_id
    JOIN services s ON s.id = a.service_id
    JOIN users p ON p.id = dr.patient_id
    LEFT JOIN users d ON d.id = dr.dentist_id
    WHERE p.name LIKE ? OR d.name LIKE ? OR s.name LIKE ?
    ORDER BY dr.created_at DESC
  ");
  $stmt->bind_param("sss", $like, $like, $like);
} else {
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
      p.name AS patient_name,
      d.name AS dentist_name
    FROM dental_records dr
    JOIN appointments a ON a.id = dr.appointment_id
    JOIN services s ON s.id = a.service_id
    JOIN users p ON p.id = dr.patient_id
    LEFT JOIN users d ON d.id = dr.dentist_id
    ORDER BY dr.created_at DESC
  ");
}

$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Dental Records - Admin</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>

<main class="main">
  <div class="pageHead">
    <h1 class="pageHead__title">Dental Records</h1>

    <form method="get" style="display:flex; gap:10px; align-items:center;">
      <input
        name="q"
        value="<?php echo h($q); ?>"
        placeholder="Search patient, dentist, service..."
        style="padding:10px 12px; border-radius:12px; border:1px solid rgba(11,31,42,.15); width:min(360px, 48vw); font-weight:800;"
      />
      <button class="btn btn--dark" type="submit">Search</button>
    </form>
  </div>

  <section class="card" style="background:#e9f7ff;">
    <div class="table">
      <div class="table__row table__row--head" style="grid-template-columns: 1.25fr .9fr .9fr .6fr;">
        <div>Patient / Service</div>
        <div>Dentist</div>
        <div>Date/Time</div>
        <div style="text-align:right;">Created</div>
      </div>

      <?php foreach ($records as $r): ?>
        <div class="table__row" style="grid-template-columns: 1.25fr .9fr .9fr .6fr;">
          <div>
            <div style="font-weight:900; color:#0b2f4f;"><?php echo h($r["patient_name"]); ?></div>
            <div style="font-size:12px; font-weight:900; opacity:.75;">
              <?php echo h($r["service"]); ?>
              <?php if (!empty($r["diagnosis"])): ?>
                • Dx: <?php echo h($r["diagnosis"]); ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="table__muted"><?php echo h($r["dentist_name"] ?: "—"); ?></div>

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
            No dental records found.
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
</body>
</html>