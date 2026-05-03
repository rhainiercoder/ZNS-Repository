<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["admin","staff","dentist"]); // allow dentist/staff/admin to view
$role = $user["role"];

function h($v){ return htmlspecialchars((string)$v); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); die("Record not found"); }

// load dental record with related appointment, service, patient, dentist
$stmt = $conn->prepare("
  SELECT dr.*, a.appointment_date, a.appointment_time, s.name AS service,
         p.name AS patient_name, p.email AS patient_email, p.id AS patient_id,
         d.name AS dentist_name
  FROM dental_records dr
  JOIN appointments a ON a.id = dr.appointment_id
  JOIN services s ON s.id = a.service_id
  JOIN users p ON p.id = dr.patient_id
  LEFT JOIN users d ON d.id = dr.dentist_id
  WHERE dr.id = ? LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
if (!$rec) { http_response_code(404); die("Record not found"); }

// format dates
$issued = date("Y-m-d H:i", strtotime($rec['created_at']));
$app_dt = $rec['appointment_date'];
$app_time = substr($rec['appointment_time'] ?? '',0,5);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Dental Record #<?php echo h($rec['id']); ?></title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
  <style>

    @page { margin: 18mm; }
    body { font-family: Arial, Helvetica, sans-serif; color:#111; background:#fff; }
    .rec-wrap { max-width: 800px; margin: 0 auto; padding: 18px; background:#fff; }
    .clinic-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .clinic-left { font-size:12px; line-height:1.1; }
    .clinic-right { text-align:center; font-size:12px; }
    h1 { margin:10px 0 2px; font-size:16px; }
    .meta { margin-top:8px; display:flex; gap:12px; justify-content:space-between; align-items:center; font-weight:700; }
    .section { margin-top:12px; border-radius:8px; padding:10px; }
    .patient-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:13px; }
    .intra { background:#f8f8f8; padding:6px; border-radius:6px; font-size:12px; }
    table.rec-table { width:100%; border-collapse:collapse; margin-top:8px; }
    table.rec-table th, table.rec-table td { border:1px solid #ddd; padding:8px; font-size:13px; }
    .sign { margin-top:18px; display:flex; justify-content:space-between; gap:12px; }
    .sign .box { width:45%; border-top:1px solid #333; padding-top:6px; text-align:center; font-size:13px; }
    .print-controls { position:fixed; right:18px; top:18px; z-index:9999; }
    .print-controls .btn{ margin-bottom:6px; display:block; }
    @media print {
      .print-controls{ display:none; }
      body{ background:#fff; }
    }
  </style>
</head>
<body>
  <div class="print-controls">
    <button class="btn btn--dark" onclick="window.print()">Print</button>
    <!-- If you later add server-side PDF generation, link to it here -->
    <a class="btn" href="/qm/pages/admin/reports.php">Back</a>
  </div>

  <div class="rec-wrap">
    <div class="clinic-header">
      <div class="clinic-left">
        <strong>ZNS Dental Clinic</strong><br>
        #12345 St., Malinta, Valenzuela City<br>
        Tel: 123 456 / 0912-345-6789<br>
        Issued: <?php echo h($issued); ?><br>
        Record ID: <?php echo h($rec['id']); ?>
      </div>

      <div class="clinic-right">
        <img src="/qm/assets/img/logo.png" alt="Logo" style="height:64px;">
        <div style="margin-top:6px; font-weight:700;"><?php echo h($rec['dentist_name'] ?: ''); ?></div>
      </div>
    </div>

    <h1 style="text-align:center; margin-top:12px;">DENTAL RECORD</h1>

    <div class="section">
      <div class="patient-grid">
        <div><strong>Patient:</strong> <?php echo h($rec['patient_name']); ?></div>
        
        <div><strong>Service:</strong> <?php echo h($rec['service']); ?></div>
        <div><strong>Appointment:</strong> <?php echo h($app_dt . ' ' . $app_time); ?></div>
        <div style="grid-column:1 / -1;"><strong>Diagnosis:</strong> <?php echo nl2br(h($rec['diagnosis'] ?? '')); ?></div>
        <div style="grid-column:1 / -1;"><strong>Treatment:</strong> <?php echo nl2br(h($rec['treatment'] ?? '')); ?></div>
        <div style="grid-column:1 / -1;"><strong>Prescription:</strong> <?php echo nl2br(h($rec['prescription'] ?? '')); ?></div>
      </div>
    </div>

    <div class="section" style="margin-top:10px;">
      <strong>Notes / Additional Info</strong>
      <div style="margin-top:6px; font-size:13px;"><?php echo nl2br(h($rec['notes'] ?? '')); ?></div>
    </div>

    <div class="section" style="margin-top:10px;">
      <strong>Procedure Log</strong>
      <table class="rec-table">
        <thead>
          <tr><th>Date</th><th>Diagnosis</th><th>Tooth #</th><th>Procedure & Recommendation</th></tr>
        </thead>
        <tbody>
          <tr>
            <td><?php echo h($rec['created_at']); ?></td>
            <td><?php echo h($rec['diagnosis']); ?></td>
            <td><?php echo h($rec['tooth_no'] ?? ''); ?></td>
            <td><?php echo nl2br(h($rec['treatment'] ?? '')); ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="sign">
      <div class="box">Dentist signature</div>
      <div class="box">Patient / Guardian signature</div>
    </div>
  </div>
</body>
</html>