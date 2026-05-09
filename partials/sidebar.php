<?php
// expects: $role (string)
if (!isset($role) || $role === "") {
    http_response_code(500);
    die("Sidebar role not set.");
}

$active = $active ?? ""; // "dashboard", "patients", "appointments", etc.
function active_class(string $key, string $active): string {
    return $key === $active ? " is-active" : "";
}
?>
<button class="sidebarToggle" type="button" onclick="document.body.classList.toggle('sidebar-open')">
  ☰
  </button>

<aside class="sidebar">
  <div class="sidebar__brand">
    <div class="sidebar__logo"><img src="/assets/img/logo.png" alt="ZNS" class="sidebarLogo" height=200 width=200></div>
  </div>

  <nav class="sidebar__nav">
    <?php if ($role === "admin" || $role === "staff"): ?>
      <a class="sidebar__link<?php echo active_class("dashboard", $active); ?>" href="/dashboards/admin.php">Dashboard</a>
      <a class="sidebar__link<?php echo active_class("patients", $active); ?>" href="/pages/admin/patients.php">Patients</a>
      <a class="sidebar__link<?php echo active_class("dentists", $active); ?>" href="/pages/admin/dentists.php">Dentist</a>
      <a class="sidebar__link<?php echo active_class("appointments", $active); ?>" href="/pages/admin/appointments.php">Appointments</a>
      <a class="sidebar__link<?php echo active_class("records", $active); ?>" href="/pages/admin/dental-records.php">Dental Records</a>
      <a class="sidebar__link<?php echo active_class("transactions", $active); ?>" href="/pages/admin/transactions.php">Transactions</a>
      <a class="sidebar__link<?php echo active_class("reports", $active); ?>" href="/pages/admin/reports.php">Reports</a>
      <a class="sidebar__link<?php echo active_class("location", $active); ?>" href="/pages/admin/location.php">Location &amp; Map</a>
       <a class="sidebar__link<?php echo active_class("settings", $active); ?>" href="/pages/admin/settings_services.php">Services</a>
      <a class="sidebar__link<?php echo active_class("settings", $active); ?>" href="/pages/admin/settings.php">Settings</a>

    <?php elseif ($role === "dentist"): ?>
      <a class="sidebar__link<?php echo active_class("dashboard", $active); ?>" href="/dashboards/dentist.php">Dashboard</a>
      <a class="sidebar__link<?php echo active_class("today", $active); ?>" href="/pages/dentist/today.php">Today's Patient</a>
      <a class="sidebar__link<?php echo active_class("records", $active); ?>" href="/pages/dentist/dental-records.php">Dental Records</a>
      <a class="sidebar__link<?php echo active_class("transactions", $active); ?>" href="/pages/dentist/transactions.php">Transaction History</a>
      <a class="sidebar__link<?php echo active_class("location", $active); ?>" href="/pages/dentist/location.php">Location &amp; Map</a>
      <a class="sidebar__link<?php echo active_class("settings", $active); ?>" href="/pages/dentist/settings.php">Settings</a>

    <?php elseif ($role === "patient"): ?>
      <a class="sidebar__link<?php echo active_class("dashboard", $active); ?>" href="/dashboards/patient.php">Dashboard</a>
      <a class="sidebar__link<?php echo active_class("appointments", $active); ?>" href="/pages/patient/appointments.php">My Appointments</a>
      <a class="sidebar__link<?php echo active_class("records", $active); ?>" href="/pages/patient/dental-records.php">My Dental Record</a>
      <a class="sidebar__link<?php echo active_class("payments", $active); ?>" href="/pages/patient/payments.php">Payment History</a>
      <a class="sidebar__link<?php echo active_class("location", $active); ?>" href="/pages/patient/location.php">Location &amp; Map</a>
      <a class="sidebar__link<?php echo active_class("settings", $active); ?>" href="/pages/patient/settings.php">Settings</a>

      <?php elseif ($role === "receptionist"): ?>
      <a class="sidebar__link<?php echo active_class("dashboard", $active); ?>" href="/dashboards/receptionist.php">Dashboard</a>
      <a class="sidebar__link<?php echo active_class("transactions", $active); ?>" href="/pages/receptionist/transactions.php">Transactions</a>
      <a class="sidebar__link<?php echo active_class("walkins", $active); ?>" href="/pages/receptionist/walkins.php">Walk-in Records</a>
       <?php elseif ($role === "patient"): ?>

    <?php else: ?>
      <?php http_response_code(403); die("Forbidden"); ?>
    <?php endif; ?>
  </nav>

  <div class="sidebar__footer">
    <a class="sidebar__logout" href="/logout.php">Log out</a>
  </div>
</aside>
<script>
document.addEventListener('click', (e) => {
  if (!document.body.classList.contains('sidebar-open')) return;
  const sidebar = document.querySelector('.sidebar');
  const toggle = document.querySelector('.sidebarToggle');
  if (sidebar.contains(e.target) || toggle.contains(e.target)) return;
  document.body.classList.remove('sidebar-open');
});
</script>
