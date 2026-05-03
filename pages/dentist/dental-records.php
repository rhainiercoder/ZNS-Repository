<?php
require_once __DIR__ . "/../../auth.php";
$user = require_role(["dentist"]);
$role = $user["role"];
$active = "dental-records";

require_once __DIR__ . "/../../db.php";
function h($v){ return htmlspecialchars((string)$v); }

$errors = [];
$success = "";

// If you clicked "Add Record" from today/dashboard, appointment_id will be present
$appointment_id = (int)($_GET["appointment_id"] ?? 0);
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

    if ($diagnosis === "" && $treatment === "" && $prescription === "" && $notes === "") {
      $errors[] = "Please fill at least one field.";
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
            INSERT INTO dental_records (appointment_id, dentist_id, patient_id, diagnosis, treatment, prescription, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
          ");
          $stmt->bind_param(
            "iiissss",
            $appointment_id,
            $user["id"],
            $check["patient_id"],
            $diagnosis,
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

          header("Location: /qm/pages/dentist/dental-records.php?saved=1");
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

// Load dentist's records list
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
    u.name AS patient_name,
    s.name AS service
  FROM dental_records dr
  JOIN appointments a ON a.id = dr.appointment_id
  JOIN users u ON u.id = dr.patient_id
  JOIN services s ON s.id = a.service_id
  WHERE dr.dentist_id = ?
  ORDER BY dr.created_at DESC
");
$stmt->bind_param("i", $user["id"]);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Dentist - Dental Records</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>

<main class="main">
  <div class="pageHead">
    <h1 class="pageHead__title">Dental Records</h1>
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
    <!-- Add record panel -->
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
          <a class="btn" style="background:#e9f7ff; color:#0b2f4f;" href="/qm/dashboards/dentist.php">Back to Dashboard</a>
          <button class="btn btn--dark" type="submit">Save Record</button>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <!-- List records -->
  <section class="card" style="background:#e9f7ff;">
    <h2 class="sectionTitle">My Records</h2>

    <div class="table">
      <div class="table__row table__row--head" style="grid-template-columns: 1.2fr .9fr .9fr;">
        <div>Patient / Service</div>
        <div>Date/Time</div>
        <div style="text-align:right;">Created</div>
      </div>

      <?php foreach ($records as $r): ?>
        <div class="table__row" style="grid-template-columns: 1.2fr .9fr .9fr;">
          <div>
            <div style="font-weight:900; color:#0b2f4f;"><?php echo h($r["patient_name"]); ?></div>
            <div style="font-size:12px; font-weight:900; opacity:.75;"><?php echo h($r["service"]); ?></div>

            <?php if (!empty($r["diagnosis"])): ?>
              <div style="margin-top:6px; font-weight:800; opacity:.75;">
                <b>Dx:</b> <?php echo h($r["diagnosis"]); ?>
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