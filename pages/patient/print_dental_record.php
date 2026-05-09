<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["patient"]);
$role = $user["role"];
$uid  = (int)$user["id"];

function h($v){ return htmlspecialchars((string)$v); }

// Support both ?id= and ?record_id= for backward compatibility
$record_id  = (int)($_GET['record_id'] ?? ($_GET['id'] ?? 0));
$patient_id = (int)($_GET['patient_id'] ?? 0);

$rec = null;
$records = [];

if ($patient_id > 0 && $record_id <= 0) {
  // Print ALL records (patient can only print their own)
  if ($patient_id !== $uid) { http_response_code(403); die("Forbidden"); }

  $stmt = $conn->prepare("
    SELECT
      dr.*,
      a.appointment_date,
      a.appointment_time,
      s.name AS service,
      p.name AS patient_name,
      d.name AS dentist_name
    FROM dental_records dr
    LEFT JOIN appointments a ON a.id = dr.appointment_id
    LEFT JOIN services s ON s.id = a.service_id
    JOIN users p ON p.id = dr.patient_id
    LEFT JOIN users d ON d.id = dr.dentist_id
    WHERE dr.patient_id = ?
    ORDER BY dr.created_at DESC
  ");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  if (empty($records)) { http_response_code(404); die("Record not found"); }
} else {
  if ($record_id <= 0) { http_response_code(404); die("Record not found"); }

  $stmt = $conn->prepare("
    SELECT
      dr.*,
      a.appointment_date,
      a.appointment_time,
      s.name AS service,
      p.name AS patient_name,
      d.name AS dentist_name
    FROM dental_records dr
    LEFT JOIN appointments a ON a.id = dr.appointment_id
    LEFT JOIN services s ON s.id = a.service_id
    JOIN users p ON p.id = dr.patient_id
    LEFT JOIN users d ON d.id = dr.dentist_id
    WHERE dr.id = ?
      AND dr.patient_id = ?
    LIMIT 1
  ");
  $stmt->bind_param("ii", $record_id, $uid);
  $stmt->execute();
  $rec = $stmt->get_result()->fetch_assoc();
  if (!$rec) { http_response_code(404); die("Record not found"); }
}

$codeMeta = [
  'MARK' => ['label' => 'Marked',      'class' => 'is-selected'],
  'C'    => ['label' => 'Caries',      'class' => 'is-red'],
  'F'    => ['label' => 'Filling',     'class' => 'is-blue'],
  'X'    => ['label' => 'Extraction',  'class' => 'is-red'],
  'RCT'  => ['label' => 'Root Canal',  'class' => 'is-blue'],
  '✓'    => ['label' => 'Completed',   'class' => 'is-selected'],
];

function parse_tooth_codes(string $rawToothNo): array {
  $toothCodes = [];
  $codeCounts = [];
  $rawToothNo = trim($rawToothNo);
  if ($rawToothNo === '') return [$toothCodes, $codeCounts];

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
  return [$toothCodes, $codeCounts];
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>
    <?php if (!empty($records)): ?>
      My Dental Records
    <?php else: ?>
      Dental Record #<?php echo h($rec['id']); ?>
    <?php endif; ?>
  </title>
  <link rel="stylesheet" href="/assets/css/style.css">
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

    .record-break { page-break-before: always; }

    @media screen and (max-width: 640px) {
      html, body {
        width: 100%;
        overflow-x: hidden;
      }

      body {
        margin: 0;
        background: #fff;
      }

      .no-print {
        position: sticky !important;
        top: 0;
        margin: 0;
        padding: 10px 12px !important;
        border-bottom: 1px solid #e5e7eb;
      }

      .no-print > div {
        max-width: 100% !important;
        width: 100%;
        justify-content: stretch !important;
      }

      .no-print .btn {
        flex: 1 1 0;
        min-height: 44px;
        justify-content: center;
        text-align: center;
      }

      .rec-wrap {
        width: 100%;
        max-width: none;
        padding: 14px 12px 22px;
        overflow-wrap: anywhere;
      }

      .rec-wrap > div:first-child {
        align-items: flex-start;
        gap: 10px !important;
      }

      .rec-wrap > div:first-child img {
        height: 44px !important;
      }

      .rec-wrap h2 {
        font-size: 24px;
        line-height: 1.1;
        margin-top: 18px !important;
      }

      .toothChart {
        padding: 10px 8px;
        border-radius: 8px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }

      .toothRow {
        grid-template-columns: repeat(8, minmax(38px, 1fr));
        gap: 6px;
        min-width: 344px;
      }

      .toothBox {
        min-width: 38px;
        height: 36px;
      }

      .chartLegend {
        gap: 8px;
        line-height: 1.35;
      }

      table.rec-table,
      table.rec-table tbody,
      table.rec-table tr,
      table.rec-table th,
      table.rec-table td {
        display: block;
        width: 100% !important;
      }

      table.rec-table tr {
        border: 1px solid #ddd;
        border-bottom: 0;
      }

      table.rec-table tr:last-child {
        border-bottom: 1px solid #ddd;
      }

      table.rec-table th,
      table.rec-table td {
        border: 0;
        padding: 8px 10px;
        text-align: left;
      }

      table.rec-table th {
        background: #f7fbfc;
        border-bottom: 1px solid #e5e7eb;
      }
    }
  </style>
</head>
<body>
  <div class="no-print" style="position:sticky; top:0; background:#fff; padding:12px 18px; z-index:10;">
    <div style="max-width:800px; margin:0 auto; display:flex; gap:8px; justify-content:flex-end;">
      <button class="btn btn--dark" onclick="window.print()">Print</button>
      <button class="btn" onclick="window.close()">Close</button>
    </div>
  </div>

  <?php
    $list = !empty($records) ? $records : [$rec];
  ?>

  <?php foreach ($list as $i => $row): ?>
    <?php
      $issued = date("Y-m-d H:i", strtotime($row['created_at'] ?? 'now'));
      $app_dt = $row['appointment_date'] ?? '';
      $app_time = substr($row['appointment_time'] ?? '', 0, 5);
      [$toothCodes, $codeCounts] = parse_tooth_codes((string)($row['tooth_no'] ?? ''));
    ?>

    <div class="rec-wrap<?php echo $i === 0 ? '' : ' record-break'; ?>">
      <div style="display:flex; justify-content:space-between; gap:12px;">
        <div style="font-size:12px; line-height:1.2;">
          <strong>ZNS Dental Clinic</strong><br>
          Dental Record<br>
          Issued: <?php echo h($issued); ?><br>
          Record ID: <?php echo h($row['id']); ?>
        </div>
        <div style="text-align:right; font-size:12px;">
          <img src="/assets/img/logo.png" alt="Logo" style="height:52px;"><br>
          Dentist: <?php echo h($row['dentist_name'] ?: '—'); ?>
        </div>
      </div>

      <h2 style="text-align:center; margin:14px 0 6px;">DENTAL RECORD</h2>

      <div style="font-weight:800; margin-top:10px;">
        Patient: <?php echo h($row['patient_name']); ?><br>
        Service: <?php echo h($row['service'] ?? '—'); ?><br>
        Appointment: <?php echo h(trim(($app_dt . ' ' . $app_time)) ?: '—'); ?>
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
          <td><?php echo nl2br(h($row['diagnosis'] ?? '')); ?></td>
        </tr>
        <tr>
          <th>Treatment</th>
          <td><?php echo nl2br(h($row['treatment'] ?? '')); ?></td>
        </tr>
        <tr>
          <th>Prescription</th>
          <td><?php echo nl2br(h($row['prescription'] ?? '')); ?></td>
        </tr>
        <tr>
          <th>Notes</th>
          <td><?php echo nl2br(h($row['notes'] ?? '')); ?></td>
        </tr>
      </table>
    </div>
  <?php endforeach; ?>
</body>
</html>
