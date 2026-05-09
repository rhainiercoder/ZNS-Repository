<?php
// partials/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$active = $active ?? ""; // "home", "about", "services", "testimonials", "contact"
?>
<header class="topnav">
  <div class="topnav__inner">
    <a class="topnav__brand" href="/index.php">
      <span class="topnav__brandTitle">ZNS</span>
      <span class="topnav__brandSub">Dental Clinic</span>
    </a>

    <!-- Mobile menu toggle -->
    <input id="navToggle" class="topnav__toggle" type="checkbox" />
    <label for="navToggle" class="topnav__burger" aria-label="Menu">☰</label>

    <nav class="topnav__links" aria-label="Primary">
      <a class="topnav__link <?php echo $active==="home" ? "is-active" : ""; ?>" href="/index.php">Home</a>
      <a class="topnav__link <?php echo $active==="about" ? "is-active" : ""; ?>" href="/about.php">About Us</a>
      <a class="topnav__link <?php echo $active==="services" ? "is-active" : ""; ?>" href="/services.php">Services</a>
      <a class="topnav__link <?php echo $active==="testimonials" ? "is-active" : ""; ?>" href="/testimonials.php">Testimonials</a>
      <a class="topnav__link <?php echo $active==="contact" ? "is-active" : ""; ?>" href="/contact.php">Contact</a>
    </nav>

    <div class="topnav__actions">
      <?php if (!empty($_SESSION["user"]["id"])): ?>
        <a class="topnav__btn topnav__btn--ghost" href="/dashboard.php">Dashboard</a>
        <a class="topnav__btn topnav__btn--primary" href="/logout.php">Logout</a>
      <?php else: ?>
        <a class="topnav__btn topnav__btn--ghost" href="/login.php">Login</a>
        <a class="topnav__btn topnav__btn--primary" href="/signup.php">Sign up</a>
      <?php endif; ?>
    </div>
  </div>
</header>