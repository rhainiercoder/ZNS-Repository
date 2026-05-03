<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["admin"]);
$role = $user["role"];
$active = "reports";

function h($v){ return htmlspecialchars((string)$v); }

$q = trim($_GET['q'] ?? '');
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

// Build query
$where = [];
$params = [];
$types = '';

if ($q !== '') {
  $where[] = "(p.name LIKE ? OR d.name LIKE ? OR s.name LIKE ? OR dr.diagnosis LIKE ?)";
  $term = "%$q%";
  $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
  $types .= 'ssss';
}
if ($from !== '') {
  $where[] = "a.appointment_date >= ?";
  $params[] = $from; $types .= 's';
}
if ($to !== '') {
  $where[] = "a.appointment_date <= ?";
  $params[] = $to; $types .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
  SELECT
    dr.id,
    dr.created_at,
    dr.diagnosis,
    dr.treatment,
    dr.prescription,
    dr.notes,
    a.appointment_date,
    a.appointment_time,
    s.name AS service,
    p.id AS patient_id,
    p.name AS patient_name,
    d.name AS dentist_name
  FROM dental_records dr
  JOIN appointments a ON a.id = dr.appointment_id
  JOIN services s ON s.id = a.service_id
  JOIN users p ON p.id = dr.patient_id
  LEFT JOIN users d ON d.id = dr.dentist_id
  $whereSql
  ORDER BY dr.created_at DESC
  LIMIT 200
";

$stmt = $conn->prepare($sql);
if ($params) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Admin - Reports</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
  <style>
    .report-row{ display:flex; justify-content:space-between; gap:12px; padding:12px; border-bottom:1px solid rgba(11,31,42,.06); }
  </style>
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>
<main class="main">
  <div class="pageHead"><h1 class="pageHead__title">Reports</h1></div>

  <section class="card" style="background:var(--accent-mid);">
    <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <input name="q" value="<?php echo h($q); ?>" placeholder="Search patient, dentist, service, diagnosis..." class="authInput w-360">
      <input type="date" name="from" value="<?php echo h($from); ?>" class="authInput">
      <input type="date" name="to" value="<?php echo h($to); ?>" class="authInput">
      <button class="btn btn--dark" type="submit">Filter</button>
      <a class="btn" href="/qm/pages/admin/reports.php">Reset</a>
    </form>

    <div style="margin-top:12px;">
      <?php if (!$rows): ?>
        <div class="callout callout--info">No records found for the selected filters.</div>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <div class="report-row">
            <div style="flex:1;">
              <div style="font-weight:900; color:#0b2f4f;"><?php echo h($r['patient_name']); ?> • <?php echo h($r['service']); ?></div>
              <div style="font-size:13px; opacity:.8;"><?php echo h($r['diagnosis'] ? $r['diagnosis'] : ($r['treatment'] ? $r['treatment'] : 'Dental record')); ?></div>
              <div style="font-size:12px; opacity:.7;"><?php echo h($r['appointment_date']); ?> <?php echo h(substr($r['appointment_time'],0,5)); ?> • Dentist: <?php echo h($r['dentist_name'] ?: '—'); ?></div>
            </div>

            <div style="display:flex; gap:8px; align-items:center;">
              <a class="btn" href="/qm/pages/admin/print_dental_record.php?id=<?php echo (int)$r['id']; ?>" target="_blank">View / Print</a>
              <a class="btn" href="/qm/pages/admin/dental-records.php?service_id=<?php echo (int)$r['service']; ?>">Open</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</main>
</body>
</html>