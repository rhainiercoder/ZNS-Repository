<?php
// partials/header.php
// REQUIREMENT: call session_start(); in the page BEFORE any HTML output.

$active = $active ?? "";              // optional: "home", "about", ...
$isHome = $isHome ?? false;           // set true in index.php if you want pure # anchors
$homePrefix = $isHome ? "" : "/index.php"; // other pages link back to index sections
?>
<header class="siteHeader">
  <div class="container nav">
    <div class="brand">
      <img src="/assets/img/logo.png" alt="logo" />
      <div>
        <div class="brandTitle">ZNS</div>
        <div class="brandSub">Dental Clinic</div>
      </div>
    </div>

    <!-- Mobile toggle (CSS-only) -->
    <input id="siteNavToggle" class="navToggle" type="checkbox">
    <label for="siteNavToggle" class="navBurger" aria-label="Menu">☰</label>

    <nav class="siteNav" aria-label="Primary">
      <ul>
        <li><a class="<?php echo $active==='home'?'is-active':''; ?>" href="<?php echo $homePrefix; ?>#home">Home</a></li>
        <li><a class="<?php echo $active==='about'?'is-active':''; ?>" href="<?php echo $homePrefix; ?>#about">About Us</a></li>
        <li><a class="<?php echo $active==='services'?'is-active':''; ?>" href="<?php echo $homePrefix; ?>#services">Services</a></li>
        <li><a class="<?php echo $active==='doctors'?'is-active':''; ?>" href="<?php echo $homePrefix; ?>#doctors">Doctors</a></li>
        <li><a class="<?php echo $active==='testimonials'?'is-active':''; ?>" href="<?php echo $homePrefix; ?>#testimonials">Testimonials</a></li>
        <li><a class="<?php echo $active==='contacts'?'is-active':''; ?>" href="<?php echo $homePrefix; ?>#contacts">Contact</a></li>
      </ul>
    </nav>

    <div class="nav-actions">
      <?php if (!empty($_SESSION["user"]["id"])): ?>
        <a class="btn light" href="/dashboard.php">Dashboard</a>
        <a class="btn primary" href="/logout.php">Logout</a>
      <?php else: ?>
        <a class="btn light" href="/login.php">Login</a>
        <a class="btn primary" href="/signup.php">Sign Up</a>
      <?php endif; ?>
    </div>
  </div>
</header>