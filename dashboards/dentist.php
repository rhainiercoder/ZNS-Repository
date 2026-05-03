<?php
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../db.php";

$user = require_role(["dentist"]);
$role = $user["role"];
$active = "dashboard";

function h($v){ return htmlspecialchars((string)$v); }

$today = get_clinic_date('00:00','Asia/Manila');

/* ---------- Today's approved appointments for this dentist ---------- */
$stmt = $conn->prepare("
  SELECT a.id, a.appointment_time, u.name AS patient_name, s.name AS service
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
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ---------- Load dentist weekly availability (1=Mon .. 7=Sun) ---------- */
$weekdayNames = [
  1 => 'Monday',
  2 => 'Tuesday',
  3 => 'Wednesday',
  4 => 'Thursday',
  5 => 'Friday',
  6 => 'Saturday',
  7 => 'Sunday'
];

$avail_map = array_fill(1, 7, []); // default empty arrays
try {
  $s = $conn->prepare("
    SELECT `day`, start_time, end_time
    FROM dentist_availability
    WHERE dentist_id = ?
    ORDER BY `day`, start_time
  ");
  $s->bind_param("i", $user["id"]);
  $s->execute();
  $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
  foreach ($rows as $r) {
    $d = (int)$r['day'];
    if ($d >= 1 && $d <= 7) {
      $avail_map[$d][] = $r;
    }
  }
} catch (Exception $e) {
  // table may not exist or error -> leave avail_map empty
}

/* Helper to format a time like "13:00:00" => "1:00 PM" */
function fmt_time($t) {
  if (!$t) return '';
  return date("g:i A", strtotime($t));
}

/* Build a display string for each weekday */
$display_by_day = [];
for ($d = 1; $d <= 7; $d++) {
  $slots = $avail_map[$d] ?? [];
  if (!$slots) {
    $display_by_day[$d] = null; // closed
  } else {
    $parts = [];
    foreach ($slots as $s) {
      $parts[] = fmt_time($s['start_time']) . ' – ' . fmt_time($s['end_time']);
    }
    $display_by_day[$d] = implode(', ', $parts);
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Dentist Dashboard</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
  <style>
    /* local tweaks to match screenshot layout */
    .hours{ background:#fff; border-radius:12px; padding:8px; }
    .hours__row{ display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 16px; border-top:1px solid rgba(11,31,42,.06); }
    .hours__row:first-child{ border-top:0; }
    .hours__left{ display:flex; gap:12px; align-items:center; font-weight:900; color:#0b2f4f; }
    .hours__right{ font-weight:900; color:#0b2f4f; }
    .hours__icon{ width:34px; height:34px; border-radius:12px; background:var(--accent-light); display:flex; align-items:center; justify-content:center; }
    .small-muted{ font-weight:800; opacity:.8; font-size:13px; }
    @media (max-width: 920px){
      .hours__row{ flex-direction:row; }
    }
  </style>
</head>
<body>
  <?php include __DIR__ . "/../partials/sidebar.php"; ?>

  <main class="main">
    <div class="pageHead">
      <h1 class="pageHead__title">
        Welcome to Your Dashboard<br />
        Dr. <?php echo h($user["name"] ?? ""); ?>!
      </h1>

      <div class="profileChip" title="Profile">
        <div class="profileChip__avatar"></div>
        <div class="profileChip__chev">▾</div>
      </div>
    </div>

    <!-- Your Operating Hours (like screenshot) -->
    <section class="card">
      <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px;">
        <h2 class="sectionTitle" style="margin:0;">Your Operating Hours</h2>
        <a class="btn" href="/qm/pages/dentist/settings.php" style="background:#e9f7ff; color:#0b2f4f;">Edit hours</a>
      </div>

      <div class="hours">
        <?php for ($d = 1; $d <= 7; $d++): ?>
          <div class="hours__row" aria-label="<?php echo h($weekdayNames[$d]); ?>">
            <div class="hours__left">
              <div class="hours__icon">⏰</div>
              <div><?php echo h($weekdayNames[$d]); ?></div>
            </div>
            <div class="hours__right">
              <?php if ($display_by_day[$d] === null): ?>
                <span class="small-muted">Day-Off</span>
              <?php else: ?>
                <?php echo h($display_by_day[$d]); ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endfor; ?>
      </div>
    </section>

    <!-- Today's Patients -->
    <section class="card dentistSection" style="margin-top:12px;">
      <h2 class="sectionTitle">Today's Patients! (<?php echo h($today); ?>)</h2>

      <div class="table">
        <div class="table__row table__row--head" style="grid-template-columns: 1.2fr .6fr 1fr .7fr;">
          <div>Patient</div>
          <div>Time</div>
          <div style="text-align:right;">Service</div>
          <div style="text-align:right;">Action</div>
        </div>

        <?php foreach ($appointments as $r): ?>
          <div class="table__row" style="grid-template-columns: 1.2fr .6fr 1fr .7fr;">
            <div class="patientCell">
              <div class="patientCell__icon">
                <img src="/qm/assets/img/teeth_icon.png" alt=""
                    style="width:22px;height:22px;object-fit:contain;display:block;">
              </div>
              <div>
                <div class="patientCell__name"><?php echo h($r["patient_name"]); ?></div>
                <div class="patientCell__sub">Patient</div>
              </div>
            </div>

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

        <?php if (!$appointments): ?>
          <div class="table__row">
            <div style="grid-column:1 / -1; font-weight:900; opacity:.75;">
              No approved appointments for today.
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>

  </main>
</body>
</html>