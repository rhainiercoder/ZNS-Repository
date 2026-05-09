<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["admin"]);
$role = $user['role']; // ensure sidebar has the role
$active = "settings";

function h($v){ return htmlspecialchars((string)$v); }

// Detect optional columns (price, is_active) so this page doesn't crash on older DBs
$hasPrice = false;
$hasActive = false;
try {
  $cols = $conn->query("SHOW COLUMNS FROM services")->fetch_all(MYSQLI_ASSOC);
  $names = array_map(fn($c) => $c['Field'], $cols);
  $hasPrice = in_array('price', $names, true);
  $hasActive = in_array('is_active', $names, true) || in_array('active', $names, true);
} catch (Exception $e) {}

// Which column name to use for active flag
$activeCol = null;
if ($hasActive) {
  // prefer is_active if exists
  $activeCol = 'is_active';
  try {
    $conn->query("SELECT is_active FROM services LIMIT 1");
  } catch (Exception $e) {
    $activeCol = 'active';
  }
}

$err = "";
$ok = "";

// Handle add/update/toggle/delete
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";

  if ($action === "add") {
    $name = trim($_POST["name"] ?? "");
    $price = (float)($_POST["price"] ?? 0);

    if ($name === "") {
      $err = "Service name is required.";
    } else {
      if ($hasPrice && $activeCol) {
        $stmt = $conn->prepare("INSERT INTO services (name, price, $activeCol) VALUES (?, ?, 1)");
        $stmt->bind_param("sd", $name, $price);
      } elseif ($hasPrice) {
        $stmt = $conn->prepare("INSERT INTO services (name, price) VALUES (?, ?)");
        $stmt->bind_param("sd", $name, $price);
      } elseif ($activeCol) {
        $stmt = $conn->prepare("INSERT INTO services (name, $activeCol) VALUES (?, 1)");
        $stmt->bind_param("s", $name);
      } else {
        $stmt = $conn->prepare("INSERT INTO services (name) VALUES (?)");
        $stmt->bind_param("s", $name);
      }
      $stmt->execute();
      $ok = "Service added.";
    }
  }

  if ($action === "update") {
    $id = (int)($_POST["id"] ?? 0);
    $name = trim($_POST["name"] ?? "");
    $price = (float)($_POST["price"] ?? 0);

    if ($id <= 0 || $name === "") {
      $err = "Invalid service.";
    } else {
      if ($hasPrice) {
        $stmt = $conn->prepare("UPDATE services SET name=?, price=? WHERE id=?");
        $stmt->bind_param("sdi", $name, $price, $id);
      } else {
        $stmt = $conn->prepare("UPDATE services SET name=? WHERE id=?");
        $stmt->bind_param("si", $name, $id);
      }
      $stmt->execute();
      $ok = "Service updated.";
    }
  }

  if ($action === "toggle" && $activeCol) {
    $id = (int)($_POST["id"] ?? 0);
    $val = (int)($_POST["val"] ?? 0); // 1 or 0
    if ($id > 0) {
      $stmt = $conn->prepare("UPDATE services SET $activeCol=? WHERE id=?");
      $stmt->bind_param("ii", $val, $id);
      $stmt->execute();
      $ok = "Service status updated.";
    }
  }

  header("Location: /pages/admin/settings_services.php?ok=" . urlencode($ok) . "&err=" . urlencode($err));
  exit;
}

// Fetch services
$services = [];
try {
  if ($hasPrice && $activeCol) {
    $services = $conn->query("SELECT id, name, price, $activeCol AS is_active FROM services ORDER BY name ASC")
      ->fetch_all(MYSQLI_ASSOC);
  } elseif ($hasPrice) {
    $services = $conn->query("SELECT id, name, price FROM services ORDER BY name ASC")
      ->fetch_all(MYSQLI_ASSOC);
  } elseif ($activeCol) {
    $services = $conn->query("SELECT id, name, $activeCol AS is_active FROM services ORDER BY name ASC")
      ->fetch_all(MYSQLI_ASSOC);
  } else {
    $services = $conn->query("SELECT id, name FROM services ORDER BY name ASC")
      ->fetch_all(MYSQLI_ASSOC);
  }
} catch (Exception $e) {
  $services = [];
}

$ok = $_GET["ok"] ?? "";
$err = $_GET["err"] ?? "";
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Admin - Services Settings</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>

<main class="main">
  <div class="pageHead" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
    <h1 class="pageHead__title">Services</h1>
    <a class="btn light" href="/pages/admin/settings.php">Back to Settings</a>
  </div>

  <?php if ($ok): ?>
    <div class="card" style="background:#e9f7ff; margin-bottom:12px; font-weight:800;"><?php echo h($ok); ?></div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="card" style="background:#ffe9e9; margin-bottom:12px; font-weight:800;"><?php echo h($err); ?></div>
  <?php endif; ?>

  <section class="card">
    <h2 class="sectionTitle">Add Service</h2>
    <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
      <input type="hidden" name="action" value="add">
      <div style="min-width:240px;">
        <label style="font-weight:800; display:block; margin-bottom:6px;">Service Name</label>
        <input name="name" required style="width:100%; padding:10px; border-radius:12px; border:1px solid rgba(0,0,0,.12);">
      </div>

      <?php if ($hasPrice): ?>
      <div style="min-width:180px;">
        <label style="font-weight:800; display:block; margin-bottom:6px;">Price</label>
        <input name="price" type="number" step="0.01" min="0" value="0" style="width:100%; padding:10px; border-radius:12px; border:1px solid rgba(0,0,0,.12);">
      </div>
      <?php endif; ?>

      <button class="btn btn--dark" type="submit">Add</button>
    </form>
  </section>

  <section class="card" style="background:#e9f7ff;">
    <h2 class="sectionTitle">Services Offered</h2>

    <div class="table">
      <div class="table__row table__row--head" style="grid-template-columns: 1.2fr <?php echo $hasPrice ? '.6fr' : ''; ?> .8fr;">
        <div>Service</div>
        <?php if ($hasPrice): ?><div style="text-align:right;">Price</div><?php endif; ?>
        <div style="text-align:right;">Action</div>
      </div>

      <?php foreach ($services as $s): ?>
        <form class="table__row" method="post" style="grid-template-columns: 1.2fr <?php echo $hasPrice ? '.6fr' : ''; ?> .8fr; align-items:center;">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">

          <div>
            <input name="name" value="<?php echo h($s['name']); ?>"
              style="width:100%; padding:10px; border-radius:12px; border:1px solid rgba(0,0,0,.12); font-weight:800;">
            <?php if (isset($s['is_active'])): ?>
              <div style="font-size:12px; font-weight:800; opacity:.75; margin-top:6px;">
                Status: <?php echo ((int)$s['is_active'] === 1) ? "Active" : "Inactive"; ?>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($hasPrice): ?>
          <div style="text-align:right;">
            <input name="price" type="number" step="0.01" min="0" value="<?php echo h($s['price'] ?? 0); ?>"
              style="width:100%; max-width:140px; text-align:right; padding:10px; border-radius:12px; border:1px solid rgba(0,0,0,.12); font-weight:800;">
          </div>
          <?php endif; ?>

          <div style="text-align:right; display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;">
            <button class="btn btn--dark" type="submit">Save</button>

            <?php if (isset($s['is_active'])): ?>
              <form method="post">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                <input type="hidden" name="val" value="<?php echo ((int)$s['is_active'] === 1) ? 0 : 1; ?>">
                <button class="btn light" type="submit">
                  <?php echo ((int)$s['is_active'] === 1) ? "Disable" : "Enable"; ?>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </form>
      <?php endforeach; ?>

      <?php if (!$services): ?>
        <div class="table__row">
          <div style="grid-column:1 / -1; font-weight:800; opacity:.7;">No services found.</div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!$hasPrice || !$hasActive): ?>

  <?php endif; ?>

</main>
</body>
</html>