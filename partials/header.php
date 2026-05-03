<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$active = $active ?? ""; // e.g. "home", "about", "services", "testimonials", "contact"
?>
<header class="topnav">
  <div class="topnav__inner">
    <a class="topnav__brand" href="/qm/index.php">
      <span class="topnav__brandTitle">ZNS</span>
      <span class="topnav__brandSub">Dental Clinic</span>
    </a>

    <nav class="topnav__links">
      <a class="topnav__link <?php echo $active==="home" ? "is-active":""; ?>" href="/qm/index.php">Home</a>
      <a class="topnav__link <?php echo $active==="about" ? "is-active":""; ?>" href="/qm/about.php">About Us</a>
      <a class="topnav__link <?php echo $active==="services" ? "is-active":""; ?>" href="/qm/services.php">Services</a>
      <a class="topnav__link <?php echo $active==="testimonials" ? "is-active":""; ?>" href="/qm/testimonials.php">Testimonials</a>
      <a class="topnav__link <?php echo $active==="contact" ? "is-active":""; ?>" href="/qm/contact.php">Contact</a>
    </nav>

    <div class="topnav__actions">
      <?php if (!empty($_SESSION["user_id"])): ?>
        <a class="topnav__btn topnav__btn--ghost" href="/qm/dashboard.php">Dashboard</a>
        <a class="topnav__btn topnav__btn--primary" href="/qm/logout.php">Logout</a>
      <?php else: ?>
        <a class="topnav__btn topnav__btn--ghost" href="/qm/login.php">Login</a>
        <a class="topnav__btn topnav__btn--primary" href="/qm/signup.php">Sign up</a>
      <?php endif; ?>
    </div>
  </div>
</header>
