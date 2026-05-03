<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";
$user = require_role(["dentist"]);
$role = $user["role"];
$active = "transactions";

function h($v){ return htmlspecialchars((string)$v); }

/* List transactions for appointments assigned to this dentist */
$stmt = $conn->prepare("
  SELECT t.*, u.name AS patient_name, a.appointment_date, a.appointment_time
  FROM transactions t
  LEFT JOIN appointments a ON a.id = t.appointment_id
  LEFT JOIN users u ON u.id = t.user_id
  WHERE a.dentist_id = ?
  ORDER BY t.created_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head><meta charset="utf-8" /><title>Dentist - Transaction History</title>
<link rel="stylesheet" href="/qm/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>
<main class="main">
  <div class="pageHead"><h1 class="pageHead__title">Transaction History</h1></div>

  <section class="card" style="background:var(--accent-mid);">
    <h2 class="sectionTitle">Payments for your appointments</h2>

    <div class="table">
      <div class="table__row table__row--head" style="grid-template-columns: 1fr .9fr .6fr .6fr;">
        <div>Patient / Note</div><div>Date</div><div>Type</div><div style="text-align:right;">Amount</div>
      </div>

      <?php foreach ($rows as $r): ?>
        <div class="table__row" style="grid-template-columns: 1fr .9fr .6fr .6fr;">
          <div style="font-weight:900; color:#0b2f4f;"><?php echo h($r['patient_name'] ?: 'Guest'); ?><div style="font-size:12px;opacity:.75;"><?php echo h($r['note'] ?: ''); ?></div></div>
          <div class="table__muted"><?php echo h($r['created_at']); ?></div>
          <div><?php echo h($r['type']); ?> / <?php echo h($r['status']); ?></div>
          <div class="table__right">₱<?php echo number_format((float)$r['amount'],2); ?></div>
        </div>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <div class="table__row"><div style="grid-column:1 / -1; font-weight:900; opacity:.75;">No transactions found.</div></div>
      <?php endif; ?>
    </div>
  </section>
</main>
</body>
</html>