<?php
// --- 1. SESSION & AUTHENTICATION ---
session_start();

// Redirect to login if session is missing
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user_id'];

// --- 2. DATABASE CONNECTION (Self-Contained) ---
$dbHost = 'localhost';
$dbName = 'lingoflow_db';
$dbUser = 'root';
$dbPass = ''; // Update with your password if needed

try {
  $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


  // --- 3. FETCH USER STATS ---
  $stmt = $pdo->prepare("SELECT username, xp, streak, accuracy, rank FROM users WHERE id = ?");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    // User invalid/deleted, kill session
    session_destroy();
    header("Location: login.php");
    exit;
  }

  // Calculate Level (Simple logic: 1 Level per 200 XP)
  $level = floor($user['xp'] / 200) + 1;

  // --- 4. FETCH NEXT ACTIVE LESSON ---
  // Join lessons with user_progress to find the current 'active' lesson
  $lessonSmt = $pdo->prepare("
      SELECT l.id, l.title, l.type
      FROM lessons l
      LEFT JOIN user_progress up ON l.id = up.lesson_id AND up.user_id = ?
      WHERE up.status IS NULL OR up.status != 'completed'
      ORDER BY l.id ASC
      LIMIT 1
      ");
  $lessonSmt->execute([$user_id]);
  $nextLesson = $lessonSmt->fetch(PDO::FETCH_ASSOC);

  // Fallback if no active lesson (e.g., all completed or none started), find first locked or default
  if (!$nextLesson) {
    $nextLesson = [
      'title' => 'Course Complete',
      'category' => 'Mastery',
      'id' => 'roadmap' // specific handling
    ];

  }

  // --- 5. FETCH STATUSES FOR GRID ---
  // specific IDs used in the HTML grid
  $gridIds = ['emotions', 'nmbers1', 'respect', 'food'];
  $placeholders = str_repeat('?,', count($gridIds) - 1) . '?';

  $gridSmt = $pdo->prepare("SELECT lesson_id, title, status
      FROM user_progress 
      WHERE user_id = ? AND lesson_id IN ($placeholders)");

  $gridParams = array_merge([$user_id], $gridIds);
  $gridSmt->execute($gridParams);
  $rawStatuses = $gridSmt->fetchAll(PDO::FETCH_KEY_PAIR); // returns ['emotions' => 'locked', etc]

  // Helper to check status
  function getGridStatus($id, $map)
  {
    return $map[$id] ?? 'locked'; // // Default to locked if not found in progress table. Or Tab by default to locked if not found
  }


} catch (PDOException $e) {
  die("System Error: Database connection failed. " . $e->getMessage());
}



?>


<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LingoFlow v2 - Dashboard</title>
</head>
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
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  h1 {
    margin: 0;
    font-size: 28px;
    letter-spacing: -0.5px;
  }

  .welcome-sub {
    color: var(--text-muted);
    margin: 5px 0 0 0;
  }

  .user-badge {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--glass);
    padding: 8px 16px;
    border-radius: 50px;
    border: 1px solid var(--border);
    cursor: pointer;
    transition: 0.2s;
  }

  .user-badge:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--violet);
  }

  .avatar-small {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, var(--violet), #6d28d9);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
    color: white;
  }

  /* --- 4. DASHBOARD WIDGETS --- */
  .glass-panel {
    background: var(--glass);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
  }

  /* Stats HUD */
  .stat-card {
    display: flex;
    align-items: center;
    gap: 15px;
    transition: trannsform 0.2s;
  }

  .stats-hud {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }

  .stats-card:hover {
    transform: translateY(-2px);
    background: rgba(255, 255, 255, 0.08);
  }

  .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
  }

  /* Hero / Continue Learning */
  .hero-section {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
  }

  .continue-card {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  /* Neon Glow Effect inside card */
  .continue-card::before {
    content: "";
    position: absolute;
    top: -50px;
    right: -50px;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle,
        var(--violet-glow) 0%,
        transparent 70%);
    opacity: 0.4s;
    pointer-events: none;
  }

  .button-primary {
    margin-top: 20px;
    background: var(--violet);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 16px;
    cursor: pointer;
    width: fit-content;
    box-shadow: 0 4px 15px var(--violet-glow);
    transition: 0.2s;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .button-primary.stats-card:hover {
    background: #7c3aed;
    transform: scale(1, 02);
  }

  /* Daily Goal */
  .goal-card h3 {
    margin: 0 0 15px 0;
    font-size: 18px;
  }

  .progress-track {
    height: 12px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 10px;
  }

  .progress-fill {
    height: 100%;
    background: var(--cyan);
    width: 75%;
    border-radius: 6px;
    box-shadow: 0 0 10px rgba(6, 182, 212, 0.5);
  }

  .goal-text {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: var(--text-muted);
  }

  /* Quick Categories / Grid */
  .grid-header {
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
  }

  .categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
  }

  .cat-box {
    text-align: center;
    padding: 30px 20px;
    cursor: pointer;
    transition: 0.2s;
    border: 1px solid transparent;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .cat-box:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--violet);
    transform: translateY(-0.5px);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
  }

  /* Locked State Helper */
  .cat-box.locked {
    opacity: 0.5s;
    cursor: not-allowed;
    filter: grayscale(1);
  }

  .cat-box.locked:hover {
    background: var(--glass);
    border-color: transparent;
    transform: none;
    box-shadow: none;
  }

  .cat-emoji {
    font-size: 40px;
    margin-bottom: 15px;
    filter: drop-shadow();
  }

  /* --- 5. RESPONSIVE --- */
  @media (max-width: 768px) {
    body {
      grid-template-columns: 1fr;
      grid-template-rows: 1fr 70px;
      /* Content + Bottom Bar */
    }

    .sidebar {
      flex-direction: row;
      justify-content: space-around;
      padding-top: 0;
      grid-row: 2;
      border-right: none;
      border-top: 1px solid var(--border);
      background: #020617;
      /* Solid bg for mobile nav */
    }

    .logo-boxes {
      display: none;
    }

    .main-content {
      padding: 20px;
    }

    .stats-hud {
      grid-template-columns: 1fr;
    }

    .hero-section {
      grid-template-columns: 1fr;
    }
  }

  .stats-echo-user {
    font-size: 24px;
    font-weight: bold;
  }

  .stats-text {
    font-size: 13px;
    color: var(--text-muted);
  }
</style>

<body>
  <!--SideBar-->
  <nav class="sidebar">
    <div class="logo-boxes">L</div>

    <!--1. Home (Active)-->
    <!-- the class  -->
    <button class="icon-button active" aria-label="Home" onclick="window.location.href = 'dashboard.php'">
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
    <button class="icon-button" aria-label="RoadMap" onclick="window.location.href = 'roadmap.php'">
      <!-- < ?xml version="1.0" encoding="utf-8"?> -->
      <!-- License: PD. Made by mono-company: https://github.com/mono-company/mono-icons -->
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path
          d="M8.684 3.051a1 1 0 0 1 .632 0L15 4.946l4.367-1.456A2 2 0 0 1 22 5.387V17.28a2 2 0 0 1-1.367 1.898l-5.317 1.772a1 1 0 0 1-.632 0L9 19.054 4.632 20.51A2 2 0 0 1 2 18.613V6.72a2 2 0 0 1 1.368-1.898L8.684 3.05zM10 17.28l4 1.334V6.72l-4-1.334V17.28zM8 5.387L4 6.721v11.892l4-1.334V5.387zm8 1.334v11.892l4-1.334V5.387l-4 1.334z"
          fill="#0D0D0D" />
      </svg>
    </button>

    <!--3. Stats-->
    <button class="icon-button" aria-label="Stats" onclick="window.location.href = 'stats.php'">
      <!-- < ?xml version="1.0" encoding="utf-8"?> -->
      <!-- License: MIT. Made by Tienda Nube: https://github.com/TiendaNube/nimbus-icons -->
      <svg fill="#000000" width="24" height="24" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
        <path d="M1.75 13.25V1.5H.5v12a1.24 1.24 0 0 0 1.22 1H15.5v-1.25z" />
        <path d="M3.15 8H4.4v3.9H3.15zm3.26-4h1.26v7.9H6.41zm3.27 2h1.25v5.9H9.68zm3.27-3.5h1.25v9.4h-1.25z" />
      </svg>
    </button>
    <!--4. Profile-->
    <button class="icon-button" aria-label="Profile" onclick="window.location.href = 'profile.php'">
      <!-- < ?xml version="1.0" encoding="utf-8"?> -->
      <!-- License: MIT. Made by basicons: https://basicons.xyz/ -->
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="7" r="4" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M4 21V17C4 15.8954 4.89543 15 6 15H18C19.1046 15 20 15.8954 20 17V21" stroke="#000000" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <!--Settings-->
    <button class="icon-button" aria-label="Settings" onclick="window.location.href = 'settings.php'">
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
        <h1>Dashboard</h1>
        <p class="welcome-sub">
          System Online. Welcome back, <strong><?php echo htmlspecialchars($user['username']); ?></strong>.
        </p>
      </div>
      <div class="user-badge" onclick="window.location.href = 'profile.php'" style="cursor: pointer">
        <div class="avatar-small"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
        <div>
          <div style="font-weight: bold; font-size: 14px">
            Level <?php echo $level; ?>
          </div>
          <div style="font-size: 11px; color: var(--text-muted)">
            <?php echo htmlspecialchars($user['rank']); ?>
          </div>
        </div>
      </div>
    </header>

    <!-- HUD Stats -->
    <div class="stats-hud">
      <div class="glass-panel stat-card">
        <div class="stat-icon" style="background: rgba(251, 146, 60, 0.2); color: var(--orange)">
          🔥
        </div>
        <div>
          <div class="stats-echo-user"><?php echo $user['streak']; ?></div>
          <div class="stats-text">Day Streak</div>
        </div>
      </div>
      <div class="glass-panel stat-card">
        <div class="stat-icon" style="background: rgba(139, 92, 246, 0.2); color: var(--violet);">⚡</div>
        <div>
          <div class="stats-echo-user"><?php echo number_format($user['xp']); ?></div>
          <div class="stats-text">Total XP</div>
        </div>
      </div>
      <div class="glass-panel stat-card">
        <div class="stat-icon" style="background: rgba(6, 182, 212, 0.2); color: var(--cyan);">🎯</div>
        <div>
          <div class="stats-echo-user"><?php echo $user['accuracy']; ?>%</div>
          <div class="stats-text">Accuracy</div>
        </div>
      </div>
    </div>

    <!-- Hero Section -->
    <div class="hero-section">
      <!-- Main Activity -->
      <div class="glass-panel continue-card">
        <h2 style="margin: 0; z-index: 1;">Continue Learning</h2>
        <?php if ($nextLesson['id'] !== 'roadmap'): ?>
          <p style="color: var(--text-muted); margin: 10px 0 0 0; z-index: 1;">
            Next Up: <strong><?php echo htmlspecialchars($nextLesson['title']); ?></strong>
            (<?php echo htmlspecialchars($nextLesson['category']); ?>)
          </p>

          <button class="button-primary"
            onclick="window.location.href = 'lesson.php?type=<?php echo htmlspecialchars($nextLesson['id']); ?>'">
            <span>Resume Path</span>
            <svg fill="#000000" width="18" height="18" viewBox="0 0 24 24" id="right" data-name="Flat Color"
              xmlns="http://www.w3.org/2000/svg" class="icon flat-color">
              <path id="primary"
                d="M21.71,11.29l-7-7a1,1,0,0,0-1.42,1.42L18.59,11H3a1,1,0,0,0,0,2H18.59l-5.3,5.29a1,1,0,0,0,0,1.42,1,1,0,0,0,1.42,0l7-7A1,1,0,0,0,21.71,11.29Z"
                style="fill: rgb(0, 0, 0)"></path>
            </svg>
          </button>
        <?php else: ?>
          <p style="color: var(--text-muted); margin: 10px 0 0 0; z-index: 1;">You are up to date! Review completed
            lessons?</p>
          <button class="button-primary" onclick="window.location.href = 'roadmap.php'">
            <span>View Roadmap</span>
          </button>
        <?php endif; ?>
      </div>

      <!-- Daily Goal -->
      <?php
      // Simulating daily progress based on total XP to show movement
      $dailyTarget = 100; // Example daily target
      $dailyProgress = $user['xp'] % 100;
      $dailyPercent = ($dailyProgress / $dailyTarget) * 100; // Example progress calculation
      ?>
      <div class="glass-panel goal-card">
        <h3>Daily Goal</h3>
        <div class="progress-track">
          <div class="progress-fill" style="<?php echo $dailyPercent; ?>%;"></div>
        </div>
        <div class="goal-text">
          <span><?php echo $dailyProgress; ?>XP</span>
          <span>Target: <?php echo $dailyTarget; ?> XP</span>
        </div>
        <p style="font-size: 12px; color: var(--text-muted); margin-top: 15px; text-align: center;">Just one more lesson
          to hit your daily target! 🚀</p>
      </div>
    </div>

    <!-- Quick Categories -->
    <div>
      <div class="grid-header">
        <h3 style="margin: 0;">Quick Practice</h3>
        <a href="roadmap.php"
          style="color: var(--violet); text-decoration: none; font-size: 14px; font-weight: bold;">View All &rarr;</a>
      </div>

      <div class="categories-grid">
        <!-- Emotion Lesson -->
        <?php $st = getGridStatus('emotions', $rawStatuses); ?>
        <div class="glass-panel cat-box <?php echo ($st == 'locked') ? 'locked' : ''; ?>" <?php if ($st != 'locked'): ?>onclick="window.location.href='lesson.php?type=emotions'" <?php endif; ?>>
          <div class="cat-emoji">😊</div>
          <strong>Emotions</strong>
        </div>

        <!-- Number Lesson -->
        <?php $st = getGridStatus('number1', $rawStatuses); ?>
        <div class="glass-panel cat-box <?php echo ($st == 'locked') ? 'locked' : ''; ?>" <?php if ($st != 'locked'): ?>onclick="window.location.href='lesson.php?type=nmbers1'" <?php endif; ?>>
          <div class="cat-emoji">🔢</div>
          <strong>Numbers</strong>
        </div>

        <!-- Greetings Lesson -->
        <?php $st = getGridStatus('repect', $rawStatuses); ?>
        <div class="glass-panel cat-box <?php echo ($st == 'locked') ? 'locked' : ''; ?>" <?php if ($st != 'locked'): ?>onclick="window.location.href='lesson.php?type=respect'" <?php endif; ?>>
          <div class="cat-emoji">🤝</div>
          <strong>Greetings</strong>
        </div>

        <!-- Locked Lesson -->
        <?php $st = getGridStatus('food', $rawStatuses); ?>
        <div class="glass-panel cat-box <?php echo ($st == 'locked') ? 'locked' : ''; ?>" <?php if ($st != 'locked'): ?>onclick="window.location.href = 'lesson.php?type=food'" <?php endif; ?>>
          <div class="cat-emoji">🍔</div>
          <strong>Food</strong>
        </div>
      </div>
    </div>
  </main>
</body>

</html>