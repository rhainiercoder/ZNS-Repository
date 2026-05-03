<?php
// pages/dentist/location.php
require_once __DIR__ . "/../../auth.php";
require_once __DIR__ . "/../../db.php";

// allow dentists too; change if you want admin-only
$user = require_role(["admin","dentist","receptionist","patient"]);
$role = $user["role"];
$active = "location";

function h($v){ return htmlspecialchars((string)$v); }

// read settings helper
function get_setting($conn, $k, $default = '') {
  $s = $conn->prepare("SELECT v FROM settings WHERE k = ? LIMIT 1");
  $s->bind_param("s", $k);
  $s->execute();
  $r = $s->get_result()->fetch_assoc();
  return $r['v'] ?? $default;
}

$clinic_name = get_setting($conn, 'clinic_name', 'ZNS Dental Clinic');
$clinic_address = get_setting($conn, 'clinic_address', '');
$clinic_phone = get_setting($conn, 'clinic_phone', '');
$lat = get_setting($conn, 'clinic_lat', '14.5995');
$lng = get_setting($conn, 'clinic_lng', '120.9842');
$zoom = (int)get_setting($conn, 'clinic_map_zoom', 15);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Location & Map - <?php echo h($clinic_name); ?></title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">

  <!-- Leaflet CSS (no integrity attribute to avoid blocking) -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    #clinicMap { height:420px; border:1px solid #ddd; border-radius:6px; }
    .locCard { max-width:920px; margin-top:12px; }
    .mapControls { display:flex; gap:8px; align-items:center; margin-top:8px; }
  </style>
</head>
<body>
<?php require_once __DIR__ . "/../../partials/sidebar.php"; ?>

<main class="main">
  <div class="pageHead"><h1 class="pageHead__title">Location &amp; Map</h1></div>

  <section class="card locCard">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <div>
        <h2 style="margin:0;"><?php echo h($clinic_name); ?></h2>
        <div style="color:#666; margin-top:6px;"><?php echo nl2br(h($clinic_address)); ?></div>
        <?php if ($clinic_phone): ?><div style="margin-top:6px;"><strong>Phone:</strong> <?php echo h($clinic_phone); ?></div><?php endif; ?>
      </div>
      <div>
        <a class="btn" href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($lat . ',' . $lng); ?>" target="_blank" rel="noopener">Open in Maps</a>
      </div>
    </div>

    <div id="clinicMap" role="region" aria-label="Clinic location map"></div>

    <div class="mapControls">
      <label style="margin-right:8px;">Latitude
        <input class="authInput" id="clinicLat" value="<?php echo h($lat); ?>" style="width:140px;">
      </label>
      <label>Longitude
        <input class="authInput" id="clinicLng" value="<?php echo h($lng); ?>" style="width:140px;">
      </label>
      <label>Zoom
        <input class="authInput" id="clinicZoom" value="<?php echo h($zoom); ?>" style="width:70px;">
      </label>
    </div>
  </section>
</main>

<!-- Leaflet JS (load once) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  if (typeof L === 'undefined') {
    document.getElementById('clinicMap').innerText = 'Map cannot be loaded (Leaflet missing).';
    return;
  }

  var lat = parseFloat(<?php echo json_encode($lat); ?>) || 14.5995;
  var lng = parseFloat(<?php echo json_encode($lng); ?>) || 120.9842;
  var zoom = parseInt(<?php echo json_encode($zoom); ?>, 10) || 15;

  var map = L.map('clinicMap').setView([lat, lng], zoom);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  var marker = L.marker([lat, lng], { draggable: false }).addTo(map);

  // update fields if marker exists
  function updateFields() {
    var p = marker.getLatLng();
    document.getElementById('clinicLat').value = p.lat.toFixed(6);
    document.getElementById('clinicLng').value = p.lng.toFixed(6);
    document.getElementById('clinicZoom').value = map.getZoom();
  }
  updateFields();

  // allow manual field edits to recenter (optional)
  document.getElementById('clinicLat').addEventListener('change', function(){
    var la = parseFloat(this.value);
    var lo = parseFloat(document.getElementById('clinicLng').value);
    if (!isNaN(la) && !isNaN(lo)) {
      var p = L.latLng(la, lo);
      marker.setLatLng(p);
      map.setView(p);
      updateFields();
    }
  });
  document.getElementById('clinicLng').addEventListener('change', function(){
    document.getElementById('clinicLat').dispatchEvent(new Event('change'));
  });
  document.getElementById('clinicZoom').addEventListener('change', function(){
    var z = parseInt(this.value, 10);
    if (!isNaN(z)) map.setZoom(z);
  });

  // ensure map renders correctly if container was resized/hidden earlier
  setTimeout(function(){ try { map.invalidateSize(); } catch(e){} }, 200);
})();
</script>
</body>
</html>