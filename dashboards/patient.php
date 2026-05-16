<?php
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../db.php";

$user = require_role(["patient"]);
$role = $user["role"];
$active = "dashboard";


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
    COALESCE(NULLIF(s.price, 0), 1200.00) AS amount,
    MAX(CASE WHEN t.id IS NOT NULL THEN 1 ELSE 0 END) AS is_paid,
    d.name AS dentist_name
  FROM appointments a
  JOIN services s ON s.id = a.service_id
  LEFT JOIN users d ON d.id = a.dentist_id
  LEFT JOIN transactions t
    ON t.appointment_id = a.id
   AND t.user_id = a.patient_id
   AND t.type = 'payment'
   AND t.status = 'success'
  WHERE a.patient_id = ?
    AND a.status = 'approved'
    AND a.appointment_date >= ?
  GROUP BY a.id, a.appointment_date, a.appointment_time, a.status, s.name, s.price, d.name
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

// Pending appointment requests for notifications.
$stmt = $conn->prepare("
  SELECT
    a.id,
    a.appointment_date,
    a.appointment_time,
    s.name AS service
  FROM appointments a
  JOIN services s ON s.id = a.service_id
  WHERE a.patient_id = ?
    AND a.status = 'pending'
  ORDER BY a.appointment_date ASC, a.appointment_time ASC
  LIMIT 2
");
$stmt->bind_param("i", $user["id"]);
$stmt->execute();
$pendingAppointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$notifications = [];
if ($upcoming && (int)$upcoming["is_paid"] !== 1) {
  $notifications[] = [
    "title" => "Payment Needed",
    "body" => "Pay your approved " . $upcoming["service"] . " appointment before treatment can be completed.",
    "href" => "/pages/patient/payments.php",
    "icon" => "PHP",
  ];
} elseif ($upcoming) {
  $notifications[] = [
    "title" => "Appointment Ready",
    "body" => $upcoming["service"] . " is scheduled on " . $upcoming["appointment_date"] . " at " . substr($upcoming["appointment_time"], 0, 5) . ".",
    "href" => "/pages/patient/appointments.php",
    "icon" => "OK",
  ];
}

foreach ($pendingAppointments as $pending) {
  $notifications[] = [
    "title" => "Awaiting Approval",
    "body" => $pending["service"] . " request for " . $pending["appointment_date"] . " is still pending.",
    "href" => "/pages/patient/appointments.php",
    "icon" => "...",
  ];
}

if ($recentRecords) {
  $notifications[] = [
    "title" => "Record Updated",
    "body" => "Your latest dental record is available for " . ($recentRecords[0]["service"] ?: "your appointment") . ".",
    "href" => "/pages/patient/dental-records.php",
    "icon" => "DR",
  ];
}

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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Dashboard - ZNS Dental Clinic</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="app">
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
          <a class="btn btn--dark" href="/pages/patient/appointments.php">Make Appointment</a>
          <a class="btn" style="background:#e9f7ff; color:#0b2f4f;" href="/pages/patient/dental-records.php">
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
            src="/assets/img/logo.png"
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
            href="/pages/patient/dental-records.php?service_id=<?php echo $sid; ?>"
            style="text-decoration:none;"
          >
            <div class="pServiceIcon">
              <img
                src="/assets/img/services/<?php echo h($imgFile); ?>"
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
                src="/assets/img/services/<?php echo h($fallbackServiceImg); ?>"
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
            <?php if ((int)$upcoming["is_paid"] === 1): ?>
              <div style="color:#15803d;">Payment status: Paid</div>
            <?php else: ?>
              <div style="color:#b42318;">Payment required before your appointment can be completed.</div>
              <div>Amount due: â‚±<?php echo number_format((float)$upcoming["amount"], 2); ?></div>
            <?php endif; ?>
          </div>

          <div style="display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap; margin-top:12px;">
            <?php if ((int)$upcoming["is_paid"] !== 1): ?>
              <a class="btn" style="background:#e64545;color:#fff;" href="/pages/patient/payments.php">Pay Now</a>
            <?php endif; ?>
            <a class="btn btn--dark" href="/pages/patient/appointments.php">View</a>
          </div>
        <?php else: ?>
          <div style="margin-top:12px; font-weight:900; opacity:.75;">
            No upcoming approved appointment yet.
          </div>
          <div style="display:flex; justify-content:flex-end; margin-top:12px;">
            <a class="btn btn--dark" href="/pages/patient/appointments.php">Make Appointment</a>
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
          <a class="btn btn--dark btn--xs" href="/pages/patient/dental-records.php">View All</a>
        </div>
      </div>

      <!-- Notifications -->
      <div class="pCard">
        <h3 class="pSectionTitle">Notifications</h3>
        <?php if ($notifications): ?>
          <div class="pMiniList">
            <?php foreach (array_slice($notifications, 0, 4) as $n): ?>
              <a class="pMiniItem" href="<?php echo h($n["href"]); ?>" style="text-decoration:none;">
                <div class="pServiceIcon" style="width:42px;height:42px;border-radius:14px;font-size:12px;font-weight:900;">
                  <?php echo h($n["icon"]); ?>
                </div>
                <div>
                  <b><?php echo h($n["title"]); ?></b>
                  <small><?php echo h($n["body"]); ?></small>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
        <div class="pMiniItem">
          <div class="pServiceIcon" style="width:42px;height:42px;border-radius:14px;">🔔</div>
          <div>
            <b>All Clear</b>
            <small>No appointments need your attention right now.</small>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Clinic location -->
      <div class="pCard mapCard">
        <?php require_once __DIR__ . "/../partials/clinic_map_widget.php"; ?>
      </div>
    </section>

  </div>
</main>
</body>
</html>
