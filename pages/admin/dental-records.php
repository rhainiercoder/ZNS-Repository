<?php
// pages/admin/dental-records.php
require_once __DIR__ . "/../../auth.php";
$user = require_role(["admin", "staff", "dentist"]);
$role = $user["role"];

require_once __DIR__ . "/../../db.php";

function h($v){ return htmlspecialchars((string)$v); }

$patient_id = (int)($_GET['patient_id'] ?? 0);

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
    <?php if ($patient_id === 0): ?>
      <h1 class="pageHead__title">Dental Records (Patients)</h1>
      <p class="pageHead__lead">Click "View Records" to see full dental records for a patient.</p>
    <?php else: ?>
      <?php
        // fetch patient name for header
        $s = $conn->prepare("SELECT id, name, phone, email FROM users WHERE id = ? LIMIT 1");
        $s->bind_param("i", $patient_id);
        $s->execute();
        $patient = $s->get_result()->fetch_assoc();
      ?>
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
          <h1 class="pageHead__title">Records for <?php echo h($patient['name'] ?? 'Unknown'); ?></h1>
          <div style="color:#6f7b86;">Phone: <?php echo h($patient['phone'] ?? '—'); ?> — Email: <?php echo h($patient['email'] ?? '—'); ?></div>
        </div>
        <div>
          <a class="btn" href="/qm/pages/admin/dental-records.php">Back to Patients</a>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($patient_id === 0): ?>
    <!-- LIST OF PATIENTS WITH RECORD COUNTS -->
    <section class="card">
      <div class="table">
        <div class="table__row table__row--head" style="grid-template-columns: 1fr .6fr .6fr .5fr;">
          <div>Patient</div>
          <div>Contact</div>
          <div>Records</div>
          <div style="text-align:right;">Action</div>
        </div>

        <?php
        // Query: patients who have dental_records via appointments
        $q = "
          SELECT u.id AS patient_id, u.name, u.phone, u.email,
                 COUNT(dr.id) AS records_count,
                 MAX(dr.created_at) AS last_record
          FROM dental_records dr
          JOIN appointments a ON a.id = dr.appointment_id
          JOIN users u ON u.id = a.patient_id
          GROUP BY u.id
          ORDER BY last_record DESC, u.name ASC
        ";
        $res = $conn->query($q);
        while ($row = $res->fetch_assoc()):
        ?>
          <div class="table__row" style="grid-template-columns: 1fr .6fr .6fr .5fr;">
            <div>
              <div style="font-weight:900; color:#0b2f4f;"><?php echo h($row['name']); ?></div>
            </div>
            <div>
              <div style="font-size:13px; color:#6f7b86;"><?php echo h($row['phone'] ?: '—'); ?></div>
              <div style="font-size:12px; color:#8a8a8a;"><?php echo h($row['email'] ?: '—'); ?></div>
            </div>
            <div>
              <div style="font-weight:800;"><?php echo (int)$row['records_count']; ?></div>
              <div style="font-size:13px; color:#6f7b86;"><?php echo $row['last_record'] ? h(substr($row['last_record'],0,10)) : '—'; ?></div>
            </div>
            <div style="text-align:right;">
              <a class="btn light" href="/qm/pages/admin/dental-records.php?patient_id=<?php echo (int)$row['patient_id']; ?>">View Records</a>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    </section>

  <?php else: ?>
    <!-- SHOW ALL RECORDS FOR A SPECIFIC PATIENT -->
    <section class="card">
      <?php
      // Fetch all dental_records for this patient (joining appointments for service/dentist info)
      $stmt = $conn->prepare("
        SELECT dr.id AS record_id,
               dr.created_at AS created_at,
               dr.diagnosis, dr.treatment, dr.prescription, dr.notes,
               a.appointment_date, a.appointment_time,
               s.name AS service,
               d.name AS dentist_name
        FROM dental_records dr
        JOIN appointments a ON a.id = dr.appointment_id
        JOIN services s ON s.id = a.service_id
        LEFT JOIN users d ON d.id = dr.dentist_id
        WHERE a.patient_id = ?
        ORDER BY dr.created_at DESC
      ");
      $stmt->bind_param("i", $patient_id);
      $stmt->execute();
      $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      ?>

      <?php if (empty($records)): ?>
        <div>No dental records found for this patient.</div>
      <?php else: ?>

        <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
          <a class="btn primary" href="/qm/pages/admin/print_dental_record.php?patient_id=<?php echo $patient_id; ?>" target="_blank">Print All Records</a>
        </div>

        <?php foreach ($records as $r): ?>
          <div style="margin-bottom:18px; padding:16px; background:#f4fbff; border-radius:10px;">
            <div style="display:flex; justify-content:space-between; gap:16px;">
              <div>
                <div style="font-weight:900; color:#0b2f4f;"><?php echo h($r['service'] ?: ''); ?></div>
                <div style="font-size:13px; color:#6f7b86;">Dentist: <?php echo h($r['dentist_name'] ?: '—'); ?></div>
                <?php if (!empty($r['diagnosis'])): ?>
                  <div style="margin-top:8px; font-weight:800;">Dx: <?php echo nl2br(h($r['diagnosis'])); ?></div>
                <?php endif; ?>
                <?php if (!empty($r['treatment'])): ?>
                  <div style="margin-top:8px; font-weight:800;">Tx: <?php echo nl2br(h($r['treatment'])); ?></div>
                <?php endif; ?>
                <?php if (!empty($r['prescription'])): ?>
                  <div style="margin-top:8px; font-weight:800;">Prescription: <?php echo nl2br(h($r['prescription'])); ?></div>
                <?php endif; ?>
              </div>

              <div style="text-align:right;">
                <div style="font-weight:900;"><?php echo h($r['appointment_date'] . ' ' . $r['appointment_time']); ?></div>
                <div style="font-size:13px; color:#6f7b86;"><?php echo h(substr($r['created_at'],0,10)); ?></div>
                <div style="margin-top:8px;">
                  <a class="btn light" href="/qm/pages/admin/print_dental_record.php?record_id=<?php echo (int)$r['record_id']; ?>" target="_blank">View / Print</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

      <?php endif; ?>

    </section>
  <?php endif; ?>
</main>

</body>
</html>