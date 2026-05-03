<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["admin"]);
$role = $user["role"];
$active = "transactions";

function h($v){ return htmlspecialchars((string)$v); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); die("Not found"); }

$stmt = $conn->prepare("
  SELECT t.*, u.name AS user_name, u.email AS user_email, a.appointment_date, a.appointment_time
  FROM transactions t
  LEFT JOIN users u ON u.id = t.user_id
  LEFT JOIN appointments a ON a.id = t.appointment_id
  WHERE t.id = ? LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$tx = $stmt->get_result()->fetch_assoc();
if (!$tx) { http_response_code(404); die("Not found"); }

?>
<!doctype html>
<html>
<head><meta charset="utf-8" /><title>Transaction #<?php echo h($tx['id']); ?></title>
<link rel="stylesheet" href="/qm/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>
<main class="main">
  <div class="pageHead">
    <h1 class="pageHead__title">Transaction #<?php echo h($tx['id']); ?></h1>
  </div>

  <section class="card">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <div>
        <div style="font-weight:900;"><?php echo h($tx['user_name'] ?: 'Guest'); ?></div>
        <div style="font-size:13px; opacity:.8;"><?php echo h($tx['user_email'] ?? ''); ?></div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:18px; font-weight:900;">₱<?php echo number_format((float)$tx['amount'],2); ?></div>
        <div style="opacity:.8;"><?php echo h($tx['type']); ?> • <?php echo h($tx['status']); ?></div>
      </div>
    </div>

    <hr style="margin:12px 0;">
    <div><b>Invoice:</b> <?php echo h($tx['invoice_number'] ?: ('INV-' . $tx['id'])); ?></div>
    <div><b>Method:</b> <?php echo h($tx['method']); ?></div>
    <div><b>Date:</b> <?php echo h($tx['created_at']); ?></div>
    <?php if (!empty($tx['appointment_date'])): ?>
      <div><b>Appointment:</b> <?php echo h($tx['appointment_date'] . ' ' . substr($tx['appointment_time'],0,5)); ?></div>
    <?php endif; ?>
    <?php if (!empty($tx['note'])): ?>
      <div style="margin-top:10px;"><b>Note:</b> <?php echo nl2br(h($tx['note'])); ?></div>
    <?php endif; ?>

    <div style="margin-top:12px; display:flex; gap:8px; justify-content:flex-end;">
      <a class="btn" href="/qm/pages/admin/transactions.php">Back</a>
    </div>
  </section>
</main>
</body>
</html>