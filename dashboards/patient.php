<?php
require_once __DIR__ . "/../auth.php";
$user = require_role(["patient"]);
$role = $user["role"];
$active = "dashboard";

require_once __DIR__ . "/../db.php";
function h($v){ return htmlspecialchars((string)$v); }

$today = date("Y-m-d");

// Services row
$services = $conn->query("
  SELECT id, name
  FROM services
  WHERE is_active = 1
  ORDER BY name ASC
")->fetch_all(MYSQLI_ASSOC);

// Upcoming approved appointment (nearest future)
$stmt = $conn->prepare("
  SELECT
    a.id,
    a.appointment_date,
    a.appointment_time,
    a.status,
    s.name AS service,
    d.name AS dentist_name
  FROM appointments a
  JOIN services s ON s.id = a.service_id
  LEFT JOIN users d ON d.id = a.dentist_id
  WHERE a.patient_id = ?
    AND a.status = 'approved'
    AND a.appointment_date >= ?
  ORDER BY a.appointment_date ASC, a.appointment_time ASC
  LIMIT 1
");
$stmt->bind_param("is", $user["id"], $today);
$stmt->execute();
$upcoming = $stmt->get_result()->fetch_assoc();

// Recent Dental Records (latest 3)
$stmt = $conn->prepare("
  SELECT
    dr.created_at,
    d.name AS dentist_name,
    s.name AS service
  FROM dental_records dr
  JOIN appointments a ON a.id = dr.appointment_id
  JOIN services s ON s.id = a.service_id
  LEFT JOIN users d ON d.id = dr.dentist_id
  WHERE dr.patient_id = ?
  ORDER BY dr.created_at DESC
  LIMIT 3
");
$stmt->bind_param("i", $user["id"]);
$stmt->execute();
$recentRecords = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Service name -> image file mapping (matches your assets/img/services folder)
$serviceImgMap = [
  "Consultation" => "consultation.png",
  "Dental Cleaning" => "cleaning.png",
  "Dental Filling" => "filling.png",
  "Teeth Whitening & Veneers" => "whitening.png",
  "Tooth Extraction & Surgery" => "extraction.png",
];
$fallbackServiceImg = "teeth_icon.png";
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Patient Dashboard - ZNS Dental Clinic</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . "/../partials/sidebar.php"; ?>

<main class="main">
  <div class="pDash">

    <!-- Hero -->
    <section class="pHero">
      <div>
        <h1>Welcome to ZNS<br>Dental Clinic</h1>
        <h2><?php echo h($user["name"]); ?>!</h2>
        <p>Your smile, Our Priority.</p>

        <div class="pHeroActions">
          <a class="btn btn--dark" href="/qm/pages/patient/appointments.php">Make Appointment</a>
          <a class="btn" style="background:#e9f7ff; color:#0b2f4f;" href="/qm/pages/patient/dental-records.php">
            View Dental Record
          </a>
        </div>
      </div>

      <!-- shape placeholder graphic -->
      <div class="pOrb" aria-hidden="true">
        <div class="ring"></div>
        <div class="ring"></div>
        <div class="pLogoShape" aria-label="ZNS logo">
          <img
            class="pLogoImg"
            src="/qm/assets/img/logo.png"
            alt="ZNS logo"
            style="width:100%; height:100%; object-fit:contain; display:block;"
          />
        </div>
      </div>
    </section>

    <!-- Services / My Dental Record -->
    <section>
      <h3 class="pSectionTitle">My Dental Record</h3>
      <div class="pServiceRow">
        <?php
          $i = 0;
          foreach ($services as $s):
            $i++;
            $sid = (int)$s["id"];

            // pick image based on service name (trim helps if DB has trailing spaces)
            $key = trim($s["name"]);
            $imgFile = $serviceImgMap[$key] ?? $fallbackServiceImg;

            // (optional) keep your first card highlighted
            $activeCard = ($i === 1);
        ?>
          <a
            class="pService <?php echo $activeCard ? "pService--active" : ""; ?>"
            href="/qm/pages/patient/dental-records.php?service_id=<?php echo $sid; ?>"
            style="text-decoration:none;"
          >
            <div class="pServiceIcon">
              <img
                src="/qm/assets/img/services/<?php echo h($imgFile); ?>"
                alt=""
                width="22"
                height="22"
                style="display:block; object-fit:contain;"
              />
            </div>
            <div class="pServiceName"><?php echo h($s["name"]); ?></div>
          </a>
        <?php endforeach; ?>

        <?php if (!$services): ?>
          <div class="pService">
            <div class="pServiceIcon">
              <img
                src="/qm/assets/img/services/<?php echo h($fallbackServiceImg); ?>"
                alt=""
                width="22"
                height="22"
                style="display:block; object-fit:contain;"
              />
            </div>
            <div class="pServiceName">No services found</div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- Lower grid -->
    <section class="pGrid2">
      <!-- Upcoming appointment -->
      <div class="pCard">
        <div class="pRow">
          <div>
            <h3 class="pSectionTitle" style="margin:0 0 4px;">My Upcoming Appointment</h3>
            <div class="pMuted">Approved appointments</div>
          </div>
          <span class="pPill">Patient</span>
        </div>

        <?php if ($upcoming): ?>
          <div class="pUpcomingMeta">
            <div>📅 <?php echo h($upcoming["appointment_date"]); ?> — <?php echo h(substr($upcoming["appointment_time"],0,5)); ?></div>
            <div>🦷 <?php echo h($upcoming["service"]); ?></div>
            <div>👨‍⚕️ <?php echo h($upcoming["dentist_name"] ?: "Assigned dentist"); ?></div>
          </div>

          <div style="display:flex; justify-content:flex-end; margin-top:12px;">
            <a class="btn btn--dark" href="/qm/pages/patient/appointments.php">View</a>
          </div>
        <?php else: ?>
          <div style="margin-top:12px; font-weight:900; opacity:.75;">
            No upcoming approved appointment yet.
          </div>
          <div style="display:flex; justify-content:flex-end; margin-top:12px;">
            <a class="btn btn--dark" href="/qm/pages/patient/appointments.php">Make Appointment</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Recent dental records (synced) -->
      <div class="pCard">
        <h3 class="pSectionTitle">Recent Dental Records</h3>

        <div class="pMiniList">
          <?php foreach ($recentRecords as $r): ?>
            <div class="pMiniItem">
              <div class="avatarShape"></div>
              <div>
                <b><?php echo h($r["dentist_name"] ?: "Dentist"); ?></b>
                <small><?php echo h($r["service"] ?: "Dental Service"); ?></small>
              </div>
            </div>
          <?php endforeach; ?>

          <?php if (!$recentRecords): ?>
            <div style="font-weight:900; opacity:.75;">
              No dental records yet.
            </div>
          <?php endif; ?>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-top:10px;">
          <a class="btn btn--dark btn--xs" href="/qm/pages/patient/dental-records.php">View All</a>
        </div>
      </div>

      <!-- Notifications (placeholder) -->
      <div class="pCard">
        <h3 class="pSectionTitle">Notifications</h3>
        <div class="pMiniItem">
          <div class="pServiceIcon" style="width:42px;height:42px;border-radius:14px;">🔔</div>
          <div>
            <b>1 New Reminder</b>
            <small>Your next appointment will appear here after approval.</small>
          </div>
        </div>
      </div>

      <!-- Clinic location --> 
      <style>
        .pCard.mapCard { max-width: 480px; padding: 12px; }
        .pCard.mapCard .clinic-widget { max-width: 100%; margin: 0; box-shadow: none; background: transparent; } 
        .pCard.mapCard #clinicMapWidget { height: 180px !important; border-radius: 8px !important; width: 100% !important; border: 1px solid rgba(11,31,42,0.08) 
        !important; box-sizing: border-box !important; } /* adjust the clinic name/address typography if needed */ 
        .pCard.mapCard .clinic-widget .meta { margin-top:8px; font-weight:700; font-size:13px; } /* responsive tweak: full width on narrow screens */ 
        @media (max-width:600px) { 
        .pCard.mapCard { max-width: 100%; padding:10px; } 
        .pCard.mapCard #clinicMapWidget { height: 160px !important; } } 
      </style> <div class="pCard mapCard"> 
        <?php require_once __DIR__ . "/../partials/clinic_map_widget.php"; ?> </div>
    </section>

  </div>
</main>
</body>
</html>