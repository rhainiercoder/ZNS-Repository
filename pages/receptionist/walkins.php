<?php
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../db.php";

$user = require_role(["receptionist"]);
$role = $user["role"];
$active = "walkins";

function h($v){ return htmlspecialchars((string)$v); }

$conn->query("
  CREATE TABLE IF NOT EXISTS walk_in_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_name VARCHAR(120) NOT NULL,
    contact VARCHAR(64) NULL,
    service_id INT NULL,
    dentist_id INT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    recorded_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (service_id),
    INDEX (dentist_id),
    INDEX (recorded_by)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$q = trim($_GET['q'] ?? '');
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$where = [];
$params = [];
$types = '';

if ($q !== '') {
  $where[] = "(w.patient_name LIKE ? OR w.contact LIKE ? OR s.name LIKE ? OR d.name LIKE ?)";
  $term = "%$q%";
  $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
  $types .= 'ssss';
}
if ($from !== '') {
  $where[] = "w.created_at >= ?";
  $params[] = $from . ' 00:00:00';
  $types .= 's';
}
if ($to !== '') {
  $where[] = "w.created_at <= ?";
  $params[] = $to . ' 23:59:59';
  $types .= 's';
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";
$sql = "
  SELECT
    w.*,
    s.name AS service_name,
    d.name AS dentist_name,
    r.name AS receptionist_name
  FROM walk_in_records w
  LEFT JOIN services s ON s.id = w.service_id
  LEFT JOIN users d ON d.id = w.dentist_id
  LEFT JOIN users r ON r.id = w.recorded_by
  $whereSql
  ORDER BY w.created_at DESC
  LIMIT 200
";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$msg = $_GET['msg'] ?? '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Receptionist - Walk-in Records</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../partials/sidebar.php"; ?>
<main class="main">
  <div class="pageHead">
    <h1 class="pageHead__title">Walk-in Records</h1>
    <a class="btn btn--dark" href="/pages/receptionist/walkin_add.php">Add Walk-in Record</a>
  </div>

  <?php if ($msg): ?>
    <div class="card callout callout--ok"><?php echo h($msg); ?></div>
  <?php endif; ?>

  <section class="card" style="background:var(--accent-mid);">
    <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px;">
      <input name="q" value="<?php echo h($q); ?>" placeholder="Search patient, contact, service, dentist..." class="authInput w-360">
      <input type="date" name="from" value="<?php echo h($from); ?>" class="authInput">
      <input type="date" name="to" value="<?php echo h($to); ?>" class="authInput">
      <button class="btn btn--dark" type="submit">Filter</button>
      <a class="btn" href="/pages/receptionist/walkins.php">Reset</a>
    </form>

    <div class="table">
      <div class="table__row table__row--head" style="grid-template-columns: 1fr .9fr .9fr .6fr .8fr;">
        <div>Patient</div>
        <div>Service</div>
        <div>Dentist</div>
        <div style="text-align:right;">Amount</div>
        <div style="text-align:right;">Recorded</div>
      </div>

      <?php foreach ($rows as $r): ?>
        <div class="table__row" style="grid-template-columns: 1fr .9fr .9fr .6fr .8fr;">
          <div style="font-weight:900; color:#0b2f4f;">
            <?php echo h($r['patient_name']); ?>
            <div style="font-size:12px; font-weight:800; opacity:.7;"><?php echo h($r['contact'] ?: 'No contact'); ?></div>
            <?php if (!empty($r['notes'])): ?>
              <div style="font-size:12px; font-weight:800; opacity:.7;"><?php echo h($r['notes']); ?></div>
            <?php endif; ?>
          </div>
          <div class="table__muted"><?php echo h($r['service_name'] ?: 'Walk-in service'); ?></div>
          <div class="table__muted"><?php echo h($r['dentist_name'] ?: 'Unassigned'); ?></div>
          <div class="table__right">PHP <?php echo number_format((float)$r['amount'], 2); ?></div>
          <div class="table__right">
            <?php echo h(date('Y-m-d H:i', strtotime($r['created_at']))); ?>
            <div style="font-size:12px; font-weight:800; opacity:.7;"><?php echo h($r['receptionist_name'] ?: 'Receptionist'); ?></div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <div class="table__row"><div style="grid-column:1 / -1; font-weight:900; opacity:.75;">No walk-in records found.</div></div>
      <?php endif; ?>
    </div>
  </section>
</main>
</body>
</html>
