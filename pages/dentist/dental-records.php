<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["dentist"]);
$role = $user["role"];
$active = "records";

function h($v){ return htmlspecialchars((string)$v); }

$errors = [];
$success = "";

// If you clicked "Add Record" from today/dashboard, appointment_id will be present
$appointment_id = (int)($_GET["appointment_id"] ?? 0);
$patient_id = (int)($_GET["patient_id"] ?? 0);
$appt = null;

if ($appointment_id > 0) {
  // Load appointment details and verify it belongs to this dentist
  $stmt = $conn->prepare("
    SELECT
      a.id,
      a.patient_id,
      a.dentist_id,
      a.appointment_date,
      a.appointment_time,
      a.status,
      u.name AS patient_name,
      s.name AS service
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    JOIN services s ON s.id = a.service_id
    WHERE a.id = ? AND a.dentist_id = ?
    LIMIT 1
  ");
  $stmt->bind_param("ii", $appointment_id, $user["id"]);
  $stmt->execute();
  $appt = $stmt->get_result()->fetch_assoc();

  if (!$appt) {
    $errors[] = "Appointment not found or not assigned to you.";
    $appointment_id = 0;
  }
}

// Handle Save Record
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save_record") {
  $appointment_id = (int)($_POST["appointment_id"] ?? 0);

  // Re-load & verify again (security)
  $stmt = $conn->prepare("
    SELECT
      a.id,
      a.patient_id,
      a.dentist_id,
      a.status
    FROM appointments a
    WHERE a.id = ? AND a.dentist_id = ?
    LIMIT 1
  ");
  $stmt->bind_param("ii", $appointment_id, $user["id"]);
  $stmt->execute();
  $check = $stmt->get_result()->fetch_assoc();

  if (!$check) {
    $errors[] = "Invalid appointment.";
  } else {
    // Optional: only allow record creation if appointment is approved
    if ($check["status"] !== "approved") {
      $errors[] = "You can only add a record to an approved appointment.";
    }
  }

  if (!$errors) {
    $diagnosis = trim($_POST["diagnosis"] ?? "");
    $treatment = trim($_POST["treatment"] ?? "");
    $prescription = trim($_POST["prescription"] ?? "");
    $notes = trim($_POST["notes"] ?? "");

    $tooth_no = trim($_POST["tooth_no"] ?? "");

    // sanitize tooth_no to "1,2,14" only
    $valid = [];
    foreach (explode(",", $tooth_no) as $t) {
      $n = (int)trim($t);
      if ($n >= 1 && $n <= 32) $valid[$n] = true;
    }
    $tooth_no = implode(",", array_keys($valid));

    if ($diagnosis === "" && $treatment === "" && $prescription === "" && $notes === "") {
      $errors[] = "Please fill at least one field.";
    }

    // require at least 1 tooth marked (disable this if you don't want it)
    if ($tooth_no === "") {
      $errors[] = "Please mark at least one affected tooth before saving.";
    }

    if (!$errors) {
      // Prevent duplicate record per appointment (keeps flow clean)
      $stmt = $conn->prepare("
        SELECT id FROM dental_records
        WHERE appointment_id = ? AND dentist_id = ?
        LIMIT 1
      ");
      $stmt->bind_param("ii", $appointment_id, $user["id"]);
      $stmt->execute();
      $existing = $stmt->get_result()->fetch_assoc();

      if ($existing) {
        $errors[] = "A dental record for this appointment already exists.";
      } else {
        // Create record + set appointment completed atomically
        $conn->begin_transaction();
        try {
          $stmt = $conn->prepare("
            INSERT INTO dental_records (appointment_id, dentist_id, patient_id, diagnosis, tooth_no, treatment, prescription, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
          ");
          $stmt->bind_param(
            "iiisssss",
            $appointment_id,
            $user["id"],
            $check["patient_id"],
            $diagnosis,
            $tooth_no,
            $treatment,
            $prescription,
            $notes
          );
          $stmt->execute();

          // Mark appointment as completed so it disappears from Today's Patient list
          $upd = $conn->prepare("
            UPDATE appointments
            SET status = 'completed'
            WHERE id = ? AND dentist_id = ?
          ");
          $upd->bind_param("ii", $appointment_id, $user["id"]);
          $upd->execute();

          $conn->commit();

          header("Location: pages/dentist/dental-records.php?saved=1");
          exit;
        } catch (Throwable $e) {
          $conn->rollback();
          $errors[] = "Failed to save record. Please try again.";
        }
      }
    }
  }
}

if (($_GET["saved"] ?? "") === "1") {
  $success = "Dental record saved and appointment marked as completed.";
}

$patient = null;
$patientGroups = [];
$records = [];

if ($patient_id > 0) {
  $stmt = $conn->prepare("
    SELECT id, name, phone, email
    FROM users
    WHERE id = ?
      AND role = 'patient'
    LIMIT 1
  ");
  $stmt->bind_param("i", $patient_id);
  $stmt->execute();
  $patient = $stmt->get_result()->fetch_assoc();

  $stmt = $conn->prepare("
    SELECT
      dr.id,
      dr.created_at,
      dr.diagnosis,
      dr.tooth_no,
      dr.treatment,
      dr.prescription,
      dr.notes,
      a.appointment_date,
      a.appointment_time,
      s.name AS service
    FROM dental_records dr
    JOIN appointments a ON a.id = dr.appointment_id
    JOIN services s ON s.id = a.service_id
    WHERE dr.dentist_id = ?
      AND dr.patient_id = ?
    ORDER BY dr.created_at DESC
  ");
  $stmt->bind_param("ii", $user["id"], $patient_id);
  $stmt->execute();
  $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
  $stmt = $conn->prepare("
    SELECT
      u.id AS patient_id,
      u.name,
      u.phone,
      u.email,
      COUNT(dr.id) AS records_count,
      MAX(dr.created_at) AS last_record
    FROM dental_records dr
    JOIN users u ON u.id = dr.patient_id
    WHERE dr.dentist_id = ?
    GROUP BY u.id, u.name, u.phone, u.email
    ORDER BY last_record DESC, u.name ASC
  ");
  $stmt->bind_param("i", $user["id"]);
  $stmt->execute();
  $patientGroups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Dentist - Dental Records</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    /* ensure visible teal marking even if style.css hasn't been updated yet */
    .toothChart{ background:#fff; border:1px solid rgba(0,0,0,.1); border-radius:12px; padding:12px; }
    .toothChart__row{ display:grid; grid-template-columns:repeat(16, 1fr); gap:8px; }
    .toothChart__label{ margin:10px 0 6px; font-weight:900; color:#0b2f4f; }
    .tooth{
      height:38px; border-radius:10px; border:1px solid rgba(0,0,0,.2);
      background:#fff; font-weight:1000; cursor:pointer;
    }
    .tooth--marked{ background:#0ea5a4; color:#fff; border-color:#0ea5a4; }
  </style>
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>

<main class="main">
  <div class="pageHead">
    <?php if ($patient_id > 0): ?>
      <div>
        <h1 class="pageHead__title">Records for <?php echo h($patient['name'] ?? 'Unknown Patient'); ?></h1>
        <div style="color:#6f7b86; font-weight:800;">
          Phone: <?php echo h($patient['phone'] ?? '—'); ?> / Email: <?php echo h($patient['email'] ?? '—'); ?>
        </div>
      </div>
      <a class="btn light" href="/pages/dentist/dental-records.php">Back to Patients</a>
    <?php else: ?>
      <div>
        <h1 class="pageHead__title">Dental Records (Patients)</h1>
        <p class="pageHead__lead">Click "View Records" to see a compiled record list for each patient.</p>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($success): ?>
    <div class="card" style="background:#e9fff0; margin-bottom:12px; font-weight:900;">
      <?php echo h($success); ?>
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="card" style="background:#ffe9e9; margin-bottom:12px; font-weight:900;">
      <?php foreach ($errors as $e) echo "<div>".h($e)."</div>"; ?>
    </div>
  <?php endif; ?>

  <?php if ($appt): ?>
    <section class="card" style="background:#e9f7ff; margin-bottom:16px;">
      <h2 class="sectionTitle">Add Record</h2>

      <div style="font-weight:900; color:#0b2f4f; margin-bottom:6px;">
        Patient: <?php echo h($appt["patient_name"]); ?>
      </div>
      <div style="font-weight:800; opacity:.75; margin-bottom:14px;">
        Service: <?php echo h($appt["service"]); ?> •
        Date/Time: <?php echo h($appt["appointment_date"]); ?> <?php echo h(substr($appt["appointment_time"],0,5)); ?> •
        Status: <?php echo h($appt["status"]); ?>
      </div>

      <form method="post" style="display:grid; gap:12px; max-width:760px;">
        <input type="hidden" name="tooth_no" id="tooth_no" value="">

        <div class="toothChart" style="margin:12px 0;">
          <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <div style="font-weight:1000; color:#0b2f4f;">Tooth Chart (Odontogram)</div>
            <div id="toothCount" style="font-weight:1000;">Marked (0)</div>
          </div>

          <div class="toothChart__label">Upper (1–16)</div>
          <div class="toothChart__row" id="teethUpper"></div>

          <div class="toothChart__label" style="margin-top:10px;">Lower (32–17)</div>
          <div class="toothChart__row" id="teethLower"></div>

          <div class="toothChart__hint" style="margin-top:10px; font-size:13px; font-weight:800; opacity:.7;">
            Click teeth to mark/unmark. Marked teeth will appear in patient/admin printables.
          </div>
        </div>

        <input type="hidden" name="action" value="save_record">
        <input type="hidden" name="appointment_id" value="<?php echo (int)$appt["id"]; ?>">

        <label>
          <div style="font-weight:900; color:#0b2f4f; margin-bottom:6px;">Diagnosis</div>
          <textarea name="diagnosis" rows="3"
            style="width:100%; padding:10px; border-radius:12px; border:1px solid rgba(11,31,42,.15);"></textarea>
        </label>

        <label>
          <div style="font-weight:900; color:#0b2f4f; margin-bottom:6px;">Treatment</div>
          <textarea name="treatment" rows="3"
            style="width:100%; padding:10px; border-radius:12px; border:1px solid rgba(11,31,42,.15);"></textarea>
        </label>

        <label>
          <div style="font-weight:900; color:#0b2f4f; margin-bottom:6px;">Prescription</div>
          <textarea name="prescription" rows="2"
            style="width:100%; padding:10px; border-radius:12px; border:1px solid rgba(11,31,42,.15);"></textarea>
        </label>

        <label>
          <div style="font-weight:900; color:#0b2f4f; margin-bottom:6px;">Notes</div>
          <textarea name="notes" rows="3"
            style="width:100%; padding:10px; border-radius:12px; border:1px solid rgba(11,31,42,.15);"></textarea>
        </label>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
          <a class="btn" style="background:#e9f7ff; color:#0b2f4f;" href="/pages/dentist/dashboards/dentist.php">Back to Dashboard</a>
          <button class="btn btn--dark" type="submit">Save Record</button>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <?php if ($patient_id === 0): ?>
    <section class="card" style="background:#e9f7ff;">
      <h2 class="sectionTitle">Patients With Dental Records</h2>

      <div class="table">
        <div class="table__row table__row--head" style="grid-template-columns: 1fr .7fr .6fr .5fr;">
          <div>Patient</div>
          <div>Contact</div>
          <div>Records</div>
          <div style="text-align:right;">Action</div>
        </div>

        <?php foreach ($patientGroups as $row): ?>
          <div class="table__row" style="grid-template-columns: 1fr .7fr .6fr .5fr;">
            <div style="font-weight:900; color:#0b2f4f;"><?php echo h($row['name']); ?></div>
            <div>
              <div style="font-size:13px; color:#6f7b86;"><?php echo h($row['phone'] ?: '—'); ?></div>
              <div style="font-size:12px; color:#8a8a8a;"><?php echo h($row['email'] ?: '—'); ?></div>
            </div>
            <div>
              <div style="font-weight:900;"><?php echo (int)$row['records_count']; ?></div>
              <div style="font-size:13px; color:#6f7b86;"><?php echo $row['last_record'] ? h(substr($row['last_record'], 0, 10)) : '—'; ?></div>
            </div>
            <div style="text-align:right;">
              <a class="btn light" href="/pages/dentist/dental-records.php?patient_id=<?php echo (int)$row['patient_id']; ?>">View Records</a>
            </div>
          </div>
        <?php endforeach; ?>

        <?php if (!$patientGroups): ?>
          <div class="table__row">
            <div style="grid-column:1 / -1; font-weight:900; opacity:.75;">No dental records yet.</div>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php else: ?>
    <section class="card" style="background:#e9f7ff;">
      <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px;">
        <h2 class="sectionTitle" style="margin:0;">Compiled Records</h2>

        <!-- NOTE: this points to /dentist/ so it won't be Forbidden. Create this page or change back to /admin/ if you prefer. -->
        <a class="btn primary" href="/pages/dentist/print_dental_records.php?patient_id=<?php echo (int)$patient_id; ?>" target="_blank">
          Print All Records
        </a>
      </div>

      <div class="table">
        <div class="table__row table__row--head" style="grid-template-columns: 1.2fr .9fr .9fr;">
          <div>Service / Details</div>
          <div>Date/Time</div>
          <div style="text-align:right;">Created</div>
        </div>

        <?php foreach ($records as $r): ?>
          <div class="table__row" style="grid-template-columns: 1.2fr .9fr .9fr;">
            <div>
              <div style="font-weight:900; color:#0b2f4f;"><?php echo h($r["service"]); ?></div>
              <?php if (!empty($r["tooth_no"])): ?>
                <div style="margin-top:6px; font-weight:800; opacity:.75;"><b>Teeth:</b> <?php echo h($r["tooth_no"]); ?></div>
              <?php endif; ?>
              <?php if (!empty($r["diagnosis"])): ?>
                <div style="margin-top:6px; font-weight:800; opacity:.75;"><b>Dx:</b> <?php echo h($r["diagnosis"]); ?></div>
              <?php endif; ?>
              <?php if (!empty($r["treatment"])): ?>
                <div style="margin-top:6px; font-weight:800; opacity:.75;"><b>Tx:</b> <?php echo h($r["treatment"]); ?></div>
              <?php endif; ?>
            </div>
            <div class="table__muted"><?php echo h($r["appointment_date"]); ?> <?php echo h(substr($r["appointment_time"],0,5)); ?></div>
            <div class="table__right" style="font-weight:900;"><?php echo h(date("Y-m-d", strtotime($r["created_at"]))); ?></div>
          </div>
        <?php endforeach; ?>

        <?php if (!$records): ?>
          <div class="table__row">
            <div style="grid-column:1 / -1; font-weight:900; opacity:.75;">No dental records found for this patient.</div>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
</main>

<script>
(function(){
  const upperWrap = document.getElementById('teethUpper');
  const lowerWrap = document.getElementById('teethLower');
  const input = document.getElementById('tooth_no');
  const countEl = document.getElementById('toothCount');
  if (!upperWrap || !lowerWrap || !input) return;

  const selected = new Set(
    (input.value || '').split(',')
      .map(s => parseInt(s.trim(), 10))
      .filter(n => Number.isFinite(n) && n >= 1 && n <= 32)
  );

  function sync(){
    const arr = Array.from(selected).sort((a,b)=>a-b);
    input.value = arr.join(',');
    if (countEl) countEl.textContent = `Marked (${arr.length})`;
  }

  function renderTooth(num, container){
    const el = document.createElement('button');
    el.type = 'button';
    el.className = 'tooth';
    el.textContent = num;
    if (selected.has(num)) el.classList.add('tooth--marked');

    el.addEventListener('click', () => {
      if (selected.has(num)) selected.delete(num);
      else selected.add(num);
      el.classList.toggle('tooth--marked');
      sync();
    });

    container.appendChild(el);
  }

  for (let i=1;i<=16;i++) renderTooth(i, upperWrap);
  for (let i=32;i>=17;i--) renderTooth(i, lowerWrap);
  sync();
})();
</script>
</body>
</html>