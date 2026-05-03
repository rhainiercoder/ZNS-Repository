<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["dentist"]);
$role = $user['role'];    // ensure sidebar has the role
$active = "settings";

function h($v){ return htmlspecialchars((string)$v); }

/**
 * Safe helper to check for a column — uses INFORMATION_SCHEMA and accepts prepared params.
 */
if (!function_exists('table_has_column')) {
    function table_has_column(mysqli $conn, string $table, string $column): bool {
        $sql = "
          SELECT COUNT(*) AS c
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
          LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) return false;
        $stmt->bind_param("ss", $table, $column);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return (int)($row['c'] ?? 0) > 0;
    }
}

$hasContact = table_has_column($conn, 'users', 'contact');

$dentist_id = $user['id'];
$flash = '';

// Handle POST: update profile or save availability
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!empty($_POST['action']) && $_POST['action'] === 'profile') {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');

    if ($hasContact) {
      $stmt = $conn->prepare("UPDATE users SET name = ?, contact = ? WHERE id = ?");
      $stmt->bind_param("ssi", $name, $contact, $dentist_id);
    } else {
      $stmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
      $stmt->bind_param("si", $name, $dentist_id);
    }
    $stmt->execute();
    $flash = "Profile updated.";
  } elseif (!empty($_POST['action']) && $_POST['action'] === 'hours') {
    // arrays: day[], start_time[], end_time[]
    $days = $_POST['day'] ?? [];
    $starts = $_POST['start_time'] ?? [];
    $ends = $_POST['end_time'] ?? [];

    // start transaction: delete existing for this dentist then re-insert
    $conn->begin_transaction();
    try {
      $d = $conn->prepare("DELETE FROM dentist_availability WHERE dentist_id = ?");
      $d->bind_param("i", $dentist_id);
      $d->execute();

      $ins = $conn->prepare("INSERT INTO dentist_availability (dentist_id, `day`, start_time, end_time) VALUES (?, ?, ?, ?)");
      for ($i=0;$i<count($days);$i++) {
        $day = (int)$days[$i];
        $s = trim($starts[$i] ?? '');
        $e = trim($ends[$i] ?? '');
        if ($day >=1 && $day <=7 && $s !== '' && $e !== '') {
          $ins->bind_param("iiss", $dentist_id, $day, $s, $e);
          $ins->execute();
        }
      }
      $conn->commit();
      $flash = "Operating hours saved.";
    } catch (Exception $ex) {
      $conn->rollback();
      $flash = "Failed to save hours.";
    }
  }

  header("Location: /qm/pages/dentist/settings.php?msg=" . urlencode($flash));
  exit;
}

// load dentist profile (select contact only if column exists)
if ($hasContact) {
  $stmt = $conn->prepare("SELECT id, name, email, contact FROM users WHERE id = ? LIMIT 1");
} else {
  $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
}
$stmt->bind_param("i", $dentist_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
if (!isset($profile['contact'])) $profile['contact'] = ''; // safe default

// load availability
$avail = [];
try {
  $s = $conn->prepare("SELECT id, `day`, start_time, end_time FROM dentist_availability WHERE dentist_id = ? ORDER BY `day`, start_time");
  $s->bind_param("i", $dentist_id);
  $s->execute();
  $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
  foreach ($rows as $r) $avail[] = $r;
} catch (Exception $e) {
  $avail = [];
}

$weekdayNames = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'];
$msg = $_GET['msg'] ?? '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Dentist Settings</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
  <style>.hoursTable{display:grid;gap:6px;}</style>
</head>
<body>
<?php require_once __DIR__ . "/../../partials/sidebar.php"; ?>
<main class="main">
  <div class="pageHead"><h1 class="pageHead__title">Settings</h1></div>
  <?php if ($msg): ?><div class="card callout callout--ok"><?php echo h($msg); ?></div><?php endif; ?>

  <section class="card">
    <h2 class="sectionTitle">Profile</h2>
    <form method="post" style="max-width:720px;">
      <input type="hidden" name="action" value="profile">
      <label>Name
        <input class="authInput" name="name" value="<?php echo h($profile['name'] ?? ''); ?>">
      </label>
      <label>Email (read-only)
        <input class="authInput" value="<?php echo h($profile['email'] ?? ''); ?>" readonly>
      </label>
      <?php if ($hasContact): ?>
      <label>Contact
        <input class="authInput" name="contact" value="<?php echo h($profile['contact'] ?? ''); ?>">
      </label>
      <?php endif; ?>
      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;">
        <button class="btn btn--dark" type="submit">Save Profile</button>
      </div>
    </form>
  </section>

  <section class="card" style="margin-top:12px;">
    <h2 class="sectionTitle">Operating hours</h2>
    <p style="margin:6px 0 12px; color:#666;">Add time ranges for each weekday. You may add multiple ranges per day.</p>

    <form method="post" id="hoursForm">
      <input type="hidden" name="action" value="hours">
      <div id="hoursContainer">
        <?php if ($avail): ?>
          <?php foreach ($avail as $a): ?>
            <div class="hoursRow" style="display:flex;gap:8px;margin-bottom:6px;">
              <select name="day[]" style="padding:8px;"><?php foreach($weekdayNames as $k=>$n): ?><option value="<?php echo $k; ?>" <?php if($k==$a['day']) echo 'selected'; ?>><?php echo h($n); ?></option><?php endforeach; ?></select>
              <input type="time" name="start_time[]" value="<?php echo h($a['start_time']); ?>">
              <input type="time" name="end_time[]" value="<?php echo h($a['end_time']); ?>">
              <button type="button" class="btn" onclick="this.closest('.hoursRow').remove();">Remove</button>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <!-- pre-populate with all weekdays empty -->
          <?php foreach ($weekdayNames as $k=>$n): ?>
            <div class="hoursRow" style="display:flex;gap:8px;margin-bottom:6px;">
              <select name="day[]" style="padding:8px;">
                <?php foreach($weekdayNames as $kk=>$nn): ?>
                  <option value="<?php echo $kk; ?>" <?php if($kk==$k) echo 'selected'; ?>><?php echo h($nn); ?></option>
                <?php endforeach; ?>
              </select>
              <input type="time" name="start_time[]" value="">
              <input type="time" name="end_time[]" value="">
              <button type="button" class="btn" onclick="this.closest('.hoursRow').remove();">Remove</button>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div style="margin-top:8px;">
        <button type="button" class="btn" id="addRange">Add time range</button>
        <button type="submit" class="btn btn--dark">Save hours</button>
      </div>
    </form>

    <script>
      document.getElementById('addRange').addEventListener('click', function(){
        var container = document.getElementById('hoursContainer');
        var row = document.createElement('div');
        row.className = 'hoursRow';
        row.style = 'display:flex;gap:8px;margin-bottom:6px;';
        row.innerHTML = `<?php
          ob_start();
          ?><select name="day[]" style="padding:8px;"><?php foreach($weekdayNames as $kk=>$nn){ ?><option value="<?php echo $kk;?>"><?php echo h($nn);?></option><?php } ?></select>
            <input type="time" name="start_time[]" value="">
            <input type="time" name="end_time[]" value="">
            <button type="button" class="btn" onclick="this.closest('.hoursRow').remove();">Remove</button><?php
          echo str_replace(["\n","\r"], ['',''], addslashes(ob_get_clean()));
        ?>`;
        container.appendChild(row);
      });
    </script>
  </section>

</main>
</body>
</html>