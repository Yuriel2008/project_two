<?php
// --- 1. SESSION & LOGIC ---
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';

  // --- 2. DATABASE CONNECTION ---
  $dbHost = 'localhost';
  $dbName = 'lingoflow_db';
  $dbUser = 'root';
  $dbPass = '';

  try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check credentials (Plaintext as per requirements)
    // Allow login by Username OR Email
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['password'] === $password) {
      // Successful login. Or Success: Set session and redirect
      $_SESSION['user_id'] = $user['id'];

      // Optional: Update last_login timestamp
      $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
      $update->execute([$user['id']]);

      header("Location: index.php");
      exit;
    } else {
      $error = "Invalid username or password.";
    }

  } catch (PDOException $e) {
    $error = "System Error: " . $e->getMessage();
  }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LingoFlow v2 - Login</title>
  <style>
    :root {
      --bg-deep: #020617;
      --text-main: #e2e8f0;
      --text-muted: #94a3b8;
      --violet: #8b5cf6;
      --violet-glow: rgba(139, 92, 246, 0.5);
      --cyan: #06b6d4;
      --green: #22c55e;
      --red: #ef4444;
      --glass: rgba(255, 255, 255, 0.05);
      --border: rgba(255, 255, 255, 0.1);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: "Segoe UI", sans-serif;
      background-color: var(--bg-deep);
      color: var(--text-main);
      /* Background Effect */
      background-image: radial-gradient(circle at 50% 0%,
          rgba(139, 92, 246, 0.15) 0%,
          transparent 50%);
      height: 100vh;
      overflow: hidden;
      /* Center Content */
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* --- 2. LOGIN CARD DESIGN --- */
    .login-card {
      background: var(--glass);
      backdrop-filter: blur(12px);
      border: 1px solid var(--border);
      border-radius: 24px;
      padding: 40px;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
      animation: fadeIn 0.8s ease-out;
      position: relative;
      overflow: hidden;
    }

    /* Decorative Top Line */
    .login-card::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, var(--violet), var(--cyan));
      box-shadow: 0 0 15px var(--violet-glow);
    }

    .logo-area {
      text-align: center;
      margin-bottom: 30px;
    }

    .logo-icon {
      width: 60px;
      height: 60px;
      background: rgba(139, 92, 246, 0.2);
      color: var(--violet);
      border-radius: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
      font-weight: bold;
      box-shadow: 0 0 20px var(--violet-glow);
      margin-bottom: 15px;
    }

    h1 {
      margin: 0;
      font-size: 24px;
      letter-spacing: -0.5px;
    }

    p.subtitle {
      color: var(--text-muted);
      margin: 5px 0 0 0;
      font-size: 14px;
    }

    /* --- 3. FORM ELEMENTS --- */
    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      color: var(--text-muted);
      font-size: 13px;
      margin-bottom: 8px;
      font-weight: 600;
    }

    .input-wrapper {
      position: relative;
    }

    input {
      width: 100%;
      background: rgba(2, 6, 23, 0.5);
      border: 1px solid var(--border);
      padding: 12px 16px;
      /* Space for icon if needed */
      border-radius: 12px;
      color: white;
      font-family: inherit;
      font-size: 15px;
      transition: 0.3s;
    }

    input:focus {
      outline: none;
      border-color: var(--violet);
      box-shadow: 0 0 15px rgba(139, 92, 246, 0.3);
      background: rgba(139, 92, 246, 0.05);
    }

    /* Login Button */
    .button-login {
      width: 100%;
      padding: 14px;
      background: var(--violet);
      color: white;
      border: none;
      border-radius: 12px;
      font-weight: bold;
      font-size: 16px;
      cursor: pointer;
      transition: 0.2s;
      margin-top: 10px;
      box-shadow: 0 4px 15px var(--violet-glow);
    }

    .button-login:hover {
      background: #7c3aed;
      transform: scale(1.02);
    }

    .button-login:active {
      transform: scale(0.98);
    }

    /* Links */
    .links-row {
      display: flex;
      justify-content: space-between;
      margin-top: 20px;
      font-size: 13px;
    }

    a {
      color: var(--text-muted);
      text-decoration: none;
      transition: 0.2s;
    }

    a:hover {
      color: var(--cyan);
      text-shadow: 0 0 8px rgba(6, 182, 212, 0.5);
    }

    .register-link {
      text-align: center;
      margin-top: 30px;
      font-size: 14px;
      color: var(--text-muted);
    }

    .register-link strong {
      color: var(--violet);
      cursor: pointer;
    }

    .register-link strong:hover {
      text-decoration: underline;
    }

    /* Error Message */
    .error-msg {
      color: var(--red);
      font-size: 13px;
      margin-bottom: 15px;
      display: none;
      align-items: center;
      gap: 8px;
      background: rgba(239, 68, 68, 0.1);
      padding: 10px;
      border-radius: 8px;
    }

    /* Animation */
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>

<body>
  <div class="login-card">
    <!-- Header -->
    <div class="logo-area">
      <div class="logo-icon">L</div>
      <h1>Welcome Back</h1>
      <p class="subtitle">Enter the simulation to continue learning.</p>
    </div>

    <!-- Error Placeholder (Populated by PHP) -->
    <div id="error-box" class="error-msg" style=" <?php echo !empty($error) ? 'display: flex;': ''; ?>">
      <span>⚠</span> <span id="error-text"> <?php echo htmlspecialchars($error); ?></span>
    </div>

    <!-- Form -->
    <!-- Method POST, Action Self -->
    <form id="loginForm" method="POST" onsubmit="handleLogin(event)">
      <div class="form-group">
        <label for="username">Username / Email</label>
        <div class="input-wrapper">
          <input type="text" id="username" name="username" placeholder="learner_one" required autocomplete="username"
            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']): ''; ?>" />
        </div>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrapper">
          <input type="password" id="password" name="password" placeholder="••••••••" required
            autocomplete="current-password" />
        </div>
      </div>

      <div class="links-row">
        <label style="
              display: flex;
              align-items: center;
              gap: 8px;
              cursor: pointer;
              font-weight: normal;
            ">
          <input type="checkbox" style="width: auto; margin: 0; accent-color: var(--violet)" />
          Remember me
        </label>
        <a href="#">Forgot Password?</a>
      </div>

      <button type="submit" class="button-login">Initialize System</button>
    </form>
    <!-- Footer -->
    <div class="register-link">
      New to LingoFlow?
      <a href="register.php"><strong>Create Account</strong></a>
    </div>
  </div>
  <script>
    function handleLogin(e) {
      // Prevent immediate submission to show animation
      e.preventDefault();

      const button = document.querySelector(".button-login");
      const form = document.getElementById("loginForm");

      // Animation Effect
      button.innerHTML = "Authenticating...";
      button.style.opacity = "0.7";
      button.style.cursor = "wait";

      // Simulate short Network Delay for UX, then submit to PHP
      setTimeout(() => {
        form.submit();
      }, 800);
    }
  </script>
</body>

</html>