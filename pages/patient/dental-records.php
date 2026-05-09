<?php
// pages/patient/dental-records.php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["patient"]);
$role = $user["role"];
$uid  = (int)$user["id"];

function h($v){ return htmlspecialchars((string)$v); }

$view = $_GET['view'] ?? '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>My Dental Records - ZNS Dental Clinic</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>

<main class="main">
  <div class="pageHead">
    <h1 class="pageHead__title">My Dental Records</h1>
    <div style="margin-top:8px;">
      <a class="btn primary" href="/pages/patient/dental-records.php?view=all">View All My Records</a>
      <a class="btn light" href="/pages/patient/dental-records.php" style="margin-left:8px">Compact View</a>
    </div>
  </div>

  <?php if ($view === 'all'): ?>

    <section class="card">
      <?php
      // Full compiled list of this patient's records, newest first
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
      $stmt->bind_param("i", $uid);
      $stmt->execute();
      $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      ?>

      <?php if (empty($records)): ?>
        <div>No dental records found.</div>
      <?php else: ?>

        <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
          <a class="btn primary" href="/pages/patient/print_dental_record.php?patient_id=<?php echo $uid; ?>" target="_blank">Print All Records</a>
        </div>

        <?php foreach ($records as $r): ?>
          <div style="margin-bottom:18px; padding:16px; background:#f4fbff; border-radius:10px;">
            <div style="display:flex; justify-content:space-between; gap:16px;">
              <div style="max-width:68%;">
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

                <?php if (!empty($r['notes'])): ?>
                  <div style="margin-top:10px; color:#3b3b3b;">Notes: <?php echo nl2br(h($r['notes'])); ?></div>
                <?php endif; ?>
              </div>

              <div style="text-align:right; min-width:190px;">
                <div style="font-weight:900;"><?php echo h($r['appointment_date'] . ' ' . $r['appointment_time']); ?></div>
                <div style="font-size:13px; color:#6f7b86;"><?php echo h(substr($r['created_at'],0,10)); ?></div>
                <div style="margin-top:8px;">
                  <a class="btn light" href="/pages/patient/print_dental_record.php?record_id=<?php echo (int)$r['record_id']; ?>" target="_blank">View / Print</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

      <?php endif; ?>
    </section>

  <?php else: ?>

    <!-- Compact / existing view: group rows like the original layout -->
    <section class="card" style="background:#e9f7ff;">
      <div class="table">
        <div class="table__row table__row--head" style="grid-template-columns: 1.2fr .9fr .9fr;">
          <div>Service / Dentist</div>
          <div>Date/Time</div>
          <div style="text-align:right;">Created</div>
        </div>

        <?php
        // original compact listing (latest first)
        $stmt2 = $conn->prepare("
          SELECT
            dr.id,
            dr.created_at,
            dr.diagnosis,
            dr.treatment,
            dr.prescription,
            a.appointment_date,
            a.appointment_time,
            s.name AS service,
            d.name AS dentist_name
          FROM dental_records dr
          JOIN appointments a ON a.id = dr.appointment_id
          JOIN services s ON s.id = a.service_id
          LEFT JOIN users d ON d.id = dr.dentist_id
          WHERE dr.patient_id = ?
          ORDER BY dr.created_at DESC
        ");
        $stmt2->bind_param("i", $uid);
        $stmt2->execute();
        $recordsCompact = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>

        <?php if (empty($recordsCompact)): ?>
          <div class="table__row" style="grid-template-columns: 1fr;">
            <div>No records yet.</div>
          </div>
        <?php else: ?>
          <?php foreach ($recordsCompact as $r): ?>
            <div class="table__row" style="grid-template-columns: 1.2fr .9fr .9fr;">
              <div>
                <div style="font-weight:900; color:#0b2f4f;"><?php echo h($r["service"]); ?></div>
                <div style="font-size:12px; font-weight:900; opacity:.75;">Dentist: <?php echo h($r["dentist_name"] ?: "—"); ?></div>

                <?php if (!empty($r["diagnosis"])): ?>
                  <div style="margin-top:6px; font-weight:800; opacity:.75;">Dx: <?php echo h($r["diagnosis"]); ?></div>
                <?php endif; ?>

                <?php if (!empty($r["treatment"])): ?>
                  <div style="margin-top:6px; font-weight:800; opacity:.75;">Tx: <?php echo h($r["treatment"]); ?></div>
                <?php endif; ?>
              </div>

              <div style="text-align:center;">
                <?php echo h($r["appointment_date"] . ' ' . $r["appointment_time"]); ?>
              </div>

              <div style="text-align:right;">
                <div style="font-weight:900;"><?php echo h(substr($r["created_at"],0,10)); ?></div>
                <div style="margin-top:6px;">
                  <a class="btn light" href="/pages/patient/print_dental_record.php?record_id=<?php echo (int)$r['id']; ?>" target="_blank">View / Print</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

      </div>
    </section>

  <?php endif; ?>

</main>

</body>
</html>