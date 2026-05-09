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
    $dashUrl = "/dashboards/admin.php";
} elseif ($role === "dentist") {
    $dashUrl = "/dashboards/dentist.php";
} else {
    $dashUrl = "/dashboards/patient.php";
}

function topnav_active(string $key, string $active): string {
    return $key === $active ? " is-active" : "";
}
?>
<header class="topnav">
  <div class="topnav__inner">
    <a class="topnav__brand" href="/index.php">
      <img
        class="topnav__brandImg"
        src="/assets/img/logo.png"
        alt="ZNS logo"
      />
      <div>
        <span class="topnav__brandTitle">ZNS</span>
        <span class="topnav__brandSub">Dental Clinic</span>
      </div>
    </a>

    <input id="navToggle" class="topnav__toggle" type="checkbox" />
    <label for="navToggle" class="topnav__burger" aria-label="Menu">☰</label>

    <nav class="topnav__links">
      <a class="topnav__link<?php echo topnav_active('home', $active); ?>"
         href="/index.php#home">Home</a>
      <a class="topnav__link<?php echo topnav_active('about', $active); ?>"
         href="/index.php#about">About Us</a>
      <a class="topnav__link<?php echo topnav_active('services', $active); ?>"
         href="/index.php#services">Services</a>
      <a class="topnav__link<?php echo topnav_active('testimonials', $active); ?>"
         href="/index.php#testimonials">Testimonials</a>
      <a class="topnav__link<?php echo topnav_active('contact', $active); ?>"
         href="/index.php#contacts">Contact</a>
    </nav>

    <div class="topnav__actions">
      <?php if ($isLoggedIn): ?>
        <a class="topnav__btn topnav__btn--ghost"
           href="<?php echo htmlspecialchars($dashUrl); ?>">Dashboard</a>
        <a class="topnav__btn topnav__btn--primary"
           href="/pages/auth/logout.php">Logout</a>
      <?php else: ?>
        <a class="topnav__btn topnav__btn--ghost"
           href="/pages/auth/login.php">Login</a>
        <a class="topnav__btn topnav__btn--primary"
           href="/pages/auth/signup.php">Sign Up</a>
      <?php endif; ?>
    </div>
  </div>
</header>
