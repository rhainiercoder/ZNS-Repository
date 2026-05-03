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
<aside class="sidebar">
  <div class="sidebar__brand">
    <div class="sidebar__logo"><img src="/qm/assets/img/logo.png" alt="ZNS" class="sidebarLogo" height=200 width=200></div>
  </div>

  <nav class="sidebar__nav">
    <?php if ($role === "admin" || $role === "staff"): ?>
      <a class="sidebar__link<?php echo active_class("dashboard", $active); ?>" href="/qm/dashboards/admin.php">Dashboard</a>
      <a class="sidebar__link<?php echo active_class("patients", $active); ?>" href="/qm/pages/admin/patients.php">Patients</a>
      <a class="sidebar__link<?php echo active_class("dentists", $active); ?>" href="/qm/pages/admin/dentists.php">Dentist</a>
      <a class="sidebar__link<?php echo active_class("appointments", $active); ?>" href="/qm/pages/admin/appointments.php">Appointments</a>
      <a class="sidebar__link<?php echo active_class("records", $active); ?>" href="/qm/pages/admin/dental-records.php">Dental Records</a>
      <a class="sidebar__link<?php echo active_class("transactions", $active); ?>" href="/qm/pages/admin/transactions.php">Transactions</a>
      <a class="sidebar__link<?php echo active_class("reports", $active); ?>" href="/qm/pages/admin/reports.php">Reports</a>
      <a class="sidebar__link<?php echo active_class("location", $active); ?>" href="/qm/pages/admin/location.php">Location &amp; Map</a>
      <a class="sidebar__link<?php echo active_class("settings", $active); ?>" href="/qm/pages/admin/settings.php">Settings</a>

    <?php elseif ($role === "dentist"): ?>
      <a class="sidebar__link<?php echo active_class("dashboard", $active); ?>" href="/qm/dashboards/dentist.php">Dashboard</a>
      <a class="sidebar__link<?php echo active_class("today", $active); ?>" href="/qm/pages/dentist/today.php">Today's Patient</a>
      <a class="sidebar__link<?php echo active_class("records", $active); ?>" href="/qm/pages/dentist/dental-records.php">Dental Records</a>
      <a class="sidebar__link<?php echo active_class("transactions", $active); ?>" href="/qm/pages/dentist/transactions.php">Transaction History</a>
      <a class="sidebar__link<?php echo active_class("location", $active); ?>" href="/qm/pages/dentist/location.php">Location &amp; Map</a>
      <a class="sidebar__link<?php echo active_class("settings", $active); ?>" href="/qm/pages/dentist/settings.php">Settings</a>

    <?php elseif ($role === "patient"): ?>
      <a class="sidebar__link<?php echo active_class("dashboard", $active); ?>" href="/qm/dashboards/patient.php">Dashboard</a>
      <a class="sidebar__link<?php echo active_class("appointments", $active); ?>" href="/qm/pages/patient/appointments.php">My Appointments</a>
      <a class="sidebar__link<?php echo active_class("records", $active); ?>" href="/qm/pages/patient/dental-records.php">My Dental Record</a>
      <a class="sidebar__link<?php echo active_class("payments", $active); ?>" href="/qm/pages/patient/payments.php">Payment History</a>
      <a class="sidebar__link<?php echo active_class("location", $active); ?>" href="/qm/pages/patient/location.php">Location &amp; Map</a>
      <a class="sidebar__link<?php echo active_class("settings", $active); ?>" href="/qm/pages/patient/settings.php">Settings</a>

      <?php elseif ($role === "receptionist"): ?>
      <a class="sidebar__link<?php echo active_class("dashboard", $active); ?>" href="/qm/dashboards/receptionist.php">Dashboard</a>
      <a class="sidebar__link<?php echo active_class("transactions", $active); ?>" href="/qm/pages/receptionist/transactions.php">Transactions</a>
       <?php elseif ($role === "patient"): ?>

    <?php else: ?>
      <?php http_response_code(403); die("Forbidden"); ?>
    <?php endif; ?>
  </nav>

  <div class="sidebar__footer">
    <a class="sidebar__logout" href="/qm/logout.php">Log out</a>
  </div>
</aside>