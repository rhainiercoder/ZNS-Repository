<?php
// partials/clinic_map_widget.php
require_once __DIR__ . "/../db.php";

/**
 * Local helper to read settings (k/v).
 */
function get_setting_local($conn, $k, $default = '') {
  $s = $conn->prepare("SELECT v FROM settings WHERE k = ? LIMIT 1");
  $s->bind_param("s", $k);
  $s->execute();
  $r = $s->get_result()->fetch_assoc();
  return $r['v'] ?? $default;
}

$lat = (float)get_setting_local($conn, 'clinic_lat', '14.5995');
$lng = (float)get_setting_local($conn, 'clinic_lng', '120.9842');
$zoom = (int)get_setting_local($conn, 'clinic_map_zoom', 15);
$name = get_setting_local($conn, 'clinic_name', 'ZNS Dental Clinic');
$address = get_setting_local($conn, 'clinic_address', '');
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<style>
/* Center the widget and keep it responsive */
.clinic-widget {
  max-width: 920px;
  margin: 0 auto;
  text-align: left;
}
.clinic-widget .widget-head {
  display: flex;
  justify-content: center; /* center title horizontally */
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}
.clinic-widget h3 {
  margin: 0;
  text-align: center;
}
#clinicMapWidget {
  height: 360px;
  border-radius: 6px;
  border: 1px solid #ddd;
  margin-top: 8px;
  width: 100%;
  box-sizing: border-box;
}
.clinic-widget .meta {
  margin-top: 8px;
  text-align: center;
  color: #333;
}
.clinic-widget .meta .addr { color:#666; font-size:13px; }
</style>

<div class="card clinic-widget">
  <div class="widget-head">
    <h3>Clinic location</h3>
  </div>

  <div id="clinicMapWidget"></div>

  <div class="meta">
    <div id="clinicNameWidget" style="font-weight:800;"><?php echo htmlspecialchars($name); ?></div>
    <div id="clinicAddrWidget" class="addr"><?php echo nl2br(htmlspecialchars($address)); ?></div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
(function(){
  if (typeof L === 'undefined') {
    document.getElementById('clinicMapWidget').innerText = 'Map cannot be loaded (Leaflet missing).';
    return;
  }

  // initial server defaults
  var lat = <?php echo json_encode($lat); ?>;
  var lng = <?php echo json_encode($lng); ?>;
  var zoom = <?php echo json_encode($zoom); ?>;

  var map = L.map('clinicMapWidget', { scrollWheelZoom: false }).setView([lat, lng], zoom);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  var marker = L.marker([lat, lng]).addTo(map);
  var nameEl = document.getElementById('clinicNameWidget');
  var addrEl = document.getElementById('clinicAddrWidget');

  function setLocation(newLat, newLng, newZoom, newName, newAddr) {
    var cur = marker.getLatLng();
    var changed = (Math.abs(cur.lat - newLat) > 1e-7) || (Math.abs(cur.lng - newLng) > 1e-7);
    marker.setLatLng([newLat, newLng]);
    if (newZoom && newZoom !== map.getZoom()) map.setView([newLat, newLng], newZoom);
    if (newName) nameEl.textContent = newName;
    if (typeof newAddr === 'string') addrEl.innerHTML = newAddr.replace(/\n/g, '<br>');
    if (changed) {
      var el = document.getElementById('clinicMapWidget');
      el.style.boxShadow = '0 0 0 3px rgba(0,150,136,0.08)';
      setTimeout(function(){ el.style.boxShadow = ''; }, 700);
    }
  }

  // Force size recalculation
  setTimeout(function(){ try { map.invalidateSize(); } catch(e){} }, 200);

  var apiUrl = '/qm/pages/api/clinic_location.php';
  function fetchLocationAndUpdate() {
    fetch(apiUrl, { cache: 'no-store' })
      .then(function(resp){
        if (!resp.ok) throw new Error(resp.status);
        return resp.json();
      })
      .then(function(data){
        if (data && typeof data.lat === 'number' && typeof data.lng === 'number') {
          setLocation(data.lat, data.lng, data.zoom || zoom, data.name || null, data.address || '');
        }
      })
      .catch(function(){ /* ignore errors silently */ });
  }

  // BroadcastChannel instant-update (if supported)
  if (typeof BroadcastChannel !== 'undefined') {
    try {
      var bc = new BroadcastChannel('clinic_location');
      bc.onmessage = function(e){
        var d = e.data;
        if (d && typeof d.lat === 'number' && typeof d.lng === 'number') {
          setLocation(d.lat, d.lng, d.zoom || null, d.name || null, d.address || '');
        }
      };
    } catch (err) {
      // ignore channel errors
    }
  }

  // first fetch then poll
  fetchLocationAndUpdate();
  var POLL_INTERVAL_MS = 15000;
  setInterval(fetchLocationAndUpdate, POLL_INTERVAL_MS);

})();
</script>