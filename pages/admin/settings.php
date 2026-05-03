<?php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

$user = require_role(["admin"]);
$role = $user['role']; // ensure sidebar has the role
$active = "settings";

function h($v){ return htmlspecialchars((string)$v); }

function get_setting($conn, $k, $default = '') {
  $s = $conn->prepare("SELECT v FROM settings WHERE k = ? LIMIT 1");
  $s->bind_param("s", $k);
  $s->execute();
  $r = $s->get_result()->fetch_assoc();
  return $r['v'] ?? $default;
}

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // simple CSRF via session token could be added; keeping minimal
  $clinic_name = trim($_POST['clinic_name'] ?? '');
  $clinic_address = trim($_POST['clinic_address'] ?? '');
  $clinic_phone = trim($_POST['clinic_phone'] ?? '');
  $clinic_tz = trim($_POST['clinic_timezone'] ?? 'Asia/Manila');
  $clinic_day_start = trim($_POST['clinic_day_start'] ?? '08:00');

  // location fields
  $clinic_lat = trim($_POST['clinic_lat'] ?? '');
  $clinic_lng = trim($_POST['clinic_lng'] ?? '');
  $clinic_map_zoom = trim($_POST['clinic_map_zoom'] ?? '');

  $stmt = $conn->prepare("INSERT INTO settings (`k`,`v`) VALUES (?,?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
  $pairs = [
    ['clinic_name',$clinic_name],
    ['clinic_address',$clinic_address],
    ['clinic_phone',$clinic_phone],
    ['clinic_timezone',$clinic_tz],
    ['clinic_day_start',$clinic_day_start],
    ['clinic_lat',$clinic_lat],
    ['clinic_lng',$clinic_lng],
    ['clinic_map_zoom',$clinic_map_zoom]
  ];
  foreach ($pairs as $p) {
    $stmt->bind_param("ss", $p[0], $p[1]);
    $stmt->execute();
  }
  $flash = "Clinic settings saved.";
  header("Location: /qm/pages/admin/settings.php?msg=" . urlencode($flash));
  exit;
}

// load current values
$clinic_name = get_setting($conn, 'clinic_name', 'ZNS Dental Clinic');
$clinic_address = get_setting($conn, 'clinic_address', '');
$clinic_phone = get_setting($conn, 'clinic_phone', '');
$clinic_timezone = get_setting($conn, 'clinic_timezone', 'Asia/Manila');
$clinic_day_start = get_setting($conn, 'clinic_day_start', '08:00');
$clinic_lat = get_setting($conn, 'clinic_lat', '14.5995');
$clinic_lng = get_setting($conn, 'clinic_lng', '120.9842');
$clinic_map_zoom = get_setting($conn, 'clinic_map_zoom', '15');

$msg = $_GET['msg'] ?? '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Admin - Settings</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

  <style>
    /* small style for map in admin settings */
    #clinicMap { height: 360px; border:1px solid #ddd; border-radius:6px; margin-top:8px; }
    .map-controls { display:flex; gap:8px; align-items:center; margin-top:6px; }
    .coordField { width:140px; }
  </style>
</head>
<body>
<?php require_once __DIR__ . "/../../partials/sidebar.php"; ?>
<main class="main">
  <div class="pageHead"><h1 class="pageHead__title">Settings</h1></div>

  <?php if ($msg): ?><div class="card callout callout--ok"><?php echo h($msg); ?></div><?php endif; ?>

  <section class="card">
    <h2 class="sectionTitle">Clinic Settings</h2>
    <form method="post" style="display:grid; gap:10px; max-width:920px;">
      <label>Clinic name
        <input class="authInput" name="clinic_name" value="<?php echo h($clinic_name); ?>">
      </label>
      <label>Address
        <input class="authInput" id="clinicAddress" name="clinic_address" value="<?php echo h($clinic_address); ?>">
      </label>
      <label>Phone
        <input class="authInput" name="clinic_phone" value="<?php echo h($clinic_phone); ?>">
      </label>
      <label>Timezone (PHP timezone)
        <input class="authInput" name="clinic_timezone" value="<?php echo h($clinic_timezone); ?>">
      </label>
      <label>Clinic day start (HH:MM) — determines "clinic day" rollover
        <input class="authInput" name="clinic_day_start" value="<?php echo h($clinic_day_start); ?>" placeholder="08:00">
      </label>

      <!-- Location map -->
      <label>Clinic location (click map to set marker)</label>
      <div id="clinicMap" aria-hidden="false"></div>
      <div class="map-controls">
        <label>Latitude
          <input class="authInput coordField" id="clinicLat" name="clinic_lat" value="<?php echo h($clinic_lat); ?>">
        </label>
        <label>Longitude
          <input class="authInput coordField" id="clinicLng" name="clinic_lng" value="<?php echo h($clinic_lng); ?>">
        </label>
        <label>Zoom
          <input class="authInput" id="clinicZoom" name="clinic_map_zoom" value="<?php echo h($clinic_map_zoom); ?>" style="width:70px;">
        </label>
        <button type="button" class="btn" id="centerFromAddress">Center from address</button>
        <div style="flex:1"></div>
      </div>

      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;">
        <button class="btn btn--dark" type="submit">Save</button>
        <a class="btn" href="/qm/pages/admin/reports.php">Cancel</a>
      </div>
    </form>
  </section>
</main>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
(function(){
  // initial values (from server)
  var lat = parseFloat(<?php echo json_encode($clinic_lat); ?>) || 14.5995;
  var lng = parseFloat(<?php echo json_encode($clinic_lng); ?>) || 120.9842;
  var zoom = parseInt(<?php echo json_encode($clinic_map_zoom); ?>, 10) || 15;

  var map = L.map('clinicMap').setView([lat, lng], zoom);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  var marker = L.marker([lat, lng], {draggable: true}).addTo(map);

  function updateFieldsFromMarker() {
    var p = marker.getLatLng();
    document.getElementById('clinicLat').value = p.lat.toFixed(6);
    document.getElementById('clinicLng').value = p.lng.toFixed(6);
    document.getElementById('clinicZoom').value = map.getZoom();
  }
  // initial write
  updateFieldsFromMarker();

  // click on map to move marker
  map.on('click', function(e){
    marker.setLatLng(e.latlng);
    updateFieldsFromMarker();
  });

  // when marker dragged
  marker.on('dragend', updateFieldsFromMarker);

  // when map zoom changed update zoom field
  map.on('zoomend', function(){ document.getElementById('clinicZoom').value = map.getZoom(); });

  // when user edits lat/lng fields manually, update marker
  var latField = document.getElementById('clinicLat');
  var lngField = document.getElementById('clinicLng');
  latField.addEventListener('change', function(){
    var la = parseFloat(latField.value);
    var lo = parseFloat(lngField.value);
    if (!isNaN(la) && !isNaN(lo)) {
      var p = L.latLng(la, lo);
      marker.setLatLng(p);
      map.setView(p);
    }
  });
  lngField.addEventListener('change', function(){ latField.dispatchEvent(new Event('change')); });

  // Center map from address using Nominatim (simple client-side geocode). Rate limits apply.
  document.getElementById('centerFromAddress').addEventListener('click', function(){
    var q = document.getElementById('clinicAddress').value.trim();
    if (!q) { alert('Enter an address first'); return; }
    // simple Nominatim lookup
    var url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q);
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data && data.length) {
          var d = data[0];
          var p = L.latLng(parseFloat(d.lat), parseFloat(d.lon));
          marker.setLatLng(p);
          map.setView(p, 16);
          updateFieldsFromMarker();
        } else {
          alert('Address not found');
        }
      }).catch(function(){
        alert('Geocoding failed');
      });
  });
})();
</script>
</body>
</html>