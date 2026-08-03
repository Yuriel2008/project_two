<?php
// --- 1. SESSION & INIT ---
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
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

  // --- 3. FETCH USER STATS ---
  $userStmt = $pdo->prepare("SELECT xp, streak, accuracy, created_at FROM users WHERE id = ?");
  $userStmt->execute([$user_id]);
  $user = $userStmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    header("Location: login.php");
    exit;
  }

  // --- 4. CALCULATE DERIVED STATS ---

  // Count Completed Lessons
  $progStmt = $pdo->prepare("SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND status = 'completed'");
  $progStmt->execute([$user_id]);
  $lessonsCompleted = $progStmt->fetchColumn();

  // Estimate Time Spent (Formula: Total XP / 50 * 3 minutes per lesson)
  // This gives a realistic approximation based on effort since we don't have a time_log table
  $totalMinutes = ($user['xp'] > 0) ? round(($user['xp'] / 50) * 3) : 0;
  $hours = floor($totalMinutes / 60);
  $mins = $totalMinutes % 60;
  $timeString = "{$hours}h {$mins}m";

  // --- 5. FETCH ACHIEVEMENTS ---
  // Left Join to get all achievements + status for this user
  $achSql = "
     SELECT a.id, a.name, a.description, a.icon_emoji, unlock_at
     FROM achievements a
     LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
";
  $achStmt = $pdo->prepare($achSql);
  $achStmt->execute([$user_id]);
  $achievements = $achStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LingoFlow v2 - Stats</title>
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
      border-radius: 1px solid var(--border);
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
      margin: 10px;
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

    /* --- 4. STATS LAYOUT --- */
    .stats-layout {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 24px;
    }

    .glass-panel {
      background: var(--glass);
      backdrop-filter: blur(10px);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 24px;
    }

    /* Activity Chart */
    .chart-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }

    .chart-container {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      height: 200px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border);
    }

    .bar-group {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
      height: 100%;
      justify-content: flex-end;
      width: 100%;
    }

    .bar {
      width: 12px;
      background: rgba(139, 92, 246, 0.3);
      border-radius: 4px;
      transition: height 0.5s ease;
      position: relative;
      min-height: 4px;
    }

    .bar:hover {
      width: 12px;
      background: rgba(139, 92, 246, 0.3);
      border-radius: 4px;
      transition: height 0.5s ease;
      position: relative;
      min-height: 4px;
    }

    .bar.high {
      background: var(--cyan);
      box-shadow: 0 0 10px rgba(6, 182, 212, 0.5);
    }

    .bar-label {
      font-size: 12px;
      color: var(--text-muted);
      font-weight: bold;
    }

    /* Overview List */
    .overview-list {
      display: flex;
      flex-direction: column;
      gap: 0;
    }

    .stat-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 0;
      border-bottom: 1px solid var(--border);
    }

    .stat-row:last-child {
      border-bottom: none;
    }

    .stat-label {
      font-size: 14px;
      color: var(--text-muted);
    }

    .stat-value {
      font-size: 18px;
      font-weight: bold;
      color: white;
    }

    /* Achievements */
    .achievement-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 20px;
      margin-top: 10px;
    }

    .ach-card {
      background: var(--glass);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 20px;
      display: flex;
      align-items: center;
      gap: 15px;
      transition: 0.2s;
    }

    .ach-card:hover {
      transform: translateY(-2px);
      background: rgba(255, 255, 255, 0.08);
    }

    .ach-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid var(--border);
    }

    .ach-info {
      margin: 0;
      font-size: 15px;
    }

    .ach-info {
      margin: 4px 0 0 0;
      font-size: 12px;
      color: var(--text-muted);
    }

    /* Locked State Styling */
    .ach-card.locked {
      opacity: 0.5s;
      filter: grayscale(1);
    }

    .ach-card.locked .ach-icon {
      border-color: transparent;
      background: #1e293b;
    }

    /* --- 5. RESPONSIVE --- */
    @media (max-width: 900px) {
      .stats-layout {
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
        border-right: none;
        border-top: 1px solid var(--border);
        background: #020617;
      }

      .logo-boxes {
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
    <button class="icon-button" onclick="window.location.href='index.php'" aria-label="Home">
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
    <button class="icon-button" onclick="window.location.href='roadmap.php'" aria-label="Roadmap">
      <!-- < ?xml version="1.0" encoding="utf-8"?> -->
      <!-- License: PD. Made by mono-company: https://github.com/mono-company/mono-icons -->
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path
          d="M8.684 3.051a1 1 0 0 1 .632 0L15 4.946l4.367-1.456A2 2 0 0 1 22 5.387V17.28a2 2 0 0 1-1.367 1.898l-5.317 1.772a1 1 0 0 1-.632 0L9 19.054 4.632 20.51A2 2 0 0 1 2 18.613V6.72a2 2 0 0 1 1.368-1.898L8.684 3.05zM10 17.28l4 1.334V6.72l-4-1.334V17.28zM8 5.387L4 6.721v11.892l4-1.334V5.387zm8 1.334v11.892l4-1.334V5.387l-4 1.334z"
          fill="#0D0D0D" />
      </svg>
    </button>

    <!--3. Stats-->
    <button class="icon-button active" aria-label="Stats">
      <!-- < ?xml version="1.0" encoding="utf-8"?> -->
      <!-- License: MIT. Made by Tienda Nube: https://github.com/TiendaNube/nimbus-icons -->
      <svg fill="#000000" width="24" height="24" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
        <path d="M1.75 13.25V1.5H.5v12a1.24 1.24 0 0 0 1.22 1H15.5v-1.25z" />
        <path d="M3.15 8H4.4v3.9H3.15zm3.26-4h1.26v7.9H6.41zm3.27 2h1.25v5.9H9.68zm3.27-3.5h1.25v9.4h-1.25z" />
      </svg>
    </button>
    <!--4. Profile-->
    <button class="icon-button" onclick="window.location.href='profile.php'" aria-label="Profile">
      <!-- < ?xml version="1.0" encoding="utf-8"?> -->
      <!-- License: MIT. Made by basicons: https://basicons.xyz/ -->
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="7" r="4" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M4 21V17C4 15.8954 4.89543 15 6 15H18C19.1046 15 20 15.8954 20 17V21" stroke="#000000" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <!--Settings-->
    <button class="icon-button" onclick="window.location.href='settings.php'" aria-label="Settings">
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
      <div>
        <h1>Statistics</h1>
        <p class="sub-header">Analyze your learning performance.</p>
      </div>
    </header>

    <div class="stats-layout">

      <!-- Left: Activity Chart (Simulated Visualization) -->
      <div class="glass-panel">
        <div class="chart-header">
          <h3 style="margin: 0;">Weekly Activity</h3>
          <span style="color: var(--cyan); font-size: 14px;">+15% XP vs last week</span>
        </div>

        <div class="chart-container">
          <?php
          // Randomized bar heights to simulate activity for prototype visual 
          $days = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
          foreach ($days as $day):
            $h = rand(20, 95);
            $class = ($h > 90) ? 'high' : '';
            ?>

            <div class="bar-group">
              <div class="bar <?php echo $class; ?>"></div>
              <span class="bar-label"><?php echo $day; ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Right: Overview  -->
      <div class="glass-panel overview-list">
        <h3 style="margin-top: 0;">Overview</h3>

        <div class="stat-row">
          <span class="stat-label">Total XP Earned</span>
          <span class="stat-value" style="color: var(--violet);"><?php echo number_format($user['xp']); ?></span>
        </div>

        <div class="stat-row">
          <span class="stat-label">Lesson Completed</span>
          <span class="stat-value" style="color: var(--cyan);"><?php echo $lessonsCompleted; ?></span>
        </div>
        <div class="stat-row">
          <span class="stat-label">Average Accuracy</span>
          <span class="stat-value" style="color: var(--green);"><?php echo $user['accuracy']; ?> </span>
        </div>

        <div class="stat-row">
          <span class="stat-label">Time Spent</span>
          <span class="stat-value"><?php echo $timeString; ?></span>
        </div>

        <div class="stat-row">
          <span class="stat-label">Current Streak</span>
          <span class="stat-value" style="color: var(--orange);"><?php echo $user['streak']; ?>
            Days</span>
        </div>
      </div>
    </div>

    <!-- Achievements Section -->
    <h2 style="margin: 30px 0 15px 0;">Achievements</h2>
    <div class="achievement-grid">
      <?php foreach ($achievements as $ach): ?>
        <?php
        $isUnlocked = !empty($ach['unlock_at']);
        $class = $isUnlocked ? '' : 'locked';
        // Simple color cycling for unlocked icons -->
        $colors = ['var(--violet)', 'var(--cyan)', 'var(--green)', 'var(--orange)', 'var(--red)'];
        $colorStyle = $isUnlocked ? "color:" . $colors[$ach['id'] % 4] . "; border-color:" . $colors[$ach['id'] % 4] . ";" : "";
        ?>
        <div class="ach-card <?php echo $class; ?>">
          <div class="ach-icon" style="<?php echo $colorStyle; ?>"><?php echo $ach['icon_emoji']; ?></div>
          <div class="ach-info">
            <h4><?php echo htmlspecialchars($ach['name']); ?></h4>
            <p><?php echo htmlspecialchars($ach['description']); ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
</body>

</html>