<?php
// --- 1. SESSION & INIT ---
session_start();

// --- 2. LOGOUT HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
  // Unset all session variables
  $_SESSION = array();

  // Destroy the session cookie
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
      session_name(),
      '',
      time() - 4200,
      $params["path"],
      $params["domain"],
      $params["secure"],
      $params["httponly"]
    );
  }

  // Destroy the session
  session_destroy();

  // Redirect to login
  header("Location: login.php");
  exit;
}

// --- 3. AUTH CHECK ---
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}
$user_id = $_SESSION['user_id'];

// Variables for Status Feedback
$statusMsg = '';
$statusType = '';

// --- 4. DATABASE CONNECTION ---
$dbHost = 'localhost';
$dbName = 'lingoflow_db';
$dbUser = 'root';
$dbPass = '';

try {
  $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // --- 5. HANDLE FORM SUBMISSIONS (EMAIL & PASSWORD UPDATE) ---
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Update Email Action
    if ($_POST['action'] === 'update_email') {
      $new_email = trim($_POST['new_email'] ?? '');

      if (!empty($new_email) && filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$new_email, $user_id]);
        if ($stmt->fetch()) {
          $statusMsg = "That email address is already in use.";
          $statusType = "error";
        } else {
          $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
          $stmt->execute([$new_email, $user_id]);
          $statusMsg = "Email address successfully updated.";
          $statusType = "success";
        }
      } else {
        $statusMsg = "Please enter a valid email address.";
        $statusType = "error";
      }
    }

    // Update Password Action
    if ($_POST['action'] === 'update_password') {
      $current_password = $_POST['current_password'] ?? '';
      $new_password = $_POST['new_password'] ?? '';
      $confirm_password = $_POST['confirm_password'] ?? '';

      // Verify current password first
      $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
      $stmt->execute([$user_id]);
      $userData = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($userData['password'] !== $current_password) {
        $statusMsg = "Current password is incorrect.";
        $statusType = "error";
      } elseif ($new_password !== $confirm_password) {
        $statusMsg = "New passwords do not match.";
        $statusType = "error";
      } elseif (strlen($new_password) < 6) {
        $statusMsg = "New password must be at least 6 characters long.";
        $statusType = "error";
      } else {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password, $user_id]);
        $statusMsg = "Security passcode successfully upload.";
        $statusType = "success";
      }
    }
  }

  // --- 6. FETCH USER DATA (Run after possible updates so UI reflects changes) ---
  $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    // Force logout if user not found in DB
    header("Location: settings.php?action=logout");
    exit;
  }
} catch (PDOException $e) {
  die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LingoFlow v2 - Settings</title>
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
      --orange: #fb923c;
      --glass: rgba(255, 255, 255, 0.05);
      --border: rgba(255, 255, 255, 0.1);
    }

    * {
      box-sizing: border-box;
    }

    /* 
         NATIVE SCROLLING FIX: 
         Removed overflow: hidden to allow the whole window to scroll normally.
      */

    body {
      margin: 0;
      font-family: "Segoe UI", sans-serif;
      background-color: var(--bg-deep);
      color: var(--text-main);
      background-image: radial-gradient(circle at 50% 0%,
          rgba(139, 92, 246, 0.1) 0%,
          transparent 80%);
      background-attachment: fixed;
      /* Keep gradient static while scrolling */
      min-height: 100vh;
      padding-left: 80px;
      /* Offset for desktop sidebar */
    }

    /* --- 2. FIXED SIDEBAR NAVIGATION --- */
    .sidebar {
      background: rgba(255, 255, 255, 0.02);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding-top: 20px;
      gap: 20px;
      backdrop-filter: blur(10px);
      z-index: 100;
      position: fixed;
      top: 0;
      left: 0;
      width: 80px;
      height: 100vh;
    }

    .logo-boxes {
      width: 40px;
      height: 40px;
      background: rgba(139, 92, 246, 0.2);
      color: var(--violet);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-self: center;
      font-weight: bold;
      font-size: 20px;
      margin-bottom: 10px;
      box-shadow: 0 0 15px rgba(139, 92, 246, 0.2);
      cursor: default;
    }

    .icon-button {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      border: none;
      background: transparent;
      color: #64748b;
      cursor: pointer;
      transition: all;
    }

    .icon-button:hover {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      border: none;
      background: transparent;
      color: #64748b;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .icon-button.active {
      background: var(--violet);
      color: white;
      box-shadow: 0 0 15px var(--violet-glow);
    }

    /* --- 3. MAIN CONTENT (NATURAL FLOW) --- */
    .main-content {
      padding: 40px;
      display: flex;
      flex-direction: column;
      gap: 30px;
    }

    header {
      margin-bottom: 10px;
    }

    h1 {
      margin: 0;
      font-size: 32px;
      letter-spacing: -0.5px;
    }

    .sub-header {
      color: var(--text-muted);
      margin: 8px 0 0 0;
      font-size: 15px;
    }

    /* --- 4. STATUS MESSAGE --- */
    .status-msg {
      padding: 16px 20px;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 12px;
      animation: fadeInDown 0.4s ease;
      margin-bottom: 10px;
    }

    .status-msg.success {
      background: rgba(34, 197, 94, 0.1);
      color: var(--green);
      border: 1px solid rgba(34, 197, 94, 0.3);
    }

    .status-msg.error {
      background: rgba(239, 68, 68, 0.1);
      color: var(--red);
      border: 1px solid rgba(239, 68, 68, 0.3);
    }

    .section-title {
      padding: 24px;
      font-size: 15px;
      font-weight: bold;
      color: white;
      text-transform: uppercase;
      letter-spacing: 1px;
      border-bottom: 1px solid var(--border);
      background: rgba(255, 255, 255, 0.02);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .section-title i {
      color: var(--violet);
    }

    .setting-item {
      padding: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid var(--border);
      transition: background 0.2s;
    }

    .setting-item:last-child {
      border-bottom: none;
    }

    .setting-item:hover {
      background: rgba(255, 255, 255, 0.02);
    }

    .item-info h3 {
      margin: 0 0 6px 0;
      font-size: 16px;
      font-weight: 600;
      color: white;
    }

    .item-info p {
      margin: 0;
      font-size: 14px;
      color: var(--text-muted);
      line-height: 1.4;
    }

    /* Inline Forms */
    .setting-item-column {
      flex-direction: column;
      align-items: stretch;
    }

    .item-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
    }

    .hidden-form {
      display: none;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px dashed rgba(255, 255, 255, 0.1);
      animation: fadeInDown 0.3s ease;
    }

    .hidden-form.active {
      display: block;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-input {
      width: 100%;
      background: rgba(0, 0, 0, 0.5);
      border: 1px solid var(--border);
      padding: 14px 16px;
      border-radius: 10px;
      color: white;
      font-family: inherit;
      transition: 0.2s;
      font-size: 15px;
    }

    .form-input:focus {
      outline: none;
      border-color: var(--violet);
      box-shadow: 0 0 15px rgba(139, 92, 246, 0.2);
    }

    .button-submit {
      background: var(--violet);
    }

    .button-submit:hover {
      background: #7c3aed;
      box-shadow: 0 0 20px var(--violet-glow);
      transform: translateY(-2px);
    }

    .button-cancel {
      background: transparent;
      color: var(--text-muted);
      border: 1px solid var(--border);
      padding: 12px 24px;
      border-radius: 10px;
      cursor: pointer;
      margin-left: 10px;
      transition: 0.2s;
      font-size: 14px;
      font-weight: bold;
    }

    .button-cancel:hover {
      background: rgba(255, 255, 255, 0.08);
      color: white;
      border-color: rgba(255, 255, 255, 0.3);
    }

    /* --- 6. CONTROLS --- */
    .toggle-switch {
      width: 54px;
      height: 30px;
      background: #334155;
      border-radius: 15px;
      position: relative;
      cursor: pointer;
      transition: 0.3s;
      flex-shrink: 0;
    }

    .toggle-switch::after {
      content: "";
      position: absolute;
      width: 24px;
      height: 24px;
      background: white;
      border-radius: 50%;
      top: 3px;
      left: 3px;
      transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
    }

    .toggle-switch.active {
      background: var(--violet);
      box-shadow: 0 0 15px var(--violet-glow);
    }

    .toggle-switch.disabled::after {
      background: #64748b;
    }

    .button-link {
      background: rgba(139, 92, 246, 0.1);
      border: 1px solid rgba(139, 92, 246, 0.3);
      color: var(--violet);
      font-size: 13px;
      font-weight: bold;
      cursor: pointer;
      padding: 8px 16px;
      border-radius: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      transition: 0.2s;
    }

    .button-link:hover {
      background: var(--violet);
      color: white;
      box-shadow: 0 0 15px var(--violet-glow);
    }

    /* --- 7. DANGER ZONE --- */
    .danger-zone {
      border: 1px solid rgb(239, 68, 68, 0.3);
      background: rgba(239, 68, 68, 0.02);
    }

    .danger-zone .section-title {
      color: var(--red);
      border-bottom: 1px solid rgba(239, 68, 68, 0.15);
      background: rgba(239, 68, 68, 0.05);
    }

    .danger-zone .item-info h3 {
      color: var(--red);
    }

    .button-danger {
      padding: 14px 24px;
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid var(--red);
      color: var(--red);
      border-radius: 10px;
      font-weight: bold;
      cursor: pointer;
      transition: 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      font-size: 15px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .button-danger:hover {
      background: var(--red);
      color: white;
      box-shadow: 0 0 20px rgba(239, 68, 68, 0.4);
      transform: translateY(-2px);
    }

    /* --- 8. RESPONSIVE --- */
    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 768px) {
      body {
        padding-left: 0;
        padding-bottom: 90px;
        /* Offset to prevent overlap with mobile bottom navbar */
      }

      .sidebar {
        flex-direction: row;
        justify-content: space-around;
        padding-top: 0;
        width: 100%;
        height: 75%;
        top: auto;
        border-right: none;
        border-top: 1px solid var(--border);
        background: rgba(2, 6, 23, 0.95);
      }

      .logo-boxes {
        display: none;
      }

      .main-content {
        padding: 25px 20px;
      }

      .item-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
      }

      .button-link {
        align-self: flex-start;
      }
    }
  </style>
</head>

<body>
  <!--SideBar-->
  <nav class="sidebar">
    <div class="logo-boxes">L</div>

    <!--1. Home (Active)-->
    <button class="icon-button" onclick="window.location.href = 'index.php'" aria-label="Home">
      <!--This SVG family is important for icons.-->
      <!-- < ?xml version="1.0" encoding="utf-8"?> -->
      <!-- License: PD. Made by mono-company: https://github.com/mono-company/mono-icons -->
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path
          d="M11.336 2.253a1 1 0 0 1 1.328 0l9 8a1 1 0 0 1-1.328 1.494L20 11.45V19a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7.55l-.336.297a1 1 0 0 1-1.328-1.494l9-8zM6 9.67V19h3v-5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v5h3V9.671l-6-5.333-6 5.333zM13 19v-4h-2v4h2z"
          fill="#0D0D0D" />
      </svg>
    </button>

    <!--2. RoadMap-->
    <button class="icon-button" onclick="window.location.href = 'roadmap.php'" aria-label="Roadmap">
      <!-- < ?xml version="1.0" encoding="utf-8"?> -->
      <!-- License: PD. Made by mono-company: https://github.com/mono-company/mono-icons -->
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path
          d="M8.684 3.051a1 1 0 0 1 .632 0L15 4.946l4.367-1.456A2 2 0 0 1 22 5.387V17.28a2 2 0 0 1-1.367 1.898l-5.317 1.772a1 1 0 0 1-.632 0L9 19.054 4.632 20.51A2 2 0 0 1 2 18.613V6.72a2 2 0 0 1 1.368-1.898L8.684 3.05zM10 17.28l4 1.334V6.72l-4-1.334V17.28zM8 5.387L4 6.721v11.892l4-1.334V5.387zm8 1.334v11.892l4-1.334V5.387l-4 1.334z"
          fill="#0D0D0D" />
      </svg>
    </button>

    <!--3. Stats-->
    <button class="icon-button" onclick="window.location.href = 'stats.php'" aria-label="Stats">
      <!-- < ?xml version="1.0" encoding="utf-8"?> -->
      <!-- License: MIT. Made by Tienda Nube: https://github.com/TiendaNube/nimbus-icons -->
      <svg fill="#000000" width="24" height="24" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
        <path d="M1.75 13.25V1.5H.5v12a1.24 1.24 0 0 0 1.22 1H15.5v-1.25z" />
        <path d="M3.15 8H4.4v3.9H3.15zm3.26-4h1.26v7.9H6.41zm3.27 2h1.25v5.9H9.68zm3.27-3.5h1.25v9.4h-1.25z" />
      </svg>
    </button>
    <!--4. Profile-->
    <button class="icon-button" onclick="window.location.href = 'profile.php'" aria-label="Profile">
      <!-- < ?xml version="1.0" encoding="utf-8"?> -->
      <!-- License: MIT. Made by basicons: https://basicons.xyz/ -->
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="7" r="4" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M4 21V17C4 15.8954 4.89543 15 6 15H18C19.1046 15 20 15.8954 20 17V21" stroke="#000000" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <!--Settings-->
    <button class="icon-button active" onclick="window.location.href = 'settings.php'" aria-label="Settings">
      <!-- < ?xml version="1.0" encoding="utf-8"?> -->
      <!-- License: PD. Made by mono-company: https://github.com/mono-company/mono-icons -->
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path
          d="M12 4a1 1 0 0 0-1 1c0 1.692-2.046 2.54-3.243 1.343a1 1 0 1 0-1.414 1.414C7.54 8.954 6.693 11 5 11a1 1 0 1 0 0 2c1.692 0 2.54 2.046 1.343 3.243a1 1 0 0 0 1.414 1.414C8.954 16.46 11 17.307 11 19a1 1 0 1 0 2 0c0-1.692 2.046-2.54 3.243-1.343a1 1 0 1 0 1.414-1.414C16.46 15.046 17.307 13 19 13a1 1 0 1 0 0-2c-1.692 0-2.54-2.046-1.343-3.243a1 1 0 0 0-1.414-1.414C15.046 7.54 13 6.693 13 5a1 1 0 0 0-1-1zm-2.992.777a3 3 0 0 1 5.984 0 3 3 0 0 1 4.23 4.231 3 3 0 0 1 .001 5.984 3 3 0 0 1-4.231 4.23 3 3 0 0 1-5.984 0 3 3 0 0 1-4.231-4.23 3 3 0 0 1 0-5.984 3 3 0 0 1 4.231-4.231z"
          fill="#0D0D0D" />
        <path d="M12 10a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-2.828-.828a4 4 0 1 1 5.656 5.656 4 4 0 0 1-5.656-5.656z"
          fill="#0D0D0D" />
      </svg>
    </button>
  </nav>

  <!--Main Content-->
  <main class="main-content">
    <header>
      <h1>Settings</h1>
      <p class="sub-header">
        Configure your system preferences and account security.
      </p>
    </header>

    <?php if (!empty($statusMsg)): ?>
      <div class="status-msg <?php echo $statusType; ?>">
        <?php echo $statusType === 'success' ? '<span>✅</span>' : '<span>⚠️</span>'; ?>
        <span><?php echo htmlspecialchars($statusMsg); ?></span>
      </div>
    <?php endif; ?>

    <!-- Preference Section -->
    <div class="glass-panel">
      <div class="section-title">General Preferences</div>

      <div class="setting-item">
        <div class="item-info">
          <h3>Sound Effects</h3>
          <p>Play mechanical clicks and feedback sounds.</p>
        </div>
        <div class="toggle-switch active" onclick="this.classList.toggle('active')"></div>
      </div>

      <div class="setting-item">
        <div class="item-info">
          <h3>Dark Mode</h3>
          <p>system interface theme (Locked to Cyberpunk/Dark).</p>
        </div>
        <div class="toggle-switch active disabled" title="Theme locked by system admin"></div>
      </div>

      <div class="setting-item">
        <div class="item-info">
          <h3>Haptic Feedback</h3>
          <p>Vibration on mobile device during lesson.</p>
        </div>
        <div class="toggle-switch active" onclick="this.classList.toggle('active')"></div>
      </div>
    </div>

    <!-- Account & Security Section -->
    <div class="glass-panel">
      <div class="section-title">Account & Security</div>

      <div class="setting-item">
        <div class="item-info">
          <h3>Public</h3>
          <p>Allow other learners to see your stats on the leaderboard.</p>
          <div class="toggle-switch" onclick="this.classList.toggle('active')"></div>
        </div>
      </div>

      <!-- Update Email Row -->
      <div class="setting-item setting-item-column">
        <div class="item-header">
          <div class="item-info">
            <h3>Email Address</h3>
            <p><?php echo htmlspecialchars($user['email']); ?></p>
          </div>
          <button class="button-link" onclick="toggleForm('emailForm')">
            Change
          </button>
        </div>
        <form method="POST" id="emailForm" class="hidden-form">
          <input type="hidden" name="action" value="update_email" />
          <div class="form-group">
            <input type="email" name="new_email" class="form-input" placeholder="New Email Address" required />
          </div>
          <div>
            <button type="submit" class="button-submit">Save Changes</button>
            <button type="button" class="button-cancel" onclick="toggleForm('emailForm')">
              Cancel
            </button>
          </div>
        </form>
      </div>

      <!-- Update Password Row -->
      <div class="setting-item setting-item-column">
        <div class="item-header">
          <div class="item-info">
            <h3>Security Passcode</h3>
            <p>Keep your account and progress secure.</p>
          </div>
          <button class="button-link" onclick="toggleForm('passwordForm')">
            Update
          </button>
        </div>
        <form method="POST" id="passwordForm" class="hidden-form">
          <input type="hidden" name="action" value="update_password" />
          <div class="form-group">
            <input type="password" name="current_password" class="form-input" placeholder="Current Password" required />
          </div>
          <div class="form-group">
            <input type="password" name="new_password" class="form-input" placeholder="New Password (Min 6 chars)"
              required />
          </div>
          <div class="form-group">
            <input type="password" name="confirm_password" class="form-input" placeholder="Confirm New Password"
              required />
          </div>
          <div>
            <button type="submit" class="button-submit">Update</button>
            <button type="button" class="button-cancel" onclick="toggleForm('passwordForm')">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Danger Zone -->
    <div class="glass-panel danger-zone">
      <div class="section-title">Danger Zone</div>
      <div class="setting-item setting-item-column" style="align-items: flex-start; gap: 20px">
        <div class="item-info" style="width: 100%">
          <h3>System Disconnect</h3>
          <p>
            Log out of the LingoFlow mainframe. You will need your credentials
            to regain access to your neural training path.
          </p>
        </div>
        <button class="button-danger" onclick="confirmLogout()">
          <!-- < ?xml version="1.0" encoding="utf-8"?> -->
          <!-- License: MIT. Made by radix-ui: https://github.com/radix-ui/icons -->
          <svg width="20" height="20" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd"
              d="M3 1C2.44771 1 2 1.44772 2 2V13C2 13.5523 2.44772 14 3 14H10.5C10.7761 14 11 13.7761 11 13.5C11 13.2239 10.7761 13 10.5 13H3V2L10.5 2C10.7761 2 11 1.77614 11 1.5C11 1.22386 10.7761 1 10.5 1H3ZM12.6036 4.89645C12.4083 4.70118 12.0917 4.70118 11.8964 4.89645C11.7012 5.09171 11.7012 5.40829 11.8964 5.60355L13.2929 7H6.5C6.22386 7 6 7.22386 6 7.5C6 7.77614 6.22386 8 6.5 8H13.2929L11.8964 9.39645C11.7012 9.59171 11.7012 9.90829 11.8964 10.1036C12.0917 10.2988 12.4083 10.2988 12.6036 10.1036L14.8536 7.85355C15.0488 7.65829 15.0488 7.34171 14.8536 7.14645L12.6036 4.89645Z"
              fill="#000000" />
          </svg>
          Initiate Log Out
        </button>
      </div>
    </div>
  </main>
  <script>
    function confirmLogout() {
      if (confirm("Are you sure you want to terminate you session?")) {
        // Triggers the PHP logout handler at the top of the file
        window.location.href = '?action=logout';
      }
    }

    function toggleForm(formId) {
      const form = document.getElementById(formId);
      if (form.classList.contains("active")) {
        form.classList.remove("active");
      } else {
        // Close other open forms before opening a new one
        document
          .querySelectorAll(".hidden-form")
          .forEach((f) => f.classList.remove("active"));
        form.classList.remove("active");

        // Focus the first input of the revealed form smoothly
        setTimeout(() => {
          const firstInput = form.querySelector('input:not([type="hidden"])');
          if (firstInput) firstInput.focus();
        }, 100);
      }
    }
  </script>
</body>

</html>