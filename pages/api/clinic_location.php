<?php
// pages/api/clinic_location.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . "/../../db.php";

/**
 * Get a single setting value from settings table
 */
function get_setting($conn, $k, $default = '') {
  $s = $conn->prepare("SELECT v FROM settings WHERE k = ? LIMIT 1");
  $s->bind_param("s", $k);
  $s->execute();
  $r = $s->get_result()->fetch_assoc();
  return $r['v'] ?? $default;
}

$lat = get_setting($conn, 'clinic_lat', '14.5995');
$lng = get_setting($conn, 'clinic_lng', '120.9842');
$zoom = (int)get_setting($conn, 'clinic_map_zoom', 15);
$name = get_setting($conn, 'clinic_name', 'ZNS Dental Clinic');
$address = get_setting($conn, 'clinic_address', '');

echo json_encode([
  'lat' => (float)$lat,
  'lng' => (float)$lng,
  'zoom' => (int)$zoom,
  'name' => $name,
  'address' => $address
], JSON_UNESCAPED_UNICODE);