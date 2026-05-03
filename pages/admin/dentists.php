<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["admin"]);
$role = $user["role"];
$active = "dentists";

function h($v){ return htmlspecialchars((string)$v); }
function day_name($d) {
  $map = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
  return $map[(int)$d] ?? '';
}

/* Helpers to format availability array into readable string */
function format_avail_list(array $slots) {
  // slots = array of ['day'=>int,'start_time'=>'HH:MM:SS','end_time'=>'HH:MM:SS']
  $parts = [];
  foreach ($slots as $s) {
    $start = substr($s['start_time'],0,5);
    $end = substr($s['end_time'],0,5);
    $parts[] = day_name($s['day']) . ' ' . $start . '–' . $end;
  }
  return $parts ? implode('; ', $parts) : '—';
}

$err = "";
$ok = "";

/* POST handling: add / edit / delete */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";
  if ($action === "add" || $action === "edit") {
    // common fields
    $id = (int)($_POST["id"] ?? 0);
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    // availability arrays (optional)
    $avail_days = $_POST['avail_day'] ?? [];
    $avail_starts = $_POST['avail_start'] ?? [];
    $avail_ends = $_POST['avail_end'] ?? [];

    // basic validation
    if ($name === "" || $email === "") {
      $err = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $err = "Invalid email address.";
    } else {
      // check email uniqueness
      if ($action === "add") {
        $s = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $s->bind_param("s", $email);
        $s->execute();
        if ($s->get_result()->fetch_assoc()) {
          $err = "Email is already in use.";
        }
      } else {
        $s = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $s->bind_param("si", $email, $id);
        $s->execute();
        if ($s->get_result()->fetch_assoc()) {
          $err = "Email is already in use by another user.";
        }
      }
    }

    // validate availability rows (if provided)
    $valid_avail = [];
    for ($i = 0; $i < count($avail_days); $i++) {
      $d = (int)$avail_days[$i];
      $st = trim($avail_starts[$i] ?? "");
      $en = trim($avail_ends[$i] ?? "");
      if ($d === 0 && $st === "" && $en === "") {
        // empty row, skip
        continue;
      }
      // required fields for a row
      if ($d < 1 || $d > 7 || $st === "" || $en === "") {
        $err = "Each availability row must have a valid day, start and end time.";
        break;
      }
      // ensure times are valid and start < end
      if (!preg_match('/^\d{2}:\d{2}$/',$st) || !preg_match('/^\d{2}:\d{2}$/',$en)) {
        $err = "Times must be in HH:MM format.";
        break;
      }
      if ($st >= $en) {
        $err = "Start time must be earlier than end time.";
        break;
      }
      $valid_avail[] = ['day'=>$d, 'start_time'=>$st.':00', 'end_time'=>$en.':00'];
    }

    if ($err === "") {
      if ($action === "add") {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, meta) VALUES (?, ?, ?, 'dentist', ?)");
        $meta = ''; // keep meta for backward compatibility; we store structured availability separately
        $stmt->bind_param("ssss", $name, $email, $hash, $meta);
        $stmt->execute();
        $did = $stmt->insert_id;
        $ok = "Dentist added.";
      } else {
        // edit
        if ($password !== "") {
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ? AND role = 'dentist'");
          $stmt->bind_param("sssi", $name, $email, $hash, $id);
        } else {
          $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'dentist'");
          $stmt->bind_param("ssi", $name, $email, $id);
        }
        $stmt->execute();
        $did = $id;
        $ok = "Dentist updated.";
      }

      // save availability if migration table exists
      try {
        // remove existing slots for this dentist and insert the valid ones
        $del = $conn->prepare("DELETE FROM dentist_availability WHERE dentist_id = ?");
        $del->bind_param("i", $did);
        $del->execute();

        if ($valid_avail) {
          $ins = $conn->prepare("INSERT INTO dentist_availability (dentist_id, `day`, start_time, end_time) VALUES (?, ?, ?, ?)");
          foreach ($valid_avail as $slot) {
            $ins->bind_param("iiss", $did, $slot['day'], $slot['start_time'], $slot['end_time']);
            $ins->execute();
          }
        }
      } catch (mysqli_sql_exception $e) {
        // If dentist_availability table doesn't exist or other DB error, surface a friendly message but keep dentist saved
        $err = "Dentist saved, but could not save availability (missing migration?). DB message: " . $e->getMessage();
        $ok = "";
      }
    }
  } elseif ($action === "delete") {
    $id = (int)($_POST["id"] ?? 0);
    if ($id > 0) {
      $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'dentist'");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $ok = "Dentist deleted.";
    } else {
      $err = "Invalid id for delete.";
    }
  }
}

/* Query list / search (same pagination as before) */
$q = trim($_GET["q"] ?? "");
$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

if ($q === "") {
  $stmt = $conn->prepare("SELECT SQL_CALC_FOUND_ROWS id, name, email, created_at FROM users WHERE role = 'dentist' ORDER BY created_at DESC LIMIT ?, ?");
  $stmt->bind_param("ii", $offset, $perPage);
  $stmt->execute();
} else {
  $term = "%".$q."%";
  $stmt = $conn->prepare("SELECT SQL_CALC_FOUND_ROWS id, name, email, created_at FROM users WHERE role = 'dentist' AND (name LIKE ? OR email LIKE ?) ORDER BY created_at DESC LIMIT ?, ?");
  $stmt->bind_param("ssii", $term, $term, $offset, $perPage);
  $stmt->execute();
}
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Load all availability slots for dentists (simple single query) */
$avail_map = [];
try {
  $res = $conn->query("SELECT dentist_id, `day`, start_time, end_time FROM dentist_availability ORDER BY dentist_id, `day`, start_time");
  while ($r = $res->fetch_assoc()) {
    $did = (int)$r['dentist_id'];
    $avail_map[$did][] = ['day'=>(int)$r['day'],'start_time'=>$r['start_time'],'end_time'=>$r['end_time']];
  }
} catch (mysqli_sql_exception $e) {
  // table may not exist yet; ignore
}

$totalRes = $conn->query("SELECT FOUND_ROWS() AS total");
$total = (int)$totalRes->fetch_assoc()["total"];
$pages = (int)ceil($total / $perPage);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Admin - Dentists</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
  <style>
    /* small inline styles for availability rows layout (keeps file self-contained) */
    .avail-row { display:flex; gap:8px; align-items:center; margin-bottom:6px; }
    .avail-row select, .avail-row input[type="time"] { padding:8px 10px; border-radius:10px; border:1px solid rgba(11,31,42,.10); font-weight:800; }
    .avail-actions { display:flex; gap:8px; align-items:center; }
    .avail-remove { background:#e64545; color:#fff; border-radius:10px; padding:6px 10px; border:0; cursor:pointer; }
    .avail-add { background:#0b2f4f; color:#fff; border-radius:10px; padding:8px 10px; border:0; cursor:pointer; }
    .small-muted{ font-weight:800; opacity:.8; font-size:13px; }
  </style>
</head>
<body>
<?php include __DIR__ . "/../../partials/sidebar.php"; ?>
<main class="main">
  <div class="pageHead">
    <h1 class="pageHead__title">Dentists</h1>

    <form method="get" style="display:flex; gap:10px; align-items:center;">
      <input name="q" value="<?php echo h($q); ?>" placeholder="Search name, email, availability..." class="authInput w-360" />
      <button class="btn btn--dark" type="submit">Search</button>
    </form>
  </div>

  <?php if ($err): ?>
    <div class="card callout callout--error"><?php echo h($err); ?></div>
  <?php endif; ?>
  <?php if ($ok): ?>
    <div class="card callout callout--ok"><?php echo h($ok); ?></div>
  <?php endif; ?>

  <?php
    // load dentist for editing if requested
    $editing = null;
    $editing_slots = [];
    if (!empty($_GET["edit"])) {
      $eid = (int)$_GET["edit"];
      $s = $conn->prepare("SELECT id, name, email, meta FROM users WHERE id = ? AND role = 'dentist' LIMIT 1");
      $s->bind_param("i", $eid);
      $s->execute();
      $editing = $s->get_result()->fetch_assoc();

      // load availability for this dentist
      if ($editing) {
        $es = $conn->prepare("SELECT `day`, start_time, end_time FROM dentist_availability WHERE dentist_id = ? ORDER BY `day`, start_time");
        $es->bind_param("i", $eid);
        $es->execute();
        $editing_slots = $es->get_result()->fetch_all(MYSQLI_ASSOC);
      }
    }
  ?>

  <div class="card">
    <h2 class="sectionTitle"><?php echo $editing ? "Edit Dentist" : "Add Dentist"; ?></h2>
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

      <label>
        <div class="authLabel">Availability (select day and time ranges)</div>
        <div id="availabilityRows">
          <?php
            // render existing slots if editing, otherwise render one empty row
            $rows_to_render = $editing_slots ?: [['day'=>'','start_time'=>'','end_time'=>'']];
            foreach ($rows_to_render as $rslot):
          ?>
            <div class="avail-row">
              <select name="avail_day[]">
                <option value="">Day</option>
                <?php for ($d=1;$d<=7;$d++): $sel = ($rslot['day']==$d) ? 'selected' : ''; ?>
                  <option value="<?php echo $d; ?>" <?php echo $sel; ?>><?php echo h(day_name($d)); ?></option>
                <?php endfor; ?>
              </select>
              <input type="time" name="avail_start[]" value="<?php echo $rslot['start_time'] ? substr($rslot['start_time'],0,5) : ''; ?>">
              <input type="time" name="avail_end[]" value="<?php echo $rslot['end_time'] ? substr($rslot['end_time'],0,5) : ''; ?>">
              <div class="avail-actions">
                <button type="button" class="avail-remove" onclick="this.closest('.avail-row').remove();">Remove</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div style="margin-top:8px;">
          <button type="button" class="avail-add" id="addAvailBtn">+ Add availability</button>
          <div class="small-muted" style="margin-top:8px;">You can add multiple day/time ranges. Example: Mon 09:00–17:00; Sat 09:00–12:00</div>
        </div>
      </label>

      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;">
        <?php if ($editing): ?>
          <button class="btn" type="submit" name="action" value="edit">Save</button>
          <a class="btn" href="/qm/pages/admin/dentists.php">Cancel</a>
        <?php else: ?>
          <button class="btn btn--dark" type="submit" name="action" value="add">Create Dentist</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- List -->
  <section class="card" style="margin-top:12px;">
    <h2 class="sectionTitle">Dentist List</h2>
    <div class="table">
      <div class="table__row table__row--head" style="grid-template-columns: 1.2fr .9fr 1.2fr .6fr;">
        <div>Dentist</div>
        <div>Email</div>
        <div>Availability</div>
        <div style="text-align:right;">Actions</div>
      </div>

      <?php foreach ($rows as $r): 
        $did = (int)$r['id'];
        $slots = $avail_map[$did] ?? [];
        $avail_text = format_avail_list($slots);
      ?>
        <div class="table__row" style="grid-template-columns: 1.2fr .9fr 1.2fr .6fr;">
          <div class="patientCell">
            <div class="patientCell__icon"><?php echo strtoupper(substr($r["name"],0,1)); ?></div>
            <div>
              <div class="patientCell__name"><?php echo h($r["name"]); ?></div>
            </div>
          </div>
          <div class="table__muted"><?php echo h($r["email"]); ?></div>
          <div class="table__muted"><?php echo h($avail_text); ?></div>
          <div class="table__right">
            <a class="btn" href="?edit=<?php echo (int)$r["id"]; ?>">Edit</a>
            <form method="post" style="display:inline-block;margin-left:8px;" onsubmit="return confirm('Delete this dentist?');">
              <input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">
              <button class="btn" name="action" value="delete" type="submit" style="background:#e64545;color:#fff;">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <div class="table__row"><div style="grid-column:1 / -1; font-weight:900; opacity:.75;">No dentists found.</div></div>
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

<script>
document.getElementById('addAvailBtn').addEventListener('click', function(){
  const container = document.getElementById('availabilityRows');
  const row = document.createElement('div');
  row.className = 'avail-row';
  row.innerHTML = `
    <select name="avail_day[]">
      <option value="">Day</option>
      <option value="1">Mon</option><option value="2">Tue</option><option value="3">Wed</option>
      <option value="4">Thu</option><option value="5">Fri</option><option value="6">Sat</option><option value="7">Sun</option>
    </select>
    <input type="time" name="avail_start[]" value="">
    <input type="time" name="avail_end[]" value="">
    <div class="avail-actions">
      <button type="button" class="avail-remove" onclick="this.closest('.avail-row').remove();">Remove</button>
    </div>
  `;
  container.appendChild(row);
});
</script>

</body>
</html>