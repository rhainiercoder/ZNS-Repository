<?php
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../db.php";

$user = require_role(["admin", "staff"]);
$role = $user["role"];
$active = "dashboard";

function h($v){ return htmlspecialchars((string)$v); }

/* Helper to read settings (k/v) */
function get_setting($conn, $k, $default = '') {
    $s = $conn->prepare("SELECT v FROM settings WHERE k = ? LIMIT 1");
    if ($s) {
        $s->bind_param("s", $k);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        return $r['v'] ?? $default;
    }
    return $default;
}

/* ---------- Stats ---------- */
$today = date("Y-m-d");

// Today's appointments (approved + pending for today)
$todayAppointments = 0;
try {
    $stmt = $conn->prepare("
      SELECT COUNT(*) AS c
      FROM appointments
      WHERE appointment_date = ?
        AND status IN ('approved','pending')
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $todayAppointments = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
} catch (Exception $e) {
    // appointments table might not exist yet — keep default 0
}

// Pending approvals (all pending)
$pendingApprovals = 0;
try {
    $res = $conn->query("SELECT COUNT(*) AS c FROM appointments WHERE status = 'pending'");
    $pendingApprovals = (int)($res->fetch_assoc()['c'] ?? 0);
} catch (Exception $e) {
    // ignore
}

// Available dentists (count users with role = dentist)
// --- Clinic timezone & clinic "day" (rollover at 08:00) ---
$clinicDate = null;
try {
    // Attempt to read clinic_day_start and timezone from settings (fallback to hardcoded)
    $clinic_day_start = get_setting($conn, 'clinic_day_start', '08:00');
    $clinic_timezone = get_setting($conn, 'clinic_timezone', 'Asia/Manila');
    // If you have a helper get_clinic_date, use it; otherwise compute using timezone and day start.
    if (function_exists('get_clinic_date')) {
        $clinicDate = get_clinic_date($clinic_day_start, $clinic_timezone);
    } else {
        // Basic fallback: treat clinic day as server date (not considering rollover)
        $clinicDate = date("Y-m-d");
    }
} catch (Exception $e) {
    $clinicDate = date("Y-m-d");
}
$clinicWeekday = (int)date('N', strtotime($clinicDate)); // 1=Mon .. 7=Sun

// --- Option A: Available dentists (scheduled at any time today) ---
$availableDentists = 0;
try {
    // If you have a holidays table, treat holidays as zero available
    $hCount = 0;
    try {
        $stmtH = $conn->prepare("SELECT COUNT(*) AS c FROM holidays WHERE date = ? LIMIT 1");
        $stmtH->bind_param("s", $clinicDate);
        $stmtH->execute();
        $hCount = (int)($stmtH->get_result()->fetch_assoc()['c'] ?? 0);
    } catch (Exception $e) {
        // no holidays table -> ignore
        $hCount = 0;
    }

    if ($hCount > 0) {
        $availableDentists = 0;
    } else {
        // count distinct dentists who have availability rows for this weekday
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT dentist_id) AS c FROM dentist_availability WHERE `day` = ?");
        $stmt->bind_param("i", $clinicWeekday);
        $stmt->execute();
        $availableDentists = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);

        // Fallback: if availability table missing or returned 0, show total dentists (backwards compatible)
        if ($availableDentists === 0) {
            $res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'dentist'");
            $availableDentists = (int)($res->fetch_assoc()['c'] ?? 0);
        }
    }
} catch (Exception $e) {
    // generic fallback on any error
    $res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'dentist'");
    $availableDentists = (int)($res->fetch_assoc()['c'] ?? 0);
}

// Clinic working hours: try a settings table fallback, otherwise default
$clinicHours = "9:00 – 18:00";
try {
    $clinicHoursSetting = get_setting($conn, 'clinic_hours', '');
    if (!empty($clinicHoursSetting)) $clinicHours = $clinicHoursSetting;
} catch (Exception $e) {
    // settings table may not exist — keep default
}

/* ---------- Dentist schedule (support ?ym=YYYY-M navigation) ---------- */
// Use requested month/year if present, otherwise current month
if (!empty($_GET['ym']) && preg_match('/^\d{4}-\d{1,2}$/', $_GET['ym'])) {
    list($year, $month) = array_map('intval', explode('-', $_GET['ym']));
    if ($month < 1 || $month > 12) {
        $year = (int)date('Y'); $month = (int)date('n');
    }
} else {
    $year  = (int)date('Y');
    $month = (int)date('n');
}
// --- Appointment counts for this month (approved + pending) ---
$apptCountByDate = []; // ['YYYY-MM-DD' => 3]
try {
    $stmt = $conn->prepare("
      SELECT appointment_date, COUNT(*) AS c
      FROM appointments
      WHERE YEAR(appointment_date) = ?
        AND MONTH(appointment_date) = ?
        AND status IN ('approved','pending')
      GROUP BY appointment_date
    ");
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $apptCountByDate[$r['appointment_date']] = (int)$r['c'];
    }
} catch (Exception $e) {
    $apptCountByDate = [];
}
$firstOfMonth = new DateTime(sprintf('%04d-%02d-01', $year, $month));
$monthDays = (int)$firstOfMonth->format('t'); // days in month

// prev / next month values for links
$prevDt = (clone $firstOfMonth)->modify('-1 month');
$nextDt = (clone $firstOfMonth)->modify('+1 month');
$prev_ym = $prevDt->format('Y-n'); // non-zero-padded month
$next_ym = $nextDt->format('Y-n');

// precompute availability counts per weekday (1=Mon..7=Sun)
$availByWeekday = array_fill(1,7,0);
try {
    $res = $conn->query("SELECT `day`, COUNT(DISTINCT dentist_id) AS c FROM dentist_availability GROUP BY `day`");
    while ($r = $res->fetch_assoc()) {
        $d = (int)$r['day'];
        $availByWeekday[$d] = (int)$r['c'];
    }
} catch (Exception $e) {
    // table may not exist -> keep zeros
}

// Optional holidays override (dates that should be shown as holiday)
$holidays = [];
try {
    $res = $conn->query("SELECT date FROM holidays WHERE YEAR(date) = " . (int)$year . " AND MONTH(date) = " . (int)$month);
    while ($r = $res->fetch_assoc()) {
        $holidays[$r['date']] = true;
    }
} catch (Exception $e) {
    // no holidays table — ignore
}

/* ---------- Recent transactions (latest 6) ---------- */
$recentTransactions = [];
try {
    $stmt = $conn->prepare("
      SELECT t.id, t.amount, t.type, t.status, t.note, t.created_at, u.name AS user_name
      FROM transactions t
      LEFT JOIN users u ON u.id = t.user_id
      ORDER BY t.created_at DESC
      LIMIT 6
    ");
    $stmt->execute();
    $recentTransactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // transactions table may not exist — leave empty
}
$incomeByDentist = [];
$salaryFrom = $_GET['salary_from'] ?? date('Y-m-01');
$salaryTo = $_GET['salary_to'] ?? date('Y-m-t');
$salaryRate = max(0, (float)($_GET['salary_rate'] ?? 500));
$salaryByDentist = [];
try {
  $stmt = $conn->prepare("
    SELECT
      d.id AS dentist_id,
      d.name AS dentist_name,
      COALESCE(SUM(t.amount), 0) AS total_income,
      COUNT(DISTINCT a.id) AS total_appointments,
      COUNT(DISTINCT a.patient_id) AS unique_patients
    FROM users d
    LEFT JOIN appointments a ON a.dentist_id = d.id
    LEFT JOIN transactions t
      ON t.appointment_id = a.id
     AND t.type = 'payment'
     AND t.status = 'success'
    WHERE d.role = 'dentist'
    GROUP BY d.id, d.name
    ORDER BY total_income DESC, total_appointments DESC
  ");
  $stmt->execute();
  $incomeByDentist = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
  $incomeByDentist = [];
}

try {
  $stmt = $conn->prepare("
    SELECT
      d.id AS dentist_id,
      d.name AS dentist_name,
      COUNT(DISTINCT a.id) AS accommodated_patients,
      COALESCE(SUM(CASE WHEN t.type = 'payment' AND t.status = 'success' THEN t.amount ELSE 0 END), 0) AS paid_revenue
    FROM users d
    LEFT JOIN appointments a
      ON a.dentist_id = d.id
     AND a.appointment_date BETWEEN ? AND ?
     AND a.status IN ('approved','completed')
    LEFT JOIN transactions t
      ON t.appointment_id = a.id
     AND t.type = 'payment'
     AND t.status = 'success'
    WHERE d.role = 'dentist'
    GROUP BY d.id, d.name
    ORDER BY accommodated_patients DESC, paid_revenue DESC, d.name ASC
  ");
  $stmt->bind_param("ss", $salaryFrom, $salaryTo);
  $stmt->execute();
  $salaryByDentist = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
  $salaryByDentist = [];
}
?>


<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>
</head>
<body>
  <?php include __DIR__ . "/../partials/sidebar.php"; ?>

  <main class="main">
    <div class="pageHead">
      <h1 class="pageHead__title">Welcome Back Admin <?php echo h($user["name"] ?? ""); ?>!</h1>
    </div>
    <?php $showApptPopup = ($pendingApprovals > 0 || $todayAppointments > 0); ?>
<?php if ($showApptPopup): ?>
  <div id="apptModalOverlay" style="position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; z-index:9999;"></div>
  <div id="apptModal" style="position:fixed; left:50%; top:16%; transform:translateX(-50%); width:min(520px, 92vw);
    background:#fff; border-radius:16px; padding:16px; box-shadow:0 10px 30px rgba(0,0,0,.25); display:none; z-index:10000;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
      <div>
        <div style="font-weight:1000; font-size:18px; color:#0b2f4f;">Today’s Summary</div>
        <div style="font-weight:800; opacity:.75; margin-top:2px;">Appointments & approvals</div>
      </div>
      <button type="button" id="apptModalClose" class="btn" style="background:#eef3f7;">Close</button>
    </div>

    <div style="display:grid; gap:10px; margin-top:12px;">
      <div class="card" style="background:#e9f7ff; margin:0; font-weight:900;">
        📋 Today's Appointments: <?php echo h($todayAppointments); ?>
      </div>
      <div class="card" style="background:#fff3d6; margin:0; font-weight:900;">
        ⏳ Pending Approvals: <?php echo h($pendingApprovals); ?>
      </div>
    </div>

    <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:14px;">
      <a class="btn btn--dark" href="/pages/admin/appointments.php">Open Appointments</a>
    </div>
  </div>

  <script>
  (function(){
    const overlay = document.getElementById('apptModalOverlay');
    const modal = document.getElementById('apptModal');
    const closeBtn = document.getElementById('apptModalClose');
    if (!overlay || !modal || !closeBtn) return;

    function close(){
      overlay.style.display = 'none';
      modal.style.display = 'none';
    }
    overlay.addEventListener('click', close);
    closeBtn.addEventListener('click', close);

    overlay.style.display = 'block';
    modal.style.display = 'block';
  })();
  </script>
  <?php endif; ?>

    



    <!-- Top stats -->
    <section class="grid grid-2 adminStats">
      <div class="card statCard">
        <div class="statCard__icon">📋</div>
        <div class="statCard__label">Today's Appointments</div>
        <div class="statCard__value"><?php echo h($todayAppointments); ?></div>
      </div>

      <div class="card statCard">
        <div class="statCard__icon">⏳</div>
        <div class="statCard__label">Pending Approvals</div>
        <div class="statCard__value"><?php echo h($pendingApprovals); ?></div>
      </div>

      <div class="card statCard">
        <div class="statCard__icon">🦷</div>
        <div class="statCard__label">Available Dentists</div>
        <div class="statCard__value"><?php echo h($availableDentists); ?></div>
      </div>

      <div class="card statCard">
        <div class="statCard__icon">🕘</div>
        <div class="statCard__label">Clinic Working Hours</div>
        <div class="statCard__value statCard__value--small"><?php echo h($clinicHours); ?></div>
      </div>
    </section>

    <!-- Dentist Schedule -->

      <div id="adminCalendar"></div>
    </div>
    <section class="card adminSchedule">
  <div class="adminSchedule__layout">
    <!-- LEFT -->
    <div class="adminSchedule__left">
      <h2 class="adminSchedule__title">Dentist Schedule</h2>

    <!-- RIGHT -->
    <div class="adminSchedule__right">
      <div class="calendar">
        <div class="calendar__bar">
        <div class="calendar__barLeft">
          <a class="calendar__nav" href="?ym=<?php echo h($prev_ym); ?>" aria-label="Previous month">‹</a>
          <div class="calendar__month"><?php echo h($firstOfMonth->format('F Y')); ?></div>
          <a class="calendar__nav" href="?ym=<?php echo h($next_ym); ?>" aria-label="Next month">›</a>
        </div>

        <div class="calendar__barRight">
          <div class="legend legend--row legend--compact">
            <span class="legend__item"><span class="dot dot--blue"></span>Appointments</span>
            <span class="legend__item"><span class="dot dot--green"></span>On Duty</span>
            <span class="legend__item"><span class="dot dot--red"></span>Off Duty</span>
            <span class="legend__item"><span class="dot dot--orange"></span>Holiday</span>
          </div>
        </div>
      </div>

        <div class="calendar__dow">
          <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
        </div>

        <div class="calendar__grid">
          <?php
          // build cells Sun..Sat for the month (include leading blanks)
          $startWeekday = (int)$firstOfMonth->format('w'); // 0 (Sun) .. 6 (Sat)
          $cells = [];

          for ($i = 0; $i < $startWeekday; $i++) $cells[] = null;
          for ($d = 1; $d <= $monthDays; $d++) $cells[] = $d;

          foreach ($cells as $cell) {
              if ($cell === null) {
                  echo '<div></div>';
                  continue;
              }

              $dateStr = sprintf("%04d-%02d-%02d", (int)$year, (int)$month, $cell);
              $weekday = (int)date('N', strtotime($dateStr)); // 1..7 (Mon..Sun)
              $isHoliday = !empty($holidays[$dateStr]);
              $availCount = $availByWeekday[$weekday] ?? 0;

              // Force Sunday off duty (RED)
              $isSunday = ((int)date('w', strtotime($dateStr)) === 0); // 0 = Sunday

              if ($isSunday) {
                  $cls = 'red';
              } else {
                  $cls = $isHoliday ? 'orange' : ($availCount > 0 ? 'green' : 'red');
              }

              $apptCount = (int)($apptCountByDate[$dateStr] ?? 0);

              echo '<div class="day day--' . $cls . '">';
              echo   '<div class="day__num">' . $cell . '</div>';
              if ($apptCount > 0) {
                  echo '<div class="day__apptBadge">' . $apptCount . '</div>';
              }
              echo '</div>';
          }
          ?>
        </div>

        <div class="calendar__note">Reminder: Arrive 1 Hour Early</div>
      </div>
    </div>
  </div>
</section>
    <section class="card adminTransactions" style="margin-bottom:18px;">
      <div class="adminTransactions__head">
        <h2 class="adminTransactions__title">Dentist Salary Calculator</h2>
      </div>

      <form method="get" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap; margin-bottom:12px;">
        <label>
          <div style="font-weight:900; color:#0b2f4f; margin-bottom:6px;">From</div>
          <input class="authInput" type="date" name="salary_from" value="<?php echo h($salaryFrom); ?>">
        </label>
        <label>
          <div style="font-weight:900; color:#0b2f4f; margin-bottom:6px;">To</div>
          <input class="authInput" type="date" name="salary_to" value="<?php echo h($salaryTo); ?>">
        </label>
        <label>
          <div style="font-weight:900; color:#0b2f4f; margin-bottom:6px;">Rate per patient</div>
          <input class="authInput" type="number" min="0" step="0.01" name="salary_rate" value="<?php echo h(number_format($salaryRate, 2, '.', '')); ?>">
        </label>
        <button class="btn btn--dark" type="submit">Compute Salaries</button>
      </form>

      <div class="table">
        <div class="table__row table__row--head" style="grid-template-columns: 1.2fr .6fr .7fr .7fr;">
          <div>Dentist</div>
          <div>Patients</div>
          <div style="text-align:right;">Paid Revenue</div>
          <div style="text-align:right;">Computed Salary</div>
        </div>

        <?php foreach ($salaryByDentist as $s): ?>
          <?php $computedSalary = (int)$s['accommodated_patients'] * $salaryRate; ?>
          <div class="table__row" style="grid-template-columns: 1.2fr .6fr .7fr .7fr;">
            <div style="font-weight:900; color:#0b2f4f;"><?php echo h($s['dentist_name']); ?></div>
            <div class="table__muted"><?php echo h($s['accommodated_patients']); ?></div>
            <div class="table__right">PHP <?php echo number_format((float)$s['paid_revenue'], 2); ?></div>
            <div class="table__right">PHP <?php echo number_format($computedSalary, 2); ?></div>
          </div>
        <?php endforeach; ?>

        <?php if (!$salaryByDentist): ?>
          <div class="table__row"><div style="grid-column:1 / -1; font-weight:900; opacity:.75;">No dentists found.</div></div>
        <?php endif; ?>
      </div>
    </section>

    <section class="card adminTransactions">
      <div class="adminTransactions__head">
        <h2 class="adminTransactions__title">Recent Transactions</h2>
      </div>

      <div class="adminTransactions__box">
        <?php if ($recentTransactions): ?>
          <div style="display:grid; gap:6px;">
            <?php foreach ($recentTransactions as $t): ?>
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-weight:800;">
                  <?php echo h($t['user_name'] ?: 'Guest'); ?>
                  <div style="font-weight:700; opacity:.7; font-size:12px;"><?php echo h($t['type']); ?><?php if (!empty($t['note'])) echo " • " . h($t['note']); ?></div>
                </div>
                <div style="text-align:right;">
                  <div style="font-weight:900; color:<?php echo ($t['type'] === 'refund' ? '#e64545' : '#0b2f4f'); ?>;">
                    ₱<?php echo number_format((float)$t['amount'], 2); ?>
                  </div>
                  <div style="font-size:12px; opacity:.75;"><?php echo h(date('Y-m-d H:i', strtotime($t['created_at']))); ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="padding:14px; font-weight:800; opacity:.8;">No recent transactions.</div>
        <?php endif; ?>
      </div>

      <div class="adminTransactions__actions">
        <a class="btn btn--dark" href="/pages/admin/transactions.php">View All Transactions</a>
      </div>
    </section>
  </main>
  <script>
document.addEventListener('DOMContentLoaded', function() {
  var calendarEl = document.getElementById('adminCalendar');

  var themeBlue = getComputedStyle(document.documentElement).getPropertyValue('--blue').trim() || '#2f63e0';

var calendar = new FullCalendar.Calendar(calendarEl, {
  initialView: 'dayGridMonth',
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
  },
  height: 700,
  nowIndicator: true,
  navLinks: true,
  timeZone: 'local',
  events: 'pages/api/calendar_appointments.php',

  displayEventTime: true,
  eventDisplay: 'block',
  dayMaxEvents: true,

  eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },

  // Sunday off shading (keep your datesSet code)
    businessHours: {
      daysOfWeek: [1, 2, 3, 4, 5, 6] // Monday..Saturday; Sunday excluded
    },
    dayMaxEventRows: true,
    displayEventTime: true,
    eventDidMount: function(info) {
      info.el.style.cursor = 'pointer';
    },
    eventClick: function(info) {
      info.jsEvent.preventDefault();
      // open the URL in a new tab if event contains a URL
      if (info.event.url) {
        window.open(info.event.url, '_blank');
      }
    },
    datesSet: function(dateInfo) {
  var src = calendar.getEventSourceById && calendar.getEventSourceById('sundays-bg');
  if (src) src.remove();

  var start = new Date(dateInfo.start);
  var end = new Date(dateInfo.end);
  var bgEvents = [];

  // normalize time to midnight para di pumalya timezone
  start.setHours(0,0,0,0);

  for (var d = new Date(start); d < end; d.setDate(d.getDate() + 1)) {
    if (d.getDay() === 0) { // Sunday
      var iso = d.toISOString().slice(0, 10);

      bgEvents.push({
        start: iso,
        end: iso, // FullCalendar treats background event as whole day for dayGrid
        allDay: true,
        display: 'background',
        backgroundColor: '#eef4ff'
      });
    }
  }

  if (bgEvents.length) {
    calendar.addEventSource({ id: 'sundays-bg', events: bgEvents });
  }
}
  });

  calendar.render();
});
</script>
</body>
</html>
