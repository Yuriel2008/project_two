<?php
// --- 1. SESSION & INIT ---
session_start();

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}

$status = null;
$statusType = ''; // 'error' or 'success'
$statusIcon = ''; // Icon for status message

// --- 2. HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Sanitize and validate inputs
  $username = trim($_POST['username'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass = $_POST['password'] ?? '';
  $confirmPassword = $_POST['confirm-password'] ?? '';

  try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);

    // Validation
    if (empty($username) || empty($email) || empty($pass)) {
      throw new Exception("All fields are required.");
    }
    if ($pass !== $confirmPass) {
      throw new Exception("Passwords do not match.");
    } elseif (strlen($pass) < 6) {
      throw new Exception("Password must be at least 6 characters long.");
    }
    // Check for duplicates
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $checkStmt->execute([$username, $email]);
    if ($checkStmt->fetch()) {
      throw new Exception("Username or email already registered.");
    }

    // Insert New User
    // Note: Rank/XP/Streak default to schema values (Silver II, 0, 0)
    $sql = "INSERT INTO users (username, email, password, created_at, last_login) VALUES (?, ?, ?, NOW(), NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $email, $pass]);

    // Auto-Login
    $_SESSION['user_id'] = $pdo->lastInsertId();

    // Redirect
    header("Location: index.php");
    exit;

  } catch (Exception $e) {
    $status = $e->getMessage();
    $statusType = 'error';
    $statusIcon = '⚠';
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LingoFlow v2 - Register</title>
  <style>
    /* --- 1. CSS VARIABLES & RESET --- */
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

    /* --- 2. REGISTER CARD DESIGN --- */
    .register-card {
      background: var(--glass);
      backdrop-filter: blur(12px);
      border: 1px solid var(--border);
      border-radius: 24px;
      width: 100%;
      max-width: 450px;
      /* Slightly wider than login for more fields */
      box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
      animation: fadeIn 0.8s ease-out;
      position: relative;
      overflow: hidden;
    }

    /* Decorative Top Line */
    .register-card::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, var(--cyan), var(--violet));
      box-shadow: 0 0 15px var(--violet-glow);
    }

    .logo-area {
      text-align: center;
      margin-bottom: 25px;
    }

    .logo-icon {
      width: 50px;
      height: 50px;
      background: rgba(6, 182, 212, 0.2);
      /* Cyan tint for register */
      color: var(--cyan);
      border-radius: 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      font-weight: bold;
      box-shadow: 0 0 20px rgba(6, 182, 212, 0.4);
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
    .form-grid {
      display: grid;
      gap: 15px;
      margin-bottom: 20px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    label {
      color: var(--text-muted);
      font-size: 13px;
      margin-bottom: 6px;
      font-weight: 600;
      padding-left: 4px;
    }

    input {
      width: 100%;
      background: rgba(2, 6, 23, 0.5);
      border: 1px solid var(--border);
      padding: 12px 16px;
      border-radius: 12px;
      color: white;
      font-family: inherit;
      font-size: 15px;
      transition: 0.3s;
    }

    input:focus {
      outline: none;
      border-color: var(--cyan);
      box-shadow: 0 0 15px rgba(6, 182, 212, 0.3);
      background: rgba(6, 182, 212, 0.05);
    }

    /* Register Button */
    .button-register {
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
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .button-register:hover {
      background: #7c3aed;
      transform: scale(1.02);
    }

    .button-register:active {
      transform: scale(0.98);
    }

    /* Links */
    .login-link {
      text-align: center;
      margin-top: 25px;
      font-size: 14px;
      color: var(--text-muted);
    }

    a {
      color: var(--text-main);
      text-decoration: none;
      font-weight: bold;
      transition: 0.2s;
    }

    a:hover {
      color: var(--cyan);
      text-shadow: 0 0 8px rgba(6, 182, 212, 0.5);
    }

    /* Error/Success Message */
    .status-msg {
      font-size: 13px;
      margin-bottom: 15px;
      display: none;
      align-items: center;
      gap: 8px;
      padding: 12px;
      border-radius: 8px;
      animation: slideDown 0.3s ease;
    }

    .status-msg.error {
      color: var(--red);
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .status-msg.success {
      color: var(--green);
      background: rgba(34, 197, 94, 0.1);
      border: 1px solid rgba(34, 197, 94, 0.2);
    }

    /* Animation */
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: scale(0.95);
      }

      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>

<body>
  <div class="register-card">
    <!-- Header -->
    <div class="logo-area">
      <div class="logo-icon">+</div>
      <h1>Join the Network</h1>
      <p class="subtitle">Creating your profile to start earning XP.</p>
    </div>

    <!-- Status Placeholder (Populated by PHP) -->
    <div id="status-box" class="status-msg <?php echo $statusMsg ? 'display: flex;': ''; ?>">
      <span id="stat-icon"><?php echo $statusIcon; ?></span>
      <span id="status-text"><?php echo htmlspecialchars($statusMsg); ?></span>
    </div>
    <!-- Form -->
    <form id="registerForm" method="POST" onsubmit="handleRegister(event)">
      <div class="form-grid">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" name="username" id="username" placeholder="Choose a codename" required
            autocomplete="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" />
        </div>

        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" name="email" id="email" placeholder="user@example.com" required autocomplete="email"
            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']): ''; ?>" />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" name="password" id="password" placeholder="Create password" required
            autocomplete="new-password" />
        </div>
        <div class="form-group">
          <label for="confirm-password">Confirm Password</label>
          <input type="password" id="confirm-password" name="confirm-password" placeholder="Repeat password" required
            autocomplete="new-password" />
        </div>
      </div>

      <button type="submit" class="button-register">
        Initialize Account
      </button>
    </form>
    <!-- Footer -->
    <div class="login-link">
      Already have an account? <a href="login.php">Log In</a>
    </div>
  </div>
  <script>
    function handleRegister(e) {
      // Prevent default only to show animation/client validation first
      e.preventDefault();

      const form = document.getElementById("registerForm");
      const button = document.querySelector(".button-register");
      const pass = document.getElementById("password").value;
      const confirmPass = document.getElementById("confirm-password").value;

      // Reset Status Display via JS (visual only)
      const statusBox = document.getElementById("status-box");
      statusBox.style.display = "none";

      // Client-side Basic Validation
      if (pass != confirmPass) {
        showStatus("error", "Passwords do not match.", "⚠");
        return;
      }

      if (pass.length < 6) {
        showStatus("error", "Password must be at least 6 characters.", "⚠");
        return;
      }

      // Animation Effect
      button.innerHTML = "Processing Data";
      button.style.opacity = "0.7";
      button.style.cursor = "wait";

      // Submit form to PHP after short visual delay
      setTimeout(() => {
        form.submit();
      }, 600);
    }

    function showStatus(type, message, icon) {
      const box = document.getElementById("status-box");
      const text = document.getElementById("status-text");
      const icon = document.getElementById("status-icon");

      box.className = `status-msg ${type}`;
      box.style.display = "flex";
      text.innerText = message;
      icon.innerText = icon;
    }
  </script>
</body>

</html>