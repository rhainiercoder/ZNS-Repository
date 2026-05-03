<?php
require_once __DIR__ . "/../../auth.php";
$user = require_role(["patient"]);
$role = $user["role"];
$active = "appointments";

require_once __DIR__ . "/../../db.php";
function h($v){ return htmlspecialchars((string)$v); }

$errors = [];
$success = "";

// Load services
$services = $conn->query("
  SELECT id, name
  FROM services
  WHERE is_active = 1
  ORDER BY name ASC
")->fetch_all(MYSQLI_ASSOC);

// Handle cancel
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "cancel") {
  $appointment_id = (int)($_POST["appointment_id"] ?? 0);

  if ($appointment_id <= 0) {
    $errors[] = "Invalid appointment.";
  } else {
    // Only allow cancelling your own appointments; only if pending/approved
    $stmt = $conn->prepare("
      UPDATE appointments
      SET status = 'cancelled'
      WHERE id = ?
        AND patient_id = ?
        AND status IN ('pending','approved')
    ");
    $stmt->bind_param("ii", $appointment_id, $user["id"]);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
      $success = "Appointment cancelled.";
    } else {
      $errors[] = "Unable to cancel this appointment (it may already be processed).";
    }
  }
}

// Handle new request
if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($_POST["action"])) {
  $service_id = (int)($_POST["service_id"] ?? 0);
  $date       = trim($_POST["appointment_date"] ?? "");
  $time       = trim($_POST["appointment_time"] ?? "");
  $note       = trim($_POST["note"] ?? "");

  if ($service_id <= 0) $errors[] = "Please choose a service.";
  if ($date === "")     $errors[] = "Date is required.";
  if ($time === "")     $errors[] = "Time is required.";

  if (!$errors) {
  date_default_timezone_set('Asia/Manila');

  $bufferMinutes = 15;
  $today = date('Y-m-d');

  if ($date < $today) {
    $errors[] = "You cannot book an appointment in the past.";
  } else {
    $apptTs = strtotime($date . ' ' . $time);
    $minTs = time() + ($bufferMinutes * 60);

    if ($apptTs === false) {
      $errors[] = "Invalid appointment date/time.";
    } elseif ($date === $today && $apptTs <= $minTs) {
      $errors[] = "For today, please choose a time at least {$bufferMinutes} minutes from now.";
    }
  }
}

  // Only INSERT if there are still no errors
  if (!$errors) {
    $stmt = $conn->prepare("
      INSERT INTO appointments (patient_id, service_id, appointment_date, appointment_time, status, note)
      VALUES (?, ?, ?, ?, 'pending', ?)
    ");
    $stmt->bind_param("iisss", $user["id"], $service_id, $date, $time, $note);
    $stmt->execute();
    $success = "Appointment request submitted (pending approval).";
  }
}


// Load history
$stmt = $conn->prepare("
  SELECT
    a.id,
    s.name AS service,
    a.appointment_date,
    a.appointment_time,
    a.status,
    a.note
  FROM appointments a
  JOIN services s ON s.id = a.service_id
  WHERE a.patient_id = ?
  ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->bind_param("i", $user["id"]);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Patient - My Appointments</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>

<main class="main">
  <div class="pageHead">
    <h1 class="pageHead__title">My Appointments</h1>
  </div>

  <?php if ($success): ?>
    <div class="card" style="background:#e9f7ff; margin-bottom:12px; font-weight:800;">
      <?php echo h($success); ?>
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="card" style="background:#ffe9e9; margin-bottom:12px; font-weight:800;">
      <?php foreach ($errors as $e) echo "<div>".h($e)."</div>"; ?>
    </div>
  <?php endif; ?>

  <section class="card" style="background:#e9f7ff; margin-bottom:18px;">
    <h2 class="sectionTitle">Request Appointment</h2>

    <form method="post" style="display:grid; gap:10px; max-width:540px;">
      <label>
        <div style="font-weight:900; color:#0b2f4f; margin-bottom:6px;">Service</div>
        <select name="service_id" required
          style="width:100%; padding:10px; border-radius:12px; border:1px solid rgba(11,31,42,.15);">
          <option value="">-- Choose a service --</option>
          <?php foreach ($services as $s): ?>
            <option value="<?php echo (int)$s["id"]; ?>"><?php echo h($s["name"]); ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
        <label>
          <div style="font-weight:900; color:#0b2f4f; margin-bottom:6px;">Date</div>
          <input type="date" id="apptDate" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>"
            style="width:100%; padding:10px; border-radius:12px; border:1px solid rgba(11,31,42,.15);">
        </label>

        <label>
          <div style="font-weight:900; color:#0b2f4f; margin-bottom:6px;">Time</div>
          <input type="time" id="apptTime" name="appointment_time" required
            style="width:100%; padding:10px; border-radius:12px; border:1px solid rgba(11,31,42,.15);">
        </label>
      </div>

      <label>
        <div style="font-weight:900; color:#0b2f4f; margin-bottom:6px;">Note (optional)</div>
        <input name="note" placeholder="Optional note"
          style="width:100%; padding:10px; border-radius:12px; border:1px solid rgba(11,31,42,.15);">
      </label>

      <div style="display:flex; justify-content:flex-end; margin-top:6px;">
        <button class="btn btn--dark" type="submit">Submit</button>
      </div>
    </form>
  </section>

  <section class="card" style="background:#e9f7ff;">
    <h2 class="sectionTitle">History</h2>

    <div class="table">
      <div class="table__row table__row--head" style="grid-template-columns: 1.2fr .9fr .6fr .7fr;">
        <div>Service</div>
        <div>Date/Time</div>
        <div>Status</div>
        <div style="text-align:right;">Action</div>
      </div>

      <?php foreach ($rows as $r): ?>
        <div class="table__row" style="grid-template-columns: 1.2fr .9fr .6fr .7fr;">
          <div style="font-weight:900; color:#0b2f4f;"><?php echo h($r["service"]); ?></div>
          <div class="table__muted"><?php echo h($r["appointment_date"]); ?> <?php echo h(substr($r["appointment_time"],0,5)); ?></div>
          <div style="font-weight:900;"><?php echo h($r["status"]); ?></div>

          <div style="text-align:right;">
            <?php if (in_array($r["status"], ["pending","approved"], true)): ?>
              <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="appointment_id" value="<?php echo (int)$r["id"]; ?>">
                <button type="submit" class="btn" style="background:#e64545;color:#fff;"
                  onclick="return confirm('Cancel this appointment?');">
                  Cancel
                </button>
              </form>
            <?php else: ?>
              <span style="opacity:.6; font-weight:800;">—</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <div class="table__row">
          <div style="grid-column:1 / -1; font-weight:800; opacity:.7;">No appointments yet.</div>
        </div>
      <?php endif; ?>
    </div>
  </section>
  <script>
(function () {
  const d = document.getElementById('apptDate');
  const t = document.getElementById('apptTime');
  if (!d || !t) return;

  const BUFFER_MINUTES = 15;

  function pad(n){ return String(n).padStart(2,'0'); }

  function todayStr() {
    const x = new Date();
    return `${x.getFullYear()}-${pad(x.getMonth()+1)}-${pad(x.getDate())}`;
  }

  function nowPlusBufferHM() {
    const x = new Date();
    x.setMinutes(x.getMinutes() + BUFFER_MINUTES);
    return `${pad(x.getHours())}:${pad(x.getMinutes())}`;
  }

  function updateMinTime() {
    if (d.value === todayStr()) {
      t.min = nowPlusBufferHM();
    } else {
      t.min = "";
    }

    if (t.value && t.min && t.value < t.min) {
      t.value = "";
    }
  }

  d.addEventListener('change', updateMinTime);
  updateMinTime();
})();
</script>
</main>
</body>
</html>