<?php
require_once __DIR__ . "/../db.php";

function h($v){ return htmlspecialchars((string)$v); }

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
$zoom = (int)get_setting($conn, 'clinic_map_zoom', '15');
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Location - <?php echo h($clinic_name); ?></title>
<link rel="stylesheet" href="/qm/assets/css/style.css">

<!-- Leaflet CSS (only once) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<style>
  #clinicMap { height:420px; border:1px solid #ddd; border-radius:6px; }
  .locCard { max-width:900px; margin:18px auto; padding:12px; }
</style>
</head>
<body>
<?php include __DIR__ . "/../partials/header.php"; /* or sidebar if you want */ ?>
<main class="main">
  <section class="card locCard">
    <h1><?php echo h($clinic_name); ?></h1>
    <p><?php echo nl2br(h($clinic_address)); ?></p>
    <p><strong>Phone:</strong> <?php echo h($clinic_phone); ?></p>
    <div id="clinicMap" role="region" aria-label="Clinic location map"></div>
  </section>
</main>

<!-- Leaflet JS (only once, before map init) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  (function(){
    if (typeof L === 'undefined') {
      console.error('Leaflet did not load. Check network/console for errors.');
      document.getElementById('clinicMap').innerText = 'Map cannot be loaded (Leaflet missing).';
      return;
    }

    var lat = parseFloat(<?php echo json_encode($lat); ?>) || 14.5995;
    var lng = parseFloat(<?php echo json_encode($lng); ?>) || 120.9842;
    var zoom = <?php echo json_encode($zoom); ?> || 15;

    var map = L.map('clinicMap').setView([lat, lng], zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
      maxZoom:19, attribution:'&copy; OpenStreetMap contributors'
    }).addTo(map);

    L.marker([lat,lng]).addTo(map);

    // Force Leaflet to recalc size in case the container was hidden or the CSS changed
    setTimeout(function(){ try { map.invalidateSize(); } catch(e){} }, 200);
  })();
</script>
</body>
</html>