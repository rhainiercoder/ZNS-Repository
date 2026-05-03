<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["patient"]);
$role = $user["role"];

function h($v){ return htmlspecialchars((string)$v); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); die("Record not found"); }

$stmt = $conn->prepare("
  SELECT
    dr.*,
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
  WHERE dr.id = ?
    AND dr.patient_id = ?
  LIMIT 1
");
$stmt->bind_param("ii", $id, $user['id']);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
if (!$rec) { http_response_code(404); die("Record not found"); }

$issued = date("Y-m-d H:i", strtotime($rec['created_at']));
$app_dt = $rec['appointment_date'];
$app_time = substr($rec['appointment_time'] ?? '', 0, 5);

// ---- Parse tooth legend codes from tooth_no like: "3:F, 4:X, 14:C" ----
$toothCodes = [];   // [toothNumber => code]
$codeCounts = [];  // [code => count]
$rawToothNo = trim((string)($rec['tooth_no'] ?? ''));

if ($rawToothNo !== '') {
  $parts = preg_split('/\s*,\s*/', $rawToothNo);
  foreach ($parts as $p) {
    if ($p === '') continue;
    if (preg_match('/\b([1-9]|[12][0-9]|3[0-2])\b\s*[:\-]?\s*([A-Za-z✓]{1,6})?/', $p, $m)) {
      $n = (int)$m[1];
      $code = strtoupper(trim($m[2] ?? ''));
      if ($n >= 1 && $n <= 32) {
        $toothCodes[$n] = $code !== '' ? $code : 'MARK';
        $codeCounts[$toothCodes[$n]] = ($codeCounts[$toothCodes[$n]] ?? 0) + 1;
      }
    }
  }
}

// code -> label + color class
$codeMeta = [
  'MARK' => ['label' => 'Marked',      'class' => 'is-selected'],
  'C'    => ['label' => 'Caries',      'class' => 'is-red'],
  'F'    => ['label' => 'Filling',     'class' => 'is-blue'],
  'X'    => ['label' => 'Extraction',  'class' => 'is-red'],
  'RCT'  => ['label' => 'Root Canal',  'class' => 'is-blue'],
  '✓'    => ['label' => 'Completed',   'class' => 'is-selected'],
];
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
    .no-print { margin-bottom: 12px; display:flex; gap:8px; justify-content:flex-end; }
    @media print { .no-print { display:none !important; } }
    table.rec-table { width:100%; border-collapse:collapse; margin-top:8px; }
    table.rec-table th, table.rec-table td { border:1px solid #ddd; padding:8px; font-size:13px; vertical-align:top; }

    /* ---- Tooth chart (box odontogram) ---- */
    .toothChart { margin-top: 14px; border: 1px solid #ddd; border-radius: 10px; padding: 10px; }
    .toothChart__title{ font-weight: 800; margin-bottom: 8px; }
    .toothRow { display: grid; grid-template-columns: repeat(16, 1fr); gap: 6px; margin-bottom: 10px; }
    .toothBox { border: 1px solid #222; border-radius: 6px; height: 34px; display: grid; place-items: center; font-size: 12px; font-weight: 800; background: #fff; }
    .toothBox small { display:block; font-weight: 700; opacity: .75; font-size: 10px; margin-top: 1px; }
    .toothBox.is-selected { background: #e9f7ff; border-color: #0b2f4f; box-shadow: inset 0 0 0 2px rgba(11,47,79,.18); }
    .toothBox.is-red { background:#ffe9e9; border-color:#b30000; }
    .toothBox.is-blue { background:#e9f0ff; border-color:#123b99; }

    .chartLegend{ display:flex; gap:10px; margin-top:8px; font-size:12px; font-weight:700; opacity:.9; flex-wrap:wrap; }
    .legendSwatch{ display:inline-block; width:14px; height:14px; border:1px solid #222; border-radius:3px; vertical-align:middle; margin-right:6px; }
    .legendSwatch.is-selected { background:#e9f7ff; border-color:#0b2f4f; }
    .legendSwatch.is-red { background:#ffe9e9; border-color:#b30000; }
    .legendSwatch.is-blue { background:#e9f0ff; border-color:#123b99; }
  </style>
</head>
<body>
  <div class="rec-wrap">
    <div class="no-print">
      <button class="btn btn--dark" onclick="window.print()">Print</button>
      <button class="btn" onclick="window.close()">Close</button>
    </div>

    <div style="display:flex; justify-content:space-between; gap:12px;">
      <div style="font-size:12px; line-height:1.2;">
        <strong>ZNS Dental Clinic</strong><br>
        Dental Record<br>
        Issued: <?php echo h($issued); ?><br>
        Record ID: <?php echo h($rec['id']); ?>
      </div>
      <div style="text-align:right; font-size:12px;">
        <img src="/qm/assets/img/logo.png" alt="Logo" style="height:52px;"><br>
        Dentist: <?php echo h($rec['dentist_name'] ?: '—'); ?>
      </div>
    </div>

    <h2 style="text-align:center; margin:14px 0 6px;">DENTAL RECORD</h2>

    <div style="font-weight:800; margin-top:10px;">
      Patient: <?php echo h($rec['patient_name']); ?><br>
      Service: <?php echo h($rec['service']); ?><br>
      Appointment: <?php echo h($app_dt . ' ' . $app_time); ?>
    </div>

    <div class="toothChart">
      <div class="toothChart__title">Tooth Chart (Odontogram)</div>

      <?php $upper = range(1,16); $lower = range(32,17); ?>

      <div style="font-size:12px; font-weight:700; opacity:.75; margin-bottom:6px;">Upper (1–16)</div>
      <div class="toothRow">
        <?php foreach ($upper as $n): ?>
          <?php
            $code = $toothCodes[$n] ?? '';
            $meta = $code !== '' ? ($codeMeta[$code] ?? $codeMeta['MARK']) : null;
            $cls = $meta ? (' ' . $meta['class']) : '';
          ?>
          <div class="toothBox<?php echo $cls; ?>">
            <?php echo (int)$n; ?>
            <small><?php echo h($code); ?></small>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="font-size:12px; font-weight:700; opacity:.75; margin-bottom:6px;">Lower (32–17)</div>
      <div class="toothRow">
        <?php foreach ($lower as $n): ?>
          <?php
            $code = $toothCodes[$n] ?? '';
            $meta = $code !== '' ? ($codeMeta[$code] ?? $codeMeta['MARK']) : null;
            $cls = $meta ? (' ' . $meta['class']) : '';
          ?>
          <div class="toothBox<?php echo $cls; ?>">
            <?php echo (int)$n; ?>
            <small><?php echo h($code); ?></small>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="chartLegend">
        <?php
          $usedCodes = array_keys($codeCounts);
          if (!$usedCodes) $usedCodes = ['MARK'];
        ?>
        <?php foreach ($usedCodes as $c): ?>
          <?php $m = $codeMeta[$c] ?? ['label'=>$c, 'class'=>'is-selected']; ?>
          <span>
            <span class="legendSwatch <?php echo h($m['class']); ?>"></span>
            <?php echo h($c); ?> = <?php echo h($m['label']); ?>
            (<?php echo (int)($codeCounts[$c] ?? 0); ?>)
          </span>
        <?php endforeach; ?>
      </div>
    </div>

    <table class="rec-table" style="margin-top:12px;">
      <tr>
        <th style="width:22%;">Diagnosis</th>
        <td><?php echo nl2br(h($rec['diagnosis'] ?? '')); ?></td>
      </tr>
      <tr>
        <th>Treatment</th>
        <td><?php echo nl2br(h($rec['treatment'] ?? '')); ?></td>
      </tr>
      <tr>
        <th>Prescription</th>
        <td><?php echo nl2br(h($rec['prescription'] ?? '')); ?></td>
      </tr>
      <tr>
        <th>Notes</th>
        <td><?php echo nl2br(h($rec['notes'] ?? '')); ?></td>
      </tr>
    </table>
  </div>
</body>
</html>