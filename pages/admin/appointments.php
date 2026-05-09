<?php
require_once __DIR__ . "/../auth.php";
$user = require_role(["admin"]);
$role = $user["role"];
$active = "appointments";

require_once __DIR__ . "/../db.php";
function h($v){ return htmlspecialchars((string)$v); }

$patient_id = (int)($_GET['patient_id'] ?? 0);

// Load dentists (users with role = dentist)
$dentists = $conn->query("
  SELECT id, name, email
  FROM users
  WHERE role = 'dentist'
  ORDER BY name ASC
")->fetch_all(MYSQLI_ASSOC);

// Build map by id for quick lookup
$dentistById = [];
foreach ($dentists as $d) $dentistById[(int)$d["id"]] = $d;

// Load dentist availability (if table exists). Map: dentist_id => [day1, day2...]
// If the table is missing or empty we leave $availabilityMap empty which will be treated as "no filtering".
$availabilityMap = [];
try {
  $res = $conn->query("SELECT dentist_id, `day` FROM dentist_availability");
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $did = (int)$r['dentist_id'];
      $day = (int)$r['day'];
      if ($did && $day >= 1 && $day <= 7) {
        $availabilityMap[$did][] = $day;
      }
    }
  }
} catch (Exception $e) {
  // ignore — table might not exist on older installs
}

// Handle approve/decline
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $id = (int)($_POST["id"] ?? 0);
  $action = $_POST["action"] ?? "";

  if ($id > 0 && in_array($action, ["approve","decline"], true)) {
    if ($action === "approve") {
      $dentist_id = (int)($_POST["dentist_id"] ?? 0);
      if ($dentist_id <= 0) {
        // simple fail-safe: redirect with no change
        $redirect = "/pages/admin/appointments.php";
          if (!empty($_POST["patient_id"])) {
            $redirect .= "?patient_id=" . (int)$_POST["patient_id"];
          }
          header("Location: " . $redirect);
          exit;
      }

      $stmt = $conn->prepare("UPDATE appointments SET status='approved', dentist_id=? WHERE id=?");
      $stmt->bind_param("ii", $dentist_id, $id);
      $stmt->execute();
    } else {
      $reason = trim($_POST["decline_reason"] ?? "");
    if ($reason === "") {
      header("Location: /pages/admin/appointments.php?err=decline_reason_required");
      exit;
    }
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE appointments SET status='declined', decline_reason=?, declined_at=? WHERE id=?");
    $stmt->bind_param("ssi", $reason, $now, $id);
    $stmt->execute();
    }
  }

  header("Location: /pages/admin/appointments.php");
  exit;
}

// List appointments
$patientRows = [];
$rows = [];

if ($patient_id === 0) {
  // COMPILED: list patients with appointment counts (pending+approved)
  $res = $conn->query("
    SELECT
      u.id AS patient_id,
      u.name AS patient_name,
      u.email AS patient_email,
      u.phone AS patient_phone,
      COUNT(a.id) AS appt_count,
      MAX(CONCAT(a.appointment_date,' ',a.appointment_time)) AS last_appt
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    WHERE a.status IN ('pending','approved')
    GROUP BY u.id
    ORDER BY last_appt DESC, u.name ASC
  ");
  $patientRows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
} else {
  // PATIENT VIEW: list appointments for one patient
  $stmt = $conn->prepare("
    SELECT
      a.id,
      a.appointment_date,
      a.appointment_time,
      a.status,
      a.dentist_id,
      u.name AS patient_name,
      u.email AS patient_email,
      s.name AS service
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    JOIN services s ON s.id = a.service_id
    WHERE a.patient_id = ?
    ORDER BY
      (a.status='pending') DESC,
      a.appointment_date ASC,
      a.appointment_time ASC
  ");
  $stmt->bind_param("i", $patient_id);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$err = $_GET["err"] ?? "";
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Admin - Appointments</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../partials/sidebar.php"; ?>

<main class="main">
<div class="pageHead" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
  <h1 class="pageHead__title">
    <?php echo $patient_id ? "Appointments (Patient)" : "Appointments (Patients)"; ?>
  </h1>

  <?php if ($patient_id): ?>
    <a class="btn light" href="/pages/admin/appointments.php">Back to Patients</a>
  <?php endif; ?>
</div>

  <?php if ($err === "choose_dentist"): ?>
    <div class="card" style="background:#ffe9e9; margin-bottom:12px; font-weight:800;">
      Please choose a dentist before approving.
    </div>
  <?php endif; ?>

  <?php if ($err === "decline_reason_required"): ?>
  <div class="card" style="background:#ffe9e9; margin-bottom:12px; font-weight:800;">
    Please provide a decline reason.
  </div>
  <?php endif; ?>

  <section class="card" style="background:#e9f7ff;">
  <?php if ($patient_id === 0): ?>

    <h2 class="sectionTitle">Manage Appointments (Patients)</h2>

    <div class="table">
      <div class="table__row table__row--head" style="grid-template-columns: 1.1fr .9fr .6fr .6fr;">
        <div>Patient</div>
        <div>Contact</div>
        <div>Appointments</div>
        <div style="text-align:right;">Action</div>
      </div>

      <?php foreach ($patientRows as $p): ?>
        <div class="table__row" style="grid-template-columns: 1.1fr .9fr .6fr .6fr;">
          <div>
            <div style="font-weight:900; color:#0b2f4f;"><?php echo h($p["patient_name"]); ?></div>
          </div>

          <div style="font-size:12px; font-weight:800; opacity:.75;">
            <div><?php echo h($p["patient_phone"] ?? "—"); ?></div>
            <div><?php echo h($p["patient_email"] ?? "—"); ?></div>
          </div>

          <div>
            <div style="font-weight:900;"><?php echo (int)$p["appt_count"]; ?></div>
            <div style="font-size:12px; font-weight:800; opacity:.75;">
              <?php echo !empty($p["last_appt"]) ? h(substr($p["last_appt"], 0, 10)) : "—"; ?>
            </div>
          </div>

          <div style="text-align:right;">
            <a class="btn light" href="/pages/admin/appointments.php?patient_id=<?php echo (int)$p["patient_id"]; ?>">
              View Appointments
            </a>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (!$patientRows): ?>
        <div class="table__row">
          <div style="grid-column:1 / -1; font-weight:800; opacity:.7;">No appointments yet.</div>
        </div>
      <?php endif; ?>
    </div>

  <?php else: ?>

    <h2 class="sectionTitle">Manage Appointments</h2>

    <div class="table">
      <div class="table__row table__row--head" style="grid-template-columns: 1.4fr .9fr 1fr;">
        <div>Patient / Service</div>
        <div>Date/Time</div>
        <div>Action / Status</div>
      </div>

      <?php foreach ($rows as $r): ?>
        <div class="table__row" style="grid-template-columns: 1.4fr .9fr 1fr;">
          <div>
            <div style="font-weight:900; color:#0b2f4f;"><?php echo h($r["patient_name"]); ?></div>
            <div style="font-size:12px; font-weight:800; opacity:.7;"><?php echo h($r["service"]); ?></div>
          </div>

          <div class="table__muted">
            <?php echo h($r["appointment_date"]); ?> <?php echo h(substr($r["appointment_time"],0,5)); ?>
          </div>

          <div class="table__right">
            <?php if ($r["status"] === "pending"): ?>
              <!-- YOUR ORIGINAL APPROVE/DECLINE FORM (UNCHANGED) -->
              <form method="post" style="display:flex; gap:8px; justify-content:flex-end; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">

                <?php
                  $weekday = (int)date('N', strtotime($r['appointment_date']));

                  $available = [];
                  $unavailable = [];
                  foreach ($dentists as $d) {
                    $did = (int)$d['id'];
                    if (empty($availabilityMap)) {
                      $available[] = $d;
                    } else {
                      $days = $availabilityMap[$did] ?? [];
                      if (in_array($weekday, $days, true)) {
                        $available[] = $d;
                      } else {
                        $unavailable[] = $d;
                      }
                    }
                  }
                ?>

                <select name="dentist_id"
                  style="padding:9px 10px; border-radius:12px; border:1px solid rgba(11,31,42,.15); font-weight:800;">
                  <option value="">Choose dentist</option>

                  <?php if ($available): ?>
                    <optgroup label="Available">
                      <?php foreach ($available as $d): ?>
                        <option value="<?php echo (int)$d["id"]; ?>">
                          <?php echo h($d["name"]); ?>
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endif; ?>

                  <?php if (!empty($availabilityMap)): ?>
                    <?php if ($unavailable): ?>
                      <optgroup label="Off duty">
                        <?php foreach ($unavailable as $d): ?>
                          <option value="<?php echo (int)$d["id"]; ?>">
                            <?php echo h($d["name"]); ?> (off today)
                          </option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endif; ?>
                  <?php endif; ?>
                </select>

                <button class="btn btn--dark" name="action" value="approve" type="submit">Approve</button>
                <input type="hidden" name="decline_reason" value="">
                <button
                  class="btn"
                  style="background:#e64545;color:#fff;"
                  name="action"
                  value="decline"
                  type="submit"
                  onclick="
                    var r = prompt('Reason for declining this appointment?');
                    if (!r || !r.trim()) { alert('Decline reason is required.'); return false; }
                    this.form.querySelector('input[name=decline_reason]').value = r.trim();
                    return true;
                  "
                >Decline</button>
              </form>
            <?php else: ?>
              <div style="font-weight:900;"><?php echo h($r["status"]); ?></div>
              <?php if (!empty($r["dentist_id"]) && isset($dentistById[(int)$r["dentist_id"]])): ?>
                <div style="font-size:12px; font-weight:800; opacity:.7;">
                  Dentist: <?php echo h($dentistById[(int)$r["dentist_id"]]["name"]); ?>
                </div>
              <?php endif; ?>
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

  <?php endif; ?>
</section>
</main>
</body>
</html>