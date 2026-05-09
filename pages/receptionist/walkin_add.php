<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

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

$services = $conn->query("SELECT id, name, price FROM services WHERE is_active = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$dentists = $conn->query("SELECT id, name FROM users WHERE role = 'dentist' ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $patientName = trim($_POST['patient_name'] ?? '');
  $contact = trim($_POST['contact'] ?? '');
  $serviceId = (int)($_POST['service_id'] ?? 0);
  $dentistId = (int)($_POST['dentist_id'] ?? 0);
  $amount = (float)($_POST['amount'] ?? 0);
  $notes = trim($_POST['notes'] ?? '');

  if ($patientName === '') $errors[] = "Patient name is required.";
  if ($amount < 0) $errors[] = "Amount cannot be negative.";

  if (!$errors) {
    $serviceParam = $serviceId > 0 ? $serviceId : null;
    $dentistParam = $dentistId > 0 ? $dentistId : null;
    $stmt = $conn->prepare("
      INSERT INTO walk_in_records (patient_name, contact, service_id, dentist_id, amount, notes, recorded_by)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssiidsi", $patientName, $contact, $serviceParam, $dentistParam, $amount, $notes, $user['id']);
    $stmt->execute();
    header("Location: /pages/receptionist/walkins.php?msg=" . urlencode("Walk-in record added."));
    exit;
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Add Walk-in Record</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../partials/sidebar.php"; ?>
<main class="main">
  <div class="pageHead">
    <h1 class="pageHead__title">Add Walk-in Record</h1>
    <a class="btn light" href="/pages/receptionist/walkins.php">View Walk-in Records</a>
  </div>

  <?php if ($errors): ?>
    <div class="card callout callout--error">
      <?php foreach ($errors as $e): ?><div><?php echo h($e); ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <section class="card" style="background:var(--accent-mid);">
    <form method="post" class="authForm" style="max-width:720px;">
      <label class="authLabel">Patient name</label>
      <input class="authInput" name="patient_name" value="<?php echo h($_POST['patient_name'] ?? ''); ?>" required>

      <label class="authLabel">Contact</label>
      <input class="authInput" name="contact" value="<?php echo h($_POST['contact'] ?? ''); ?>" placeholder="Phone or email">

      <label class="authLabel">Service</label>
      <select class="authInput" name="service_id" id="walkinService">
        <option value="">Walk-in service / not listed</option>
        <?php foreach ($services as $s): ?>
          <option value="<?php echo (int)$s['id']; ?>" data-price="<?php echo h($s['price']); ?>">
            <?php echo h($s['name']); ?> - PHP <?php echo number_format((float)$s['price'], 2); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label class="authLabel">Dentist</label>
      <select class="authInput" name="dentist_id">
        <option value="">Unassigned</option>
        <?php foreach ($dentists as $d): ?>
          <option value="<?php echo (int)$d['id']; ?>"><?php echo h($d['name']); ?></option>
        <?php endforeach; ?>
      </select>

      <label class="authLabel">Amount</label>
      <input class="authInput" id="walkinAmount" type="number" min="0" step="0.01" name="amount" value="<?php echo h($_POST['amount'] ?? '0.00'); ?>">

      <label class="authLabel">Notes</label>
      <textarea class="authInput" name="notes" rows="4"><?php echo h($_POST['notes'] ?? ''); ?></textarea>

      <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:12px;">
        <a class="btn" href="/pages/receptionist/walkins.php">Cancel</a>
        <button class="btn btn--dark" type="submit">Save Walk-in Record</button>
      </div>
    </form>
  </section>
</main>
<script>
(function(){
  const service = document.getElementById('walkinService');
  const amount = document.getElementById('walkinAmount');
  if (!service || !amount) return;
  service.addEventListener('change', function(){
    const selected = service.options[service.selectedIndex];
    const price = selected ? selected.getAttribute('data-price') : '';
    if (price && Number(price) > 0) amount.value = Number(price).toFixed(2);
  });
})();
</script>
</body>
</html>
