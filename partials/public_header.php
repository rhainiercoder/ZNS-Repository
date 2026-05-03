<?php
/**
 * partials/public_header.php
 * Shared public header — include on index.php, login.php, signup.php.
 *
 * Expects optional:
 *   $active  string  one of: "home", "about", "services", "testimonials", "contact"
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$active = $active ?? "";

$isLoggedIn = !empty($_SESSION["user"]["id"]);
$role       = $_SESSION["user"]["role"] ?? "";

// Route dashboard link based on role
if ($role === "admin" || $role === "staff") {
    $dashUrl = "/qm/dashboards/admin.php";
} elseif ($role === "dentist") {
    $dashUrl = "/qm/dashboards/dentist.php";
} else {
    $dashUrl = "/qm/dashboards/patient.php";
}

function topnav_active(string $key, string $active): string {
    return $key === $active ? " is-active" : "";
}
?>
<header class="topnav">
  <div class="topnav__inner">
    <a class="topnav__brand" href="/qm/index.php">
      <img
        class="topnav__brandImg"
        src="/qm/assets/img/logo.png"
        alt="ZNS logo"
      />
      <div>
        <span class="topnav__brandTitle">ZNS</span>
        <span class="topnav__brandSub">Dental Clinic</span>
      </div>
    </a>

    <nav class="topnav__links">
      <a class="topnav__link<?php echo topnav_active('home', $active); ?>"
         href="/qm/index.php#home">Home</a>
      <a class="topnav__link<?php echo topnav_active('about', $active); ?>"
         href="/qm/index.php#about">About Us</a>
      <a class="topnav__link<?php echo topnav_active('services', $active); ?>"
         href="/qm/index.php#services">Services</a>
      <a class="topnav__link<?php echo topnav_active('testimonials', $active); ?>"
         href="/qm/index.php#testimonials">Testimonials</a>
      <a class="topnav__link<?php echo topnav_active('contact', $active); ?>"
         href="/qm/index.php#contacts">Contact</a>
    </nav>

    <div class="topnav__actions">
      <?php if ($isLoggedIn): ?>
        <a class="topnav__btn topnav__btn--ghost"
           href="<?php echo htmlspecialchars($dashUrl); ?>">Dashboard</a>
        <a class="topnav__btn topnav__btn--primary"
           href="/qm/logout.php">Logout</a>
      <?php else: ?>
        <a class="topnav__btn topnav__btn--ghost"
           href="/qm/login.php">Login</a>
        <a class="topnav__btn topnav__btn--primary"
           href="/qm/signup.php">Sign Up</a>
      <?php endif; ?>
    </div>
  </div>
</header>
