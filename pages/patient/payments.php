<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";
$user = require_role(["patient"]);
$role = $user["role"];
$active = "payments";

function h($v){ return htmlspecialchars((string)$v); }
$err = $ok = "";

/* Handle simulated payment POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
  $appointment_id = (int)($_POST['appointment_id'] ?? 0);
  $amount = (float)($_POST['amount'] ?? 0);
  if ($appointment_id <= 0 || $amount <= 0) {
    $err = "Invalid payment data.";
  } else {
    $invoice = 'INV-' . time() . '-' . rand(100,999);
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, appointment_id, invoice_number, amount, currency, method, type, status, note) VALUES (?, ?, ?, ?, 'PHP', ?, 'payment', 'success', ?)");
    $method = 'manual';
    $note = 'Paid by user via manual UI';
    $stmt->bind_param("iidsis", $user['id'], $appointment_id, $invoice, $amount, $method, $note);
    $stmt->execute();
    $ok = "Payment recorded. Receipt #: " . $stmt->insert_id;

    // optional: mark appointment as paid / approved depending on workflow
  }
  header("Location: /qm/pages/patient/payments.php?ok=" . urlencode($ok));
  exit;
}

/* Load patient transactions */
$stmt = $conn->prepare("SELECT id, amount, type, status, created_at, note, invoice_number FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Load upcoming pending appointments for this patient (to pay) */
$stmt2 = $conn->prepare("SELECT a.id, a.appointment_date, a.appointment_time, s.name AS service FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.patient_id = ? AND a.status IN ('pending') ORDER BY a.appointment_date ASC LIMIT 10");
$stmt2->bind_param("i", $user['id']);
$stmt2->execute();
$upcoming = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

$ok = $_GET['ok'] ?? $ok;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Payment History</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>
<main class="main">
  <div class="pageHead"><h1 class="pageHead__title">Payment History</h1></div>

  <?php if ($ok): ?><div class="card callout callout--ok"><?php echo h($ok); ?></div><?php endif; ?>
  <?php if ($err): ?><div class="card callout callout--error"><?php echo h($err); ?></div><?php endif; ?>

  <section class="card" style="background:var(--accent-mid);">
    <h2 class="sectionTitle">Transactions</h2>
    <div class="table">
      <div class="table__row table__row--head" style="grid-template-columns: 1fr .7fr .7fr .6fr;">
        <div>Invoice / Note</div>
        <div>Date</div>
        <div>Type</div>
        <div style="text-align:right;">Amount</div>
      </div>

      <?php foreach ($transactions as $t): ?>
        <div class="table__row" style="grid-template-columns: 1fr .7fr .7fr .6fr;">
          <div style="font-weight:900; color:#0b2f4f;"><?php echo h($t['invoice_number'] ?? ('#' . $t['id'])); ?><div style="font-size:12px;opacity:.75;"><?php echo h($t['note'] ?? ''); ?></div></div>
          <div class="table__muted"><?php echo h($t['created_at']); ?></div>
          <div><?php echo h($t['type']); ?> / <?php echo h($t['status']); ?></div>
          <div class="table__right">₱<?php echo number_format((float)$t['amount'],2); ?></div>
        </div>
      <?php endforeach; ?>

      <?php if (!$transactions): ?>
        <div class="table__row"><div style="grid-column:1 / -1; font-weight:900; opacity:.75;">No transactions found.</div></div>
      <?php endif; ?>
    </div>
  </section>

  <section class="card" style="margin-top:12px;">
    <h2 class="sectionTitle">Upcoming appointments (pay now)</h2>
    <?php if ($upcoming): ?>
      <div class="table">
        <div class="table__row table__row--head" style="grid-template-columns: 1.2fr .6fr .6fr .6fr;">
          <div>Service</div><div>Date</div><div>Time</div><div style="text-align:right;">Action</div>
        </div>
        <?php foreach ($upcoming as $a): ?>
          <div class="table__row" style="grid-template-columns: 1.2fr .6fr .6fr .6fr;">
            <div style="font-weight:900; color:#0b2f4f;"><?php echo h($a['service']); ?></div>
            <div class="table__muted"><?php echo h($a['appointment_date']); ?></div>
            <div class="table__muted"><?php echo h(substr($a['appointment_time'],0,5)); ?></div>
            <div style="text-align:right;">
              <form method="post" style="display:inline-block;">
                <input type="hidden" name="action" value="pay">
                <input type="hidden" name="appointment_id" value="<?php echo (int)$a['id']; ?>">
                <!-- In a real app, amount should come from services/pricing. We'll use a sample 1200.00 -->
                <input type="hidden" name="amount" value="1200.00">
                <button class="btn btn--dark" type="submit">Pay ₱1,200.00</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="small-muted">No upcoming pending appointments to pay.</div>
    <?php endif; ?>
  </section>
</main>
</body>
</html>