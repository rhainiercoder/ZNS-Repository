<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . "/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Email and password are required.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($password, $user["password_hash"])) {
            $error = "Invalid email or password.";
        } else {
            session_regenerate_id(true);

            $_SESSION["user"] = [
                "id" => (int)$user["id"],
                "name" => $user["name"],
                "email" => $user["email"],
                "role" => $user["role"],
            ];

            if ($user["role"] === "admin" || $user["role"] === "staff") {
                header("Location: /qm/dashboards/admin.php");
            } elseif ($user["role"] === "dentist") {
                header("Location: /qm/dashboards/dentist.php");
            } elseif ($user["role"] === "receptionist") {
                header("Location: /qm/dashboards/receptionist.php");
            } else {
                header("Location: /qm/dashboards/patient.php");
            }
            exit;
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Login - ZNS Dental Clinic</title>
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
      <img class="authHero__img" src="/qm/assets/img/facility_2.jpg" alt="ZNS Dental Clinic">
      <div class="authHero__overlay"></div>

      <div class="authQuote">
        “For There Was Never Yet Philosopher, That Could Endure The Toothache Patiently”
        <span class="authQuote__by">~ Dr. Dre Andre Romelle</span>
      </div>
    </section>

    <section class="authPanel">
      <h1 class="authH1">Welcome Back</h1>
      <p class="authLead">Discover a better way of appointments with ZNS Dental Clinic.</p>

      <?php if (!empty($error)): ?>
        <div class="authMsg2 authMsg2--error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <!-- Optional (UI only) -->
      <button class="authGoogleBtn" type="button" onclick="alert('Google login is UI only for now.');">
        <span style="font-weight:900;">G</span> Log in with Google
      </button>

      <div class="authDivider">Or</div>

      <form method="post">
        <div class="authField">
          <span class="authField__icon">✉</span>
          <input name="email" type="email" placeholder="Enter your Email" required>
        </div>

        <div class="authField">
          <span class="authField__icon">🔒</span>
          <input name="password" type="password" placeholder="Password" required>
        </div>

        <div class="authRow">
          <label><input type="checkbox" name="remember" value="1"> Remember Me</label>
          <a class="authSmallLink" href="#" onclick="alert('Forgot password not implemented yet.'); return false;">Forget Password?</a>
        </div>

        <button class="authSubmit" type="submit">Log in</button>

        <div class="authBottomText">
          Not member yet? <a href="/qm/signup.php">Create an account</a>
        </div>
      </form>
    </section>
  </div>

</body>
</html>