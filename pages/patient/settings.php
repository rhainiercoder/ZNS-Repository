<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["patient"]);
$role = $user['role'];            // ensure sidebar has the role
$active = "settings";

function h($v){ return htmlspecialchars((string)$v); }

/**
 * Helper: check if a table has a given column.
 */
/**
 * Returns true if the given table has the given column.
 * Usage: $has = table_has_column($conn, 'users', 'contact');
 */
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

$hasContact = table_has_column($conn, 'users', 'contact');

$uid = $user['id'];
$flash = '';

// handle profile update or password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!empty($_POST['action']) && $_POST['action'] === 'profile') {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');

    if ($hasContact) {
      $stmt = $conn->prepare("UPDATE users SET name = ?, contact = ? WHERE id = ?");
      $stmt->bind_param("ssi", $name, $contact, $uid);
    } else {
      // contact column missing — update only name
      $stmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
      $stmt->bind_param("si", $name, $uid);
    }
    $stmt->execute();
    $flash = "Profile updated.";
  } elseif (!empty($_POST['action']) && $_POST['action'] === 'password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($new !== $confirm) {
      $flash = "New password and confirmation do not match.";
    } else {
      // verify current password
      $s = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
      $s->bind_param("i", $uid);
      $s->execute();
      $row = $s->get_result()->fetch_assoc();
      $hash = $row['password'] ?? '';
      if (!password_verify($current, $hash)) {
        $flash = "Current password is incorrect.";
      } else {
        $newhash = password_hash($new, PASSWORD_DEFAULT);
        $u = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $u->bind_param("si", $newhash, $uid);
        $u->execute();
        $flash = "Password updated.";
      }
    }
  }
  header("Location: /qm/pages/patient/settings.php?msg=" . urlencode($flash));
  exit;
}

// load profile (select contact only if column exists)
if ($hasContact) {
  $stmt = $conn->prepare("SELECT id, name, email, contact FROM users WHERE id = ? LIMIT 1");
} else {
  $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
}
$stmt->bind_param("i", $uid);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
if (!isset($profile['contact'])) $profile['contact'] = ''; // safe default if column missing

$msg = $_GET['msg'] ?? '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>My Settings</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
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
        <button class="btn btn--dark" type="submit">Save</button>
      </div>
    </form>
  </section>

  <section class="card" style="margin-top:12px;">
    <h2 class="sectionTitle">Change password</h2>
    <form method="post" style="max-width:720px;">
      <input type="hidden" name="action" value="password">
      <label>Current password
        <input class="authInput" type="password" name="current_password" required>
      </label>
      <label>New password
        <input class="authInput" type="password" name="new_password" required>
      </label>
      <label>Confirm new password
        <input class="authInput" type="password" name="confirm_password" required>
      </label>
      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;">
        <button class="btn btn--dark" type="submit">Change password</button>
      </div>
    </form>
  </section>

</main>
</body>
</html>