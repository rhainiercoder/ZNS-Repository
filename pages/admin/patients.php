<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["admin"]);
$role = $user["role"];
$active = "patients";

function h($v){ return htmlspecialchars((string)$v); }

$err = "";
$ok = "";

// Handle POST actions: add, edit, delete
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";
  if ($action === "add") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($name === "" || $email === "" || $password === "") {
      $err = "Name, email and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $err = "Invalid email address.";
    } else {
      // check unique email
      $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
      $stmt->bind_param("s", $email);
      $stmt->execute();
      if ($stmt->get_result()->fetch_assoc()) {
        $err = "Email is already in use.";
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'patient')");
        $stmt->bind_param("sss", $name, $email, $hash);
        $stmt->execute();
        $ok = "Patient added.";
      }
    }
  } elseif ($action === "edit") {
    $id = (int)($_POST["id"] ?? 0);
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($id <= 0 || $name === "" || $email === "") {
      $err = "ID, name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $err = "Invalid email address.";
    } else {
      // check if email used by other user
      $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
      $stmt->bind_param("si", $email, $id);
      $stmt->execute();
      if ($stmt->get_result()->fetch_assoc()) {
        $err = "Email is already in use by another user.";
      } else {
        if ($password !== "") {
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?");
          $stmt->bind_param("sssi", $name, $email, $hash, $id);
        } else {
          $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
          $stmt->bind_param("ssi", $name, $email, $id);
        }
        $stmt->execute();
        $ok = "Patient updated.";
      }
    }
  } elseif ($action === "delete") {
    $id = (int)($_POST["id"] ?? 0);
    if ($id > 0) {
      $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'patient'");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $ok = "Patient deleted.";
    } else {
      $err = "Invalid id for delete.";
    }
  }
}

// Query list / search
$q = trim($_GET["q"] ?? "");
$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

if ($q === "") {
  $stmt = $conn->prepare("SELECT SQL_CALC_FOUND_ROWS id, name, email, created_at FROM users WHERE role = 'patient' ORDER BY created_at DESC LIMIT ?, ?");
  $stmt->bind_param("ii", $offset, $perPage);
  $stmt->execute();
} else {
  $term = "%".$q."%";
  $stmt = $conn->prepare("SELECT SQL_CALC_FOUND_ROWS id, name, email, created_at FROM users WHERE role = 'patient' AND (name LIKE ? OR email LIKE ?) ORDER BY created_at DESC LIMIT ?, ?");
  $stmt->bind_param("ssii", $term, $term, $offset, $perPage);
  $stmt->execute();
}
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// get total
$totalRes = $conn->query("SELECT FOUND_ROWS() AS total");
$total = (int)$totalRes->fetch_assoc()["total"];
$pages = (int)ceil($total / $perPage);

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Admin - Patients</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>
<main class="main">
  <div class="pageHead">
    <h1 class="pageHead__title">Patients</h1>

    <form method="get" style="display:flex; gap:10px; align-items:center;">
      <input name="q" value="<?php echo h($q); ?>" placeholder="Search name or email..." class="authInput w-360" />
      <button class="btn btn--dark" type="submit">Search</button>
    </form>
  </div>

  <?php if ($err): ?>
    <div class="card callout callout--error"><?php echo h($err); ?></div>
  <?php endif; ?>
  <?php if ($ok): ?>
    <div class="card callout callout--ok"><?php echo h($ok); ?></div>
  <?php endif; ?>

  <!-- Add / Edit form -->
  <?php
    $editing = null;
    if (!empty($_GET["edit"])) {
      $eid = (int)$_GET["edit"];
      $s = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? AND role = 'patient' LIMIT 1");
      $s->bind_param("i", $eid);
      $s->execute();
      $editing = $s->get_result()->fetch_assoc();
    }
  ?>

  <div class="card">
    <h2 class="sectionTitle"><?php echo $editing ? "Edit Patient" : "Add Patient"; ?></h2>
    <form method="post" class="authForm" style="max-width:760px;">
      <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?php echo (int)$editing["id"]; ?>">
      <?php endif; ?>
      <label>
        <div class="authLabel">Name</div>
        <input name="name" value="<?php echo h($editing["name"] ?? ""); ?>" class="authInput" required>
      </label>
      <label>
        <div class="authLabel">Email</div>
        <input name="email" value="<?php echo h($editing["email"] ?? ""); ?>" class="authInput" required>
      </label>
      <label>
        <div class="authLabel">Password <?php if ($editing) echo "<small>(leave blank to keep)</small>"; ?></div>
        <input name="password" type="password" class="authInput" <?php if (!$editing) echo "required"; ?>>
      </label>

      <div style="display:flex; gap:8px; justify-content:flex-end;">
        <?php if ($editing): ?>
          <button class="btn" type="submit" name="action" value="edit">Save</button>
          <a class="btn" href="/qm/pages/admin/patients.php">Cancel</a>
        <?php else: ?>
          <button class="btn btn--dark" type="submit" name="action" value="add">Create Patient</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- List -->
  <section class="card" style="margin-top:12px;">
    <h2 class="sectionTitle">Patient List</h2>
    <div class="table">
      <div class="table__row table__row--head" style="grid-template-columns: 1.2fr .9fr .6fr;">
        <div>Patient</div>
        <div>Email</div>
        <div style="text-align:right;">Actions</div>
      </div>

      <?php foreach ($rows as $r): ?>
        <div class="table__row" style="grid-template-columns: 1.2fr .9fr .6fr;">
          <div class="patientCell">
            <div class="patientCell__icon"><?php echo strtoupper(substr($r["name"],0,1)); ?></div>
            <div>
              <div class="patientCell__name"><?php echo h($r["name"]); ?></div>
            </div>
          </div>
          <div class="table__muted"><?php echo h($r["email"]); ?></div>
          <div class="table__right">
            <a class="btn" href="?edit=<?php echo (int)$r["id"]; ?>">Edit</a>
            <form method="post" style="display:inline-block;margin-left:8px;" onsubmit="return confirm('Delete this patient?');">
              <input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">
              <button class="btn" name="action" value="delete" type="submit" style="background:#e64545;color:#fff;">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <div class="table__row"><div style="grid-column:1 / -1; font-weight:900; opacity:.75;">No patients found.</div></div>
      <?php endif; ?>
    </div>

    <!-- pagination -->
    <?php if ($pages > 1): ?>
      <div style="margin-top:12px; display:flex; gap:8px; align-items:center;">
        <?php for ($p=1;$p<=$pages;$p++): ?>
          <a class="btn" href="?<?php echo http_build_query(array_merge($_GET, ["page"=>$p])); ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  </section>
</main>
</body>
</html>