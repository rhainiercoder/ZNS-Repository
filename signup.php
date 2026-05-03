<?php
session_start();
require __DIR__ . "/db.php";

$error = "";
$success = "";

// If already logged in, redirect
if (!empty($_SESSION["user"]["role"])) {
    $role = $_SESSION["user"]["role"];
    if ($role === "admin" || $role === "staff") {
        header("Location: /qm/dashboards/admin.php");
    } elseif ($role === "dentist") {
        header("Location: /qm/dashboards/dentist.php");
    } else {
        header("Location: /qm/dashboards/patient.php");
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $role = "patient";

    if ($name === "" || $email === "" || $password === "") {
        $error = "Name, email, and password are required.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();

        if ($exists) {
            $error = "Email is already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $hash, $role);
            $stmt->execute();

            $success = "Account created! You can now log in.";
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Sign up - ZNS Dental Clinic</title>
  <link rel="stylesheet" href="/qm/assets/css/style.css">
</head>
<body class="authPage">

  <header class="authTopbar">
    <div class="authBrand2">
      <div class="authLogoMark">
        <img
            src="/qm/assets/img/logo.png"
            alt="ZNS Dental Clinic"
            style="width:100%; height:100%; object-fit:contain; display:block;"
          />
      </div>
      <div>
        ZNS
        <small>Dental Clinic</small>
      </div>
    </div>

    <nav class="authNav">
      <a href="/qm/index.php">Home</a>
      <a href="/qm/index.php#about">About Us</a>
      <a href="/qm/index.php#services">Services</a>
      <a href="/qm/index.php#testimonials">Testimonials</a>
      <a href="/qm/index.php#contact">Contact</a>
    </nav>

    <div class="authNavRight">
      <a class="authBtnGhost" href="/qm/login.php">Login</a>
      <a class="authBtnPrimary" href="/qm/signup.php">Sign up</a>
    </div>
  </header>

  <div class="authShell">
    <section class="authHero">
      <img class="authHero__img" src="/qm/assets/img/facility.jpg" alt="ZNS Dental Clinic">
      <div class="authHero__overlay"></div>

      <div class="authQuote">
        Elevating Standards, One Smile at a Time.
        <span class="authQuote__by">ZNS Dental Clinic</span>
      </div>
    </section>

    <section class="authPanel">
      <h1 class="authH1">Create account</h1>
      <p class="authLead">Sign up to book an appointment.</p>

      <?php if (!empty($success)): ?>
        <div class="authMsg2 authMsg2--ok"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="authMsg2 authMsg2--error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <div class="authDivider">Details</div>

      <form method="post">
        <div class="authField">
          <span class="authField__icon">👤</span>
          <input name="name" placeholder="Full name" required>
        </div>

        <div class="authField">
          <span class="authField__icon">✉</span>
          <input name="email" type="email" placeholder="Email" required>
        </div>

        <div class="authField">
          <span class="authField__icon">🔒</span>
          <input name="password" type="password" placeholder="Password" required>
        </div>

        <button class="authSubmit" type="submit">Sign up</button>

        <div class="authBottomText">
          Already have an account? <a href="/qm/login.php">Log in</a>
        </div>
      </form>
    </section>
  </div>

</body>
</html>