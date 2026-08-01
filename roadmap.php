<?php
// --- 1. SESSION & INITATE ---

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit();
}

$userId = $_SESSION['user_id'];

// --- 2. DATABASE CONNECTION ---
$dbHost = 'localhost';
$dbName = 'lingoflow_db';
$dbUser = 'root';
$dbPass = ''; // Update with your database password

try {
  $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // --- 3. FETCH ROADMAP DATA ---
  // We select all lessons and join with user_progress to see the status for THIS user.
  // IF no progress row exists, we default to 'locked' (handled in logic or SQL COALESCE).
  $sql = "
    SELECT 
      l.id,
      l.title,
      l.description, 
      COALESCE(up.status, 'locked') AS status
    FROM lessons l
    LEFT JOIN user_progress up ON l.id = up.lesson_id AND up.user_id = ?
    ORDER BY l.sort_order ASC";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([$userId]);
  $roadmapItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
  die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LingoFlow v2 - Roadmap</title>
  <style>
    :root {
      --bg-deep: #020617;
      --text-main: #e2e8f0;
      --text-muted: #94a3b8;
      --violet: #8b5cf6;
      --violet-glow: rgba(139, 92, 246, 0.5);
      --cyan: #06b6d4;
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
      background: var(--violet-glow);
      color: white;
      box-shadow: 0 0 15px var(--violet-glow);
    }

    /* --- 3. MAIN CONTENT --- */
    .main-content {
      height: 100vh;
      padding: 40px;
      overflow-y: auto;
    }

    header {
      margin-bottom: 40px;
      max-width: 800px;
      margin-left: auto;
      margin-right: auto;
    }

    h1 {
      margin: 0;
      font-size: 28px;
      letter-spacing: -0.5px;
    }

    .subtitle {
      color: var(--text-muted);
      margin: 50px 0 0 0;
    }

    /* --- 4. TIMELINE STYLES --- */
    .timeline-container {
      max-width: 600px;
      margin: 0 auto 50px auto;
      position: relative;
      padding-left: 20px;
    }

    /* The Vertical Line */
    .timeline-line {
      position: absolute;
      left: 29px;
      /* Center of the node circle */
      top: 20px;
      bottom: 0;
      width: 2px;
      background: linear-gradient(to bottom, var(--cyan), var(--violet), rgba(255, 255, 255, 0.1));
      z-index: 0;
    }

    /* The Node (Circle) */
    .node {
      width: 20px;
      height: 20px;
      background: var(--bg-deep);
      border: 4px solid var(--bg-deep);
      z-index: 1;
      transition: 0.3s;
      box-shadow: 0 0 0 4px var(--bg-deep);
      /* Masking the line */
    }

    /* Completed State */
    .timeline-line.completed .node {
      background: var(--cyan);
      border-color: var(--cyan);
      box-shadow: 0 0 15px rgba(6, 182, 212, 0.5), 0 0 0 4px var(--bg-deep);
    }

    /* Active State */
    .timeline-line.active .node {
      background: var(--bg-deep);
      border-color: var(--violet);
      width: 24px;
      height: 24px;
      box-shadow: 0 0 20px var(--violet-glow), 0 0 0 4px var(--bg-deep);
      animation: pulse 2s infinite;
    }

    /* Locked State */
    .timeline-item.locked .node {
      border-color: #334155;
      background: #1e293b;
    }

    /* Card */
    .roadmap-card {
      flex: 1;

    }

    .roadmap-card:hover {
      transform: translateX(50px);
      border-color: rgba(255, 255, 255, 0.2);
      background: rgba(255, 255, 255, 0.08);
    }

    /* Card Content */
    .card-content h3 {
      margin: 0 0 5px 0;
      font-size: 18px;
    }

    .card-content p {
      margin: 0;
      font-size: 13px;
      color: var(--text-muted);
    }

    /* Action Button */
    .button-start {
      padding: 10px 20px;
      background: var(--violet);
      color: white;
      border-radius: 8px;
      font-weight: bold;
      cursor: pointer;
      font-size: 14px;
      cursor: pointer;
      font-size: 14px;
      transition: 0.2s;
      text-decoration: none;
      display: inline-block;
    }

    .button-start:hover {
      background: #7c3aed;
      box-shadow: 0 0 15px var(--violet-glow);
      transform: scale(1.05);
    }

    /* Completed Text */
    .status-text {
      font-weight: bold;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .status-text.done {
      color: var(--cyan);
    }

    .status-text.locked {
      color: #475569;
    }

    /* Modifiers for Locked Cards */
    .timeline-item.locked .roadmap-card {
      opacity: 0.5s;
      border-color: transparent;
      cursor: not-allowed;
    }

    .timeline-item.locked .roadmap-card:hover {
      transform: none;
    }

    /* Animation */
    @keyframes pulse {
      0% {
        box-shadow: 0 0 0 0 rgba(139, 92, 246, 0.7);
      }

      70% {
        box-shadow: 0 0 0 10px rgba(139, 92, 246, 0);
      }

      100% {
        box-shadow: 0 0 0 0 rgba(139, 92, 246, 0);
      }
    }

    /* --- 5. RESPONSIVE --- */
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


      .timeline-line {
        left: 9px;
      }

      .timeline-container {
        padding-left: 0;
      }

      .timeline-item {
        gap: 15px;
      }
    }
  </style>
</head>

<body>
  <!-- Sidebar -->
  <nav class="sidebar">
    <div class="logo-boxes">L</div>
    <!--1. Home (Active)-->
    <!-- The classes were once called button-one to five -->
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

    <!--2. Active RoadMap-->
    <button class="icon-button active" aria-label="Roadmap">
      <!-- < ?xml version="1.0" encoding="utf-8"?> -->
      <!-- License: PD. Made by mono-company: https://github.com/mono-company/mono-icons -->
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path
          d="M8.684 3.051a1 1 0 0 1 .632 0L15 4.946l4.367-1.456A2 2 0 0 1 22 5.387V17.28a2 2 0 0 1-1.367 1.898l-5.317 1.772a1 1 0 0 1-.632 0L9 19.054 4.632 20.51A2 2 0 0 1 2 18.613V6.72a2 2 0 0 1 1.368-1.898L8.684 3.05zM10 17.28l4 1.334V6.72l-4-1.334V17.28zM8 5.387L4 6.721v11.892l4-1.334V5.387zm8 1.334v11.892l4-1.334V5.387l-4 1.334z"
          fill="#0D0D0D" />
      </svg>
    </button>

    <!--3. Stats-->
    <button class="icon-button" onclick="window.location.href='stats.html'" aria-label="Stats">
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
  <!-- Main Content -->
  <main class="main-content">
    <header>
      <h1>Skill Tree</h1>
      <p class="subtitle">Complete nodes to unlock advanced modules.</p>
    </header>

    <div class="timeline-container">
      <div class="timeline-line"></div>

      <?php foreach ($roadmapItems as $item): ?>
        <?php
        $status = $item['status'];
        $class = $status; // 'completed', 'active', 'locked' 
      
        // // Add specific styling for active card border
        $cardStyle = ($status === 'active') ? 'style="border-color: var(--violet);"' : '';
        ?>

        <div class="timeline-item <?php echo $class; ?>">
          <div class="node"></div>
          <div class="roadmap-card" <?php echo $cardStyle; ?>>
            <div class="card-content">
              <h3><?php echo htmlspecialchars($item['title']); ?></h3>
              <p><?php echo htmlspecialchars($item['description']); ?></p>
            </div>
            <?php if ($status === 'active'): ?>
              <div class="status-text">
                <span>✓</span> Complete
              </div>
            <?php elseif ($status === 'active'): ?>
              <?php ?>
              <a href="lesson.html?type=<?php echo htmlspecialchars($item['id']); ?>" class="button-start">Continue
                &nbsp;</a>
            <?php else: //locked ?>
              <?php ?>
              <div class="status-text">
                <span>🔒</span> Locked
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
</body>

</html>