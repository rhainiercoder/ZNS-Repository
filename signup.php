<?php
session_start();
require __DIR__ . "/db.php";

$error = "";
$success = "";

function table_has_column(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("
      SELECT COUNT(*) AS c
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
      LIMIT 1
    ");
    if ($stmt === false) return false;
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row["c"] ?? 0) > 0;
}

$contactColumn = table_has_column($conn, "users", "phone") ? "phone" : (table_has_column($conn, "users", "contact") ? "contact" : "");

// If already logged in, redirect
if (!empty($_SESSION["user"]["role"])) {
    $role = $_SESSION["user"]["role"];
    if ($role === "admin" || $role === "staff") {
        header("Location: /dashboards/admin.php");
    } elseif ($role === "dentist") {
        header("Location: /dashboards/dentist.php");
    } else {
        header("Location: /dashboards/patient.php");
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $contact = trim($_POST["contact"] ?? "");
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

            if ($contactColumn !== "") {
                $stmt = $conn->prepare("INSERT INTO users (name, email, {$contactColumn}, password_hash, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $name, $email, $contact, $hash, $role);
            } else {
                $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $email, $hash, $role);
            }
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign up - ZNS Dental Clinic</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="authPage">

  <header class="siteHeader">
  <div class="container nav">
    <div class="brand">
      <img src="/assets/img/logo.png" alt="logo"/>
      <div>
        <div class="brandTitle">ZNS</div>
        <div class="brandSub">Dental Clinic</div>
      </div>
    </div>

    <!-- hamburger toggle -->
    <input id="siteNavToggle" class="navToggle" type="checkbox">
    <label for="siteNavToggle" class="navBurger" aria-label="Menu">☰</label>

    <nav class="siteNav">
      <ul>
        <li><a href="/index.php#home">Home</a></li>
        <li><a href="/index.php#about">About Us</a></li>
        <li><a href="/index.php#services">Services</a></li>
        <li><a href="/index.php#doctors">Doctors</a></li>
        <li><a href="/index.php#testimonials">Testimonials</a></li>
        <li><a href="/index.php#contacts">Contact</a></li>
      </ul>
    </nav>

    <div class="nav-actions">
      <a class="btn light" href="/login.php">Login</a>
      <a class="btn primary" href="/signup.php">Sign Up</a>
    </div>
  </div>
</header>

  <div class="authShell">
    <section class="authHero">
      <img class="authHero__img" src="/assets/img/facility.jpg" alt="ZNS Dental Clinic">
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
          <span class="authField__icon">#</span>
          <input name="contact" placeholder="Contact number" value="<?php echo htmlspecialchars($_POST["contact"] ?? ""); ?>">
        </div>

        <div class="authField">
          <span class="authField__icon">🔒</span>
          <input class="hasPasswordToggle" name="password" type="password" placeholder="Password" required>
          <button class="passwordToggle" type="button" aria-label="Show password" data-password-toggle>&#128065;</button>
        </div>

        <button class="authSubmit" type="submit">Sign up</button>

        <div class="authBottomText">
          Already have an account? <a href="login.php">Log in</a>
        </div>
      </form>
    </section>
  </div>

<script>
document.querySelectorAll('[data-password-toggle]').forEach(function(btn){
  btn.addEventListener('click', function(){
    var input = btn.parentElement.querySelector('input[type="password"], input[type="text"]');
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
});
</script>

</body>
</html>
