<?php
/**
 * LingoFlow v2 - Admin Authentication Terminal
 * Self-Contained Logic for System Access
 */

session_start();

// 1. Database Configuration
$host = 'localhost';
$db = 'lingoflow_db';
$user = 'root';
$pass = '';
$port = '3306';
$error_msg = "";

$error = "";

// 2. Already logged in? Redirect to dashboard
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
  header("Location: dashboard.php");
  exit();
}

// 3. Handle Login Attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $admin_id = $_POST['admin_id'] ?? '';
  $passcode = $_POST['passcode'] ?? '';

  try {
    $dsn = "mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Authentication Query (Plaintext passwords as per schema requirements)
    // Strictly enforcing role = 'admin'
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();

    if ($admin && $admin['password'] === $passcode) {
      // Set Session Data
      $_SESSION['user_id'] = $admin['id'];
      $_SESSION['username'] = $admin['username'];
      $_SESSION['role'] = $admin['role'];

      // Redirect to Admin Dashboard
      header("Location: dashboard.php");
      exit();
    } else {
      $error_msg = "ACCESS DENIED: INVALID CREDENTIALS OR INSUFFICIENT CLEARANCE.";
    }

  } catch (PDOException $e) {
    $error_msg = "SYSTEM ERROR: " . $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LingoFlow v2 - Admin Login</title>
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
      --red-glow: rgba(239, 68, 68, 0.5);
      --glass: rgba(255, 255, 255, 0.03);
      --border: rgba(255, 255, 255, 0.08);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: "Segoe UI", sans-serif;
      background-color: var(--bg-deep);
      color: var(--text-main);
      /* Background Effect - Red/Violet Tint for Admin */
      background-image: radial-gradient(circle at 50% 0%,
          rgba(239, 68, 68, 0.1) 0%,
          transparent 50%);
      height: 100vh;
      overflow: hidden;
      /* Center Content */
      display: flex;
      align-items: center;
      justify-self: center;
      position: relative;
    }

    /* Grid overlay for cyberpunk feel */
    body::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-image:
        linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
      background-size: 30px 30px;
      z-index: -1;
      pointer-events: none;
    }

    /* --- 2. LOGIN CARD DESIGN --- */
    .login-card {
      background: var(--glass);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(239, 68, 68, 0.2);
      border-radius: 20px;
      padding: 40px;
      width: 100%;
      max-width: 420px;
      box-shadow:
        0 0 50px rgba(0, 0, 0, 0.8),
        inset 0 0 20px rgba(239, 68, 68, 0.05);
      animation: glitch-in 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
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
      background: linear-gradient(90deg, var(--red), var(--violet));
      box-shadow: 0 0 15px var(--red-glow);
    }

    .logo-area {
      text-align: center;
      margin-bottom: 30px;
    }

    .logo-icon {
      width: 60px;
      height: 60px;
      background: rgba(239, 68, 68, 0.15);
      color: var(--red);
      border-radius: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
      font-weight: bold;
      box-shadow: 0 0 25px rgba(239, 68, 68, 0.3);
      margin-bottom: 15px;
      border: 1px solid rgba(239, 68, 68, 0.3);
    }

    .h1 {
      margin: 0;
      font-size: 24px;
      letter-spacing: 2px;
      text-transform: uppercase;
      color: #fff;
      text-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
    }

    p.subtitle {
      color: var(--red);
      margin: 5px 0 0 0;
      font-size: 13px;
      letter-spacing: 1px;
      text-transform: uppercase;
      opacity: 0.8;
    }

    /* --- 3. FORM ELEMENTS --- */
    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      color: var(--text-muted);
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 8px;
      font-weight: 600;
    }

    .input-wrapper {
      position: relative;
    }

    input {
      width: 100%;
      background: rgba(0, 0, 0, 0.4);
      border: 1px solid var(--border);
      padding: 14px 16px;
      border-radius: 8px;
      color: var(--cyan);
      font-family: "Courier New", Courier, monospace;
      font-size: 16px;
      transition: 0.3s;
      letter-spacing: 1px;
    }

    input::placeholder {
      color: rgba(148, 163, 184, 0.3);
    }

    input:focus {
      outline: none;
      border-color: var(--red);
      box-shadow: 0 0 15px rgba(239, 68, 68, 0.2);
      background: rgba(239, 68, 68, 0.05);
    }

    /* Login Button */
    .button-login {
      width: 100%;
      padding: 16px;
      background: transparent;
      color: var(--red);
      border: 1px solid var(--red);
      border-radius: 8px;
      font-weight: bold;
      font-size: 16px;
      letter-spacing: 2px;
      text-transform: uppercase;
      cursor: pointer;
      transition: 0.2s;
      margin-bottom: 15px;
      position: relative;
      overflow: hidden;
    }

    .button-login:hover {
      background: var(--red);
      color: #fff;
      box-shadow: 0 0 20px var(--red-glow);
      transform: scale(1.02);
    }

    .button-login:active {
      transform: scale(0.98);
    }

    /* Warning/Error Message */
    .error-msg {
      color: var(--red);
      font-size: 13px;
      margin-bottom: 20px;
      display: none;
      /* Controlled via PHP */
      align-items: center;
      gap: 8px;
      background: rgba(239, 68, 68, 0.1);
      padding: 12px;
      border-radius: 8px;
      border: 1px solid rgba(239, 68, 68, 0.3);
      font-family: "Courier New", Courier, monospace;
    }

    /* Footer Link */
    .return-link {
      text-align: center;
      margin-top: 30px;
      font-size: 13px;
    }

    .return-link a {
      color: var(--text-muted);
      text-decoration: none;
      transition: 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .return-link a:hover {
      color: white;
      text-shadow: 0 0 8px rgba(255, 255, 255, 0.5);
    }

    /* Animation */
    @keyframes glitch-in {
      0% {
        transform: scale(0.95);
        opacity: 0;
        filter: blur(10px);
      }

      100% {
        transform: scale(1);
        opacity: 1;
        filter: blur(0);
      }
    }
  </style>
</head>

<body>
  <div class="login-card">
    <!-- Header -->
    <div class="logo-area">
      <div class="logo-icon">A</div>
      <h1>System Command</h1>
      <p class="subtitle">Restricted Access Portal</p>
    </div>

    <!-- Error Placeholder -->
    <div id="error-box" class="error-msg" style=" <?php echo !empty($error_msg) ? 'display: flex;' : ''; ?>">
      <span>⚠</span> <span id="error-text"><?php echo htmlspecialchars($error_msg); ?></span>
    </div>

    <!-- Form: Method updated to POST -->
    <form id="adminLoginForm" method="POST" action="login.php">
      <div class="form-group">
        <label for="admin-id">Admin ID</label>
        <div class="input-wrapper">
          <input type="text" name="admin_id" id="admin-id" placeholder="SYS_ADMIN_01" required autocomplete="username"
            spellcheck="false"
            value="<?php echo isset($_POST['admin_id']) ? htmlspecialchars($_POST['admin_id']) : ''; ?>" />
        </div>
      </div>

      <div class="form-group">
        <label for="passcode">Security Passcode</label>
        <div class="input-wrapper">
          <input type="password" name="passcode" id="passcode" placeholder="••••••••••••" required
            autocomplete="current-password" />
        </div>
      </div>

      <button type="submit" class="button-login" id="submit-button">
        Authenticate
      </button>
    </form>

    <!-- Footer -->
    <div class="return-link">
      <a href="../login.php">
        <!-- < ?xml version="1.0" encoding="utf-8"?> -->
        <!-- License: PD. Made by Mary Akveo: https://maryakveo.com/ -->
        <svg fill="#000000" width="14" height="14" viewBox="0 0 24 24" id="left" data-name="Flat Color"
          xmlns="http://www.w3.org/2000/svg" class="icon flat-color">
          <path id="primary"
            d="M21,11H5.41l5.3-5.29A1,1,0,1,0,9.29,4.29l-7,7a1,1,0,0,0,0,1.42l7,7a1,1,0,0,0,1.42,0,1,1,0,0,0,0-1.42L5.41,13H21a1,1,0,0,0,0-2Z"
            style="fill: rgb(0, 0, 0)"></path>
        </svg>
        Return to Learner Portal
      </a>
    </div>
  </div>
  <script>
    // We keep the visual flair for the button, but let the PHP handle the actual logic
    const form = document.getElementById('adminLoginForm')
    const button = document.getElementById('submit-button')

    form.addEventListener('submit', function () {
      button.innerHTML = 'Verifying...';
      button.style.backgroundColor = 'var(--transparent)';
      button.style.color = 'var(--text-muted)';
      button.style.borderColor = 'var(--text-muted)';
      button.style.cursor = 'wait';
      button.style.boxShadow = 'none';
    });
  </script>
</body>

</html>