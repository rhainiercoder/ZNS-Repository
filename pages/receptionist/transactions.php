<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["receptionist"]);
$role = $user["role"];
$active = "transactions";

function h($v){ return htmlspecialchars((string)$v); }

/* Handle POST actions: refund (receptionist) */
$flash = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
  $action = $_POST['action'];
  if ($action === 'refund') {
    $txId = (int)($_POST['id'] ?? 0);
    if ($txId <= 0) {
      $flash = "Invalid transaction id.";
    } else {
      // load original transaction
      $stmt = $conn->prepare("SELECT id, user_id, appointment_id, amount, status FROM transactions WHERE id = ? LIMIT 1");
      $stmt->bind_param("i", $txId);
      $stmt->execute();
      $orig = $stmt->get_result()->fetch_assoc();
      if (!$orig) {
        $flash = "Transaction not found.";
      } elseif ($orig['type'] === 'refund') {
        $flash = "Cannot refund a refund.";
      } else {
        // create refund transaction (type=refund) and mark original as refunded
        $refundNote = "Refund for transaction #{$txId} — " . ($_POST['note'] ?? '');
        $ins = $conn->prepare("INSERT INTO transactions (user_id, appointment_id, invoice_number, amount, currency, method, type, status, note) VALUES (?, ?, ?, ?, 'PHP', ?, 'refund', 'success', ?)");
        $invoice = 'R-' . time() . '-' . rand(100,999);
        $method = 'manual';
        $amt = (float)$orig['amount'];
        $ins->bind_param("iisdss", $orig['user_id'], $orig['appointment_id'], $invoice, $amt, $method, $refundNote);
        $ins->execute();

        // mark original as refunded (status)
        $u = $conn->prepare("UPDATE transactions SET status = 'refunded' WHERE id = ?");
        $u->bind_param("i", $txId);
        $u->execute();

        $flash = "Refund created (ID: " . $ins->insert_id . ").";
      }
    }
  }
  // redirect to avoid double-post
  header("Location: /qm/pages/receptionist/transactions.php?msg=" . urlencode($flash));
  exit;
}

// read filters: q, type, status, date range, page
$q = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// build query with params
$where = [];
$params = [];
$types = ''; // for bind_param

if ($q !== '') {
  $where[] = "(t.invoice_number LIKE ? OR u.name LIKE ? OR t.note LIKE ?)";
  $term = "%$q%";
  $params[] = $term; $params[] = $term; $params[] = $term;
  $types .= 'sss';
}
if ($type !== '') {
  $where[] = "t.type = ?";
  $params[] = $type; $types .= 's';
}
if ($status !== '') {
  $where[] = "t.status = ?";
  $params[] = $status; $types .= 's';
}
if ($from !== '') {
  $where[] = "t.created_at >= ?";
  $params[] = $from . ' 00:00:00'; $types .= 's';
}
if ($to !== '') {
  $where[] = "t.created_at <= ?";
  $params[] = $to . ' 23:59:59'; $types .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// count total
$countSql = "SELECT COUNT(*) as c FROM transactions t LEFT JOIN users u ON u.id = t.user_id $whereSql";
$countStmt = $conn->prepare($countSql);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int)$countStmt->get_result()->fetch_assoc()['c'];
$pages = (int)ceil($total / $perPage);

// fetch page
$sql = "
  SELECT t.*, u.name AS user_name, a.appointment_date, a.appointment_time
  FROM transactions t
  LEFT JOIN users u ON u.id = t.user_id
  LEFT JOIN appointments a ON a.id = t.appointment_id
  $whereSql
  ORDER BY t.created_at DESC
  LIMIT ?, ?
";
$stmt = $conn->prepare($sql);
$bindParams = $params;
$bindTypes = $types . 'ii';
$bindParams[] = $offset;
$bindParams[] = $perPage;
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$msg = $_GET['msg'] ?? '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Receptionist - Transactions</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
  <style>.tx-row{display:flex;justify-content:space-between;align-items:center;padding:10px 8px;border-bottom:1px solid rgba(11,31,42,.06)}</style>
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>
<main class="main">
  <div class="pageHead"><h1 class="pageHead__title">Transactions</h1></div>

  <?php if ($msg): ?>
    <div class="card callout callout--info"><?php echo h($msg); ?></div>
  <?php endif; ?>

  <section class="card" style="background:var(--accent-mid);">
    <h2 class="sectionTitle">Transactions</h2>
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px;">
      <input name="q" value="<?php echo h($q); ?>" placeholder="Search invoice, user, note..." class="authInput w-360">
      <select name="type" class="authInput"><option value="">All types</option><option value="payment" <?php if($type==='payment')echo 'selected';?>>Payment</option><option value="refund" <?php if($type==='refund')echo 'selected';?>>Refund</option></select>
      <select name="status" class="authInput"><option value="">All status</option><option value="success" <?php if($status==='success')echo 'selected';?>>Success</option><option value="pending" <?php if($status==='pending')echo 'selected';?>>Pending</option><option value="failed" <?php if($status==='failed')echo 'selected';?>>Failed</option><option value="refunded" <?php if($status==='refunded')echo 'selected';?>>Refunded</option></select>
      <input type="date" name="from" value="<?php echo h($from); ?>" class="authInput">
      <input type="date" name="to" value="<?php echo h($to); ?>" class="authInput">
      <button class="btn btn--dark" type="submit">Filter</button>
      <a class="btn" href="/qm/pages/receptionist/transactions.php">Reset</a>
    </form>

    <div class="table" style="margin-top:6px;">
      <div class="table__row table__row--head" style="grid-template-columns: 1fr .8fr .6fr .7fr .6fr;">
        <div>User / Note</div>
        <div>Date</div>
        <div>Type</div>
        <div style="text-align:right;">Amount</div>
        <div style="text-align:right;">Action</div>
      </div>

      <?php foreach ($rows as $r): ?>
        <div class="table__row" style="grid-template-columns: 1fr .8fr .6fr .7fr .6fr;">
          <div style="font-weight:900; color:#0b2f4f;">
            <?php echo h($r['user_name'] ?: 'Guest'); ?>
            <div style="font-size:12px; font-weight:700; opacity:.75;"><?php echo h($r['note'] ?: ''); ?></div>
          </div>
          <div class="table__muted"><?php echo h(date('Y-m-d H:i', strtotime($r['created_at']))); ?></div>
          <div><?php echo h($r['type']); ?> / <?php echo h($r['status']); ?></div>
          <div class="table__right">₱<?php echo number_format((float)$r['amount'],2); ?></div>
          <div class="table__right">
            <a class="btn" href="/qm/pages/receptionist/transaction_view.php?id=<?php echo (int)$r['id']; ?>">View</a>
            <?php if ($r['type'] === 'payment' && $r['status'] === 'success'): ?>
              <form method="post" style="display:inline-block;margin-left:6px;" onsubmit="return confirm('Issue refund for this transaction?');">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <input type="hidden" name="action" value="refund">
                <button class="btn" type="submit" style="background:#e64545;color:#fff;">Refund</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <div class="table__row"><div style="grid-column:1 / -1; font-weight:900; opacity:.75;">No transactions found.</div></div>
      <?php endif; ?>
    </div>

    <!-- pagination -->
    <?php if ($pages > 1): ?>
      <div style="margin-top:12px;">
        <?php for ($p=1;$p<=$pages;$p++): ?>
          <a class="btn" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$p])); ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  </section>
</main>
</body>
</html>