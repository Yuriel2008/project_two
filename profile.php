<?php
// --- 1. SESSION & INITATE ---
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit();
}

$user_id = $_SESSION['user_id'];

// --- 2. DATABASE CONNECTION ---
$dbHost = 'localhost';
$dbName = 'lingoflow_db';
$dbUser = 'root';
$dbPass = '';

try {
  $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // --- 3. FETCH USER DATA ---
  $stmt = $pdo->prepare("SELECT username, email, xp, streak, accuracy, rank, created_at FROM users WHERE id = ?");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  $totalMinutes = ($user['xp'] > 0) ? round(($user['xp'] / 50) * 3) : 0;
  $hoursSpent = floor($totalMinutes / 60) . "h " . ($totalMinutes % 60) . "m";

  if (!$user) {
    session_destroy();
    // User not found, redirect to login
    header('Location: login.php');
    exit();
  }

  // Calculations
  $level = floor($user['xp'] / 200) + 1;
  $joinDate = date("F Y", strtotime($user['created_at']));

  // Daily Goal Calc (Logic: 100 XP per day cycle)
  $dailyTarget = 100;
  $dailyProgress = $user['xp'] % 100;// distinct from total, just for visual loop
  $dailyPercent = ($dailyProgress / $dailyTarget) * 100;

  // --- 4. FETCH ACHIEVEMENTS ---
  // Get all definitions
  $achStmt = $pdo->query("SELECT * FROM achievements");
  $allAchievements = $achStmt->fetchAll(PDO::FETCH_ASSOC);

  // Get user's unlocked IDs
  $userAchStmt = $pdo->prepare("SELECT achievement_id FROM user_achievements WHERE user_id = ?");
  $userAchStmt->execute([$user_id]);
  $unlockIds = $userAchStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
  die("Database Error: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LingoFlow v2 - Profile</title>
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
      /* Global Grid Layout */
      display: grid;
      grid-template-columns: 80px 1fr;
    }

    /* --- 2. SIDEBAR NAVIGATION --- */
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
    }

    .logo-boxes {
      width: 40px;
      height: 40px;
      background: rgba(139, 92, 246, 0.2);
      color: var(--violet);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 20px;
      margin-bottom: 10px;
      box-shadow: 0 0 15px rgba(139, 92, 246, 0.2);
    }

    .icon-button {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      border: none;
      background: transparent;
      color: #64748b;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .icon-button:hover {
      background: rgba(255, 255, 255, 0.1);
      color: white;
      transform: scale(1.05);
    }

    .icon-button.active {
      background: var(--violet);
      color: white;
      box-shadow: 0 0 15px var(--violet-glow);
    }

    /* --- 3. MAIN CONTENT --- */
    .main-content {
      padding: 40px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 30px;
    }

    header {
      margin-bottom: 10px;
    }

    h1 {
      margin: 0;
      font-size: 28px;
      letter-spacing: -0.5px;
    }

    .sub-header {
      color: var(--text-muted);
      margin: 5px 0 0 0;
    }

    /* --- 4. PROFILE LAYOUT --- */
    .profile-layout {
      display: grid;
      grid-template-columns: 350px 1fr;
      gap: 30px;
      align-items: start;
    }

    .glass-panel {
      background: var(--glass);
      backdrop-filter: blur(10px);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 30px;
    }

    /* Identity Card (Left) */
    .identity-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .avatar-large {
      width: 120px;
      height: 120px;
      background: linear-gradient(135deg, var(--violet), #4c1d95);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 48px;
      color: white;
      box-shadow: 0 0 30px var(--violet-glow);
      margin-bottom: 20px;
      border: 4px solid rgba(255, 255, 255, 0.1);
      position: relative;
      z-index: 1;
    }

    .edit-badge {
      position: absolute;
      bottom: 5px;
      right: 5px;
      width: 30px;
      height: 30px;
      background: #1e293b;
      border: 1px solid var(--border);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      cursor: pointer;
      transition: 0.2s;
    }

    .edit-badge:hover {
      background: var(--violet);
    }

    .user-name {
      font-size: 24px;
      font-weight: bold;
      margin: 0;
    }

    .join-date {
      color: var(--text-muted);
      font-size: 14px;
      margin: 5px 0 20px 0;
    }

    .badges-row {
      display: flex;
      gap: 10px;
      margin-bottom: 25px;
      flex-wrap: wrap;
      justify-content: center;
    }

    .badge-pill {
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: bold;
      border: 1px solid transparent;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .badge-rank {
      background: rgba(139, 92, 246, 0.15);
      color: var(--violet);
      border-color: rgba(139, 92, 246, 0.3);
    }

    .badge-level {
      background: rgba(6, 182, 212, 0.15);
      color: var(--cyan);
      border-color: rgba(6, 182, 212, 0.3);
    }

    .button-outline {
      width: 100%;
      padding: 12px;
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text-main);
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s;
    }

    .button-outline:hover {
      background: rgba(255, 255, 255, 0.05);
      border-color: var(--violet);
      color: white;
    }

    /* Stats Grid (Right) */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-box {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid var(--border);
      padding: 20px;
      display: flex;
      align-items: center;
      gap: 20px;
      transition: transform 0.2s;
    }

    .stat-box:hover {
      transform: translateY(-2px);
      background: rgba(255, 255, 255, 0.06);
    }

    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
    }

    .stat-value {
      font-size: 24px;
      font-weight: bold;
      line-height: 1.2;
    }

    .stat-label {
      font-size: 13px;
      color: var(--text-muted);
    }

    /* Goals Section */
    h3 {
      margin: 0 0 20px 0;
      font-size: 18px;
    }

    .goal-item {
      margin-bottom: 25px;
    }

    .goal-info {
      display: flex;
      justify-content: space-between;
      font-size: 14px;
      margin-bottom: 8px;
    }

    .progress-track {
      height: 10px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 5px;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      border-radius: 5px;
      transition: width 1s ease;
    }

    /* Achievement Gallery */
    .achievements-row {
      display: flex;
      gap: 15px;
      overflow-x: auto;
      padding-bottom: 10px;
    }

    .achievement-item {
      min-width: 80px;
      text-align: center;
      cursor: pointer;
    }

    .ach-icon {
      width: 60px;
      height: 60px;
      background: var(--glass);
      border: 1px solid var(--border);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      margin: 0 auto 8px auto;
      transition: 0.2s;
    }

    .achievement-item:hover .ach-icon {
      background-color: var(--violet);
      background: rgba(139, 92, 246, 0.1);
    }

    .ach-name {
      font-size: 12px;
      color: var(--text-muted);
    }

    .locked .ach-icon {
      opacity: 0.3;
      filter: grayscale(1);
      border-color: transparent;
    }

    /* --- 5. RESPONSIVE --- */
    @media (max-width: 900px) {
      .profile-layout {
        grid-template-columns: 1fr;
      }

      .stats-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 768px) {
      body {
        grid-template-columns: 1fr;
        grid-template-rows: 1fr 70px;
      }

      .sidebar {
        flex-direction: row;
        justify-content: space-around;
        padding-top: 0;
        grid-row: 2;
        border-right: navajowhite;
        border-top: 1px solid var(--border);
        background: #020617;
      }

      .logo-box {
        display: none;
      }

      .main-content {
        padding: 20px;
      }
    }
  </style>
</head>

<body>
  <!--SideBar-->
  <nav class="sidebar">
    <div class="logo-boxes">L</div>

    <!--1. Home (Active)-->
    <!-- Every button class has button-one to five below this texts -->
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
    <!--4. Active Profile-->
    <button class="icon-button active" onclick="window.location.href = 'profile.php'" aria-label="Profile">
      <!-- < ?xml version="1.0" encoding="utf-8"?> -->
      <!-- License: MIT. Made by basicons: https://basicons.xyz/ -->
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="7" r="4" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M4 21V17C4 15.8954 4.89543 15 6 15H18C19.1046 15 20 15.8954 20 17V21" stroke="#000000" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <!--Settings-->
    <button class="icon-button" onclick="window.location.href = 'settings.php'" aria-label="Settings">
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
    <!--Header-->
    <header>
      <h1>Profile</h1>
      <p class="sub-header">Manage your identity and progress.</p>
    </header>

    <div class="profile-layout">
      <!-- LEFT: Identity Card -->
      <div class="glass-panel identity card">
        <div class="avatar-large">
          <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
          <div class="edit-badge" title="Upload Avatar">📷</div>
        </div>
        <h2 class="user-name"><?php echo htmlspecialchars($user['username']); ?></h2>
        <p class="join-date">Joined <?php echo $joinDate; ?></p>

        <div class="badges-row">
          <div class="badge-pill badge-rank">
            <span>🏆</span><?php echo htmlspecialchars($user['rank']); ?>
          </div>
          <div class="badge-pill badge-level">
            <span>⚡</span> Level <?php echo $level; ?>
          </div>
        </div>
        <button class="button-outline">Edit Profile</button>
      </div>

      <!-- RIGHT: Stats & Goals -->
      <div class="stats-section">
        <!-- Stats Grid -->
        <div class="stats-grid">
          <div class="stat-box">
            <div class="stat-icon" style="
                  background: rgba(139, 92, 246, 0.2);
                  color: var(--violet);
                ">
              ⚡
            </div>
            <div>
              <div class="stat-value"><?php echo number_format($user['xp']); ?></div>
              <div class="stat-label">Total XP</div>
            </div>
          </div>
          <div class="stat-box">
            <div class="stat-icon" style="background: rgba(6, 182, 212, 0.2); color: var(--green)">
              🎯
            </div>
            <div>
              <div class="stat-value"><?php echo $user['accuracy']; ?>%</div>
              <div class="stat-label">Accuracy</div>
            </div>
          </div>
          <div class="stat-box">
            <div class="stat-icon" style="background: #2b5748; color: var(--green)">
              ⏱
            </div>
            <div>
              <div class="stat-value"><?php echo $hoursSpent; ?></div>
              <div class="stat-label">Time Spent</div>
            </div>
          </div>
          <div class="stat-box">
            <div class="stat-icon" style="
                  background: rgba(251, 146, 60, 0.2);
                  color: var(--orange);
                ">
              🔥
            </div>
            <div>
              <div class="stat-value"><?php echo $user['streak']; ?></div>
              <div class="stat-label">Day Streak</div>
            </div>
          </div>
        </div>

        <!-- Learning Goals -->
        <div class="glass-panel">
          <h3>Learning Goals</h3>

          <div class="goal-item">
            <div class="goal-info">
              <span>Daily XP</span>
              <span style="color: var(--violet); font-weight: bold"><?php echo $dailyProgress; ?>
                / 100</span>
            </div>
            <div class="progress-track">
              <div class="progress-fill" style=" width: <?php echo $dailyProgress; ?>%;
                    background: var(--violet);
                    box-shadow: 0 0 10px var(--violet-glow);
                  "></div>
            </div>
          </div>

          <div class="goal-item">
            <div class="goal-info">
              <span>Weekly Lessons</span>
              <span style="color: var(--cyan); font-weight: bold">4 / 7</span>
            </div>
            <div class="progress-track">
              <div class="progress-fill" style="
                    width: 57%;
                    background: var(--cyan);
                    box-shadow: 0 0 10px rgba(6, 182, 212, 0.5);
                  "></div>
            </div>
          </div>

          <h3>Recent Achievements</h3>
          <div class="achievements-row">
            <?php foreach ($allAchievements as $ach):
              $isUnlocked = in_array($ach['id'], $unlockIds);
              $class = $isUnlocked ? '' : 'locked';
              // <!-- // Simple color rotation based on ID for visuals, or default -->
              $colors = ['var(--green)', 'var(--orange)', 'var(--violet)', 'var(--cyan)'];
              $color = $isUnlocked ? $colors[$ach['id'] % 4] : '';
              ?>
              <div class="achievement-item <?php echo $class; ?>">
                <div class="ach-icon" style="<?php echo $isUnlocked ? "color: $color;" : ""; ?>">
                  <?php echo $ach['icon_emoji']; ?>
                </div>
                <div class="ach-name"><?php echo htmlspecialchars($ach['name']); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</body>

</html>