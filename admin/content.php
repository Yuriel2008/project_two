<?php
// --- 1. SESSION & INIT ---
session_start();

// Basic Auth Check (In a real app, check for 'is_admin' role)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  // For prototype, we assume if logged in, you can access. 
  // In production, redirect to admin/login.php
  header('Location: ../login.php');
  exit;
}

// --- 2. DATABASE CONNECTION ---
$dbHost = 'localhost';
$dbName = 'lingoflow_db';
$dbUser = 'root';
$dbPass = ''; // Update with your password

try {
  $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // --- 3. HANDLE ACTIONS (POST) ---

  // DELETE LESSON
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $idToDelete = $_POST['id'];
    $deleteStmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
    $deleteStmt->execute([$idToDelete]);
    header("Location: content.php");// Refresh to clear post data
    exit;
  }

  // ADD LESSON
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $newId = $_POST['lesson_id'];
    $newTitle = $_POST['title'];
    $newCat = $_POST['category'];
    $newXp = $_POST['xp'];
    $newOrder = $_POST['sort_order'];

    $addStmt = $pdo->prepare("INSERT INTO lessons (id, title, category, description, xp_value, sort_order) VALUES (?, ?, ?, 'New content', ?, ?)");
    $addStmt->execute([
      $newId,
      $newTitle,
      $newCat,
      $newXp,
      $newOrder
    ]);
    header("Location: content.php");// Refresh to clear post data
    exit;
  }


  // --- 4. FETCH DATA ---

  // Stats
  $statsStmt = $pdo->query("SELECT COUNT(*) as count, SUM(xp_value) as total_xp FROM lessons");
  $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
  $totalLessons = $stats['count'];
  $totalXP = $stats['total_xp'];

  // Search Filter
  $search = $_GET['q'] ?? '';
  $sql = "SELECT * FROM lessons";
  $params = [];
  if ($search) {
    $sql .= " WHERE title LIKE ? OR category LIKE ?";
    $params = ["%$search%", "%$search%"];
  }
  $sql .= " ORDER BY sort_order ASC";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
  die("Database connection failed: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LingoFlow v2 - Content Management</title>
  <link rel="stylesheet" href="" />
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
      --orange: #fb923c;
      --glass: rgba(255, 255, 255, 0.05);
      --glass-hover: rgba(255, 255, 255, 0.08);
      --border: rgba(255, 255, 255, 0.1);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
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
      /* 900px for below experiment layer */
      grid-template-columns: 80px 1fr;
    }

    /* --- 2. SIDEBAR NAVIGATION (Admin Version) --- */
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
      width: 49px;
      height: 48px;
      border-radius: 12px;
      border: none;
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
      align-items: flex-end;
    }

    h1 {
      margin: 0;
      font-size: 28px;
      letter-spacing: -0.5px;
    }

    .subtitle {
      color: var(--text-muted);
      margin: 5px 0 0 0;
      font-size: 14px;
    }

    /* --- 4. ACTION BAR --- */
    .action-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }

    .search-wrapper {
      position: relative;
      width: 300px;
    }

    .search-input {
      width: 100%;
      background: var(--glass);
      border: 1px solid var(--border);
      padding: 12px 15px 12px 40px;
      border-radius: 12px;
      color: white;
      font-family: inherit;
      outline: none;
      transition: 0.2s;
    }

    .search-input:focus {
      border-color: var(--violet);
      box-shadow: 0 0 10px rgba(139, 92, 246, 0.2);
    }

    .search-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      pointer-events: none;
    }

    .button-add {
      background: var(--violet);
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 10px;
      font-weight: bold;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: 0.2s;
      box-shadow: 0 4px 15px var(--violet-glow);
    }

    .button-add:hover {
      background: #7c3aed;
      transform: scale(1.02);
    }

    /* --- 5. DATA TABLE --- */
    .table-container {
      background: var(--glass);
      backdrop-filter: blur(10px);
      border: 1px solid var(--border);
      border-radius: 16px;
      overflow: hidden;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead {
      background: rgba(255, 255, 255, 0.02);
      border-bottom: 1px solid var(--border);
    }

    th {
      text-align: left;
      padding: 20px;
      font-size: 13px;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 1px;
      font-weight: 600;
    }

    tr {
      border-bottom: 1px solid var(--border);
      transition: 0.2s;
    }

    tr:last-child {
      border-bottom: none;
    }

    tr:hover {
      background: var(--glass-hover);
    }

    td {
      padding: 20px;
      font-size: 14px;
      vertical-align: middle;
    }

    /* Status Badges */
    .status-badge {
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: bold;
      display: inline-block;
    }

    .status-published {
      background: rgba(34, 197, 94, 0.15);
      color: var(--green);
      border: 1px solid rgba(34, 197, 94, 0.3);
    }

    .status-draft {
      background: rgba(251, 146, 60, 0.15);
      color: var(--orange);
      border: 1px solid rgba(251, 146, 60, 0.3);
    }

    .status-locked {
      background: rgba(148, 163, 184, 0.15);
      color: var(--text-muted);
      border: 1px solid rgba(148, 163, 184, 0.3);
    }

    /* Action Icons */
    .action-row {
      display: flex;
      gap: 10px;
    }

    .action-icon {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: 0.2s;
      border: 1px solid transparent;
    }

    .action-edit {
      background: rgba(6, 182, 212, 0.1);
      color: var(--cyan);
    }

    .action-edit:hover {
      background: rgba(6, 182, 212, 0.2);
      border-color: var(--cyan);
    }

    .action-delete {
      background: rgba(239, 68, 68, 0.2);
      border-color: 20px;
    }

    /* --- 6. STATS PREVIEW --- */
    .stats-strip {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
    }

    .mini-stat {
      background: var(--glass);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 15px 20px;
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .mini-icon {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      background: rgba(255, 255, 255, 0.03);
    }

    /* Modal (Quick & Dirty for Prototype) */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      ;
      background: rgba(0, 0, 0, 0.8);
      z-index: 200;
      align-items: center;
      justify-content: center;
    }

    .modal-content {
      background: var(--bg-deep);
      border: 1px solid var(--violet);
      padding: 30px;
      ;
      border-radius: 20px;
      width: 400px;
    }

    .modal input {
      display: block;
      width: 100%;
      margin: 10px 0;
      padding: 10px;
      background: var(--glass);
      border: 1px solid var(--border);
      color: white;
    }

    /* Responsive */
    @media (max-width:768px) {
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

      .stats-strip {
        grid-template-columns: 1fr 1fr;
      }

      .action-bar {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
      }

      .search-wrapper {
        width: 100%;
      }

      td:nth-child(3),
      th:nth-child(3) {
        display: none;
      }

      /* Hide Category on mobile */

    }

    .stats-strip-coloring {
      font-weight: bold;
      font-size: 18px;
    }

    .stats-strip-coloring-two {
      font-size: 12px;
      color: var(--text-muted);
    }
  </style>
</head>

<body>
  <!--SideBar (Admin)-->
  <nav class="sidebar">
    <div class="logo-boxes">A</div>

    <!-- Dashboard -->
    <button class="icon-button" onclick="window.location.href='dashboard.php'" aria-label="Dashboard">
      <img src="grid-icon.svg" alt="Grid Layout Icon" width="24" height="24">
    </button>

    <!--Users-->
    <button class="icon-button" onclick="window.location.href='users.php'" aria-label="Users">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <!-- Front Person -->
        <circle cx="9" cy="7" r="4" />
        <path d="M2 21a7 7 0 0 1 14 0" />

        <!-- Back Person -->
        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        <path d="M22 21a7 7 0 0 0-7-7" />
      </svg>
    </button>

    <!-- Content (Active) -->
    <button class="icon-button active" aria-label="Content">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#231f20"
        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <!-- The Paper Document Outline -->
        <path d="M4 2h11l5 5v15H4Z" fill="#ffffff" />

        <!-- The Folded Paper Corner -->
        <path d="M15 2v5h5" fill="#d1d3d4" />

        <!-- The Two Text Lines Inside -->
        <line x1="8" y1="13" x2="13" y2="13" />
        <line x1="8" y1="17" x2="16" y2="17" />
      </svg>
    </button>

    <!--Logout-->
    <button class="icon-button" onclick="window.location.href='../login.php'"
      style="margin-top: auto; color: var(--red);" aria-label="Logout">
      <svg width="24" height="24" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg">
        <g fill="none" fill-rule="evenodd" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
          transform="translate(4 3)">
          <path d="m10.595 10.5 2.905-3-2.905-3" />
          <path d="m13.5 7.5h-9" />
          <path
            d="m10.5.5-8 .00224609c-1.1043501.00087167-1.9994384.89621131-2 2.00056153v9.99438478c0 1.1045695.8954305 2 2 2h8.0954792" />
        </g>
      </svg>
    </button>
  </nav>

  <!-- Main Content -->
  <main class="main-content">
    <header>
      <div>
        <h1>Content Management</h1>
        <p class="subtitle">
          Manage lessons, quizzes, and learning modules.
        </p>
      </div>
    </header>

    <!-- Stats Strip -->
    <div class="stat-strip">
      <div class="mini-stat">
        <div class="mini-icon" style="color: var(--cyan);">📚</div>
        <div>
          <div class="stats-strip-coloring"><?php echo $totalLessons; ?></div>
          <div class="stats-strip-coloring-two">Total Lessons</div>
        </div>
      </div>
      <div class="mini-stat">
        <div class="mini-icon" style="color: var(--green);">✅</div>
        <div>
          <div class="stats-strip-coloring"><?php echo $totalLessons; ?></div>
          <div class="stats-strip-coloring-two">Published</div>
        </div>
      </div>
      <div class="mini-stat">
        <div class="mini-icon" style="color: var(--orange);">📝</div>
        <div>
          <div class="stats-strip-coloring">0</div>
          <div class="stats-strip-coloring-two">Drafts</div>
        </div>
      </div>
      <div class="mini-stat">
        <div class="mini-icon" style="color: var(--violet);">⚡</div>
        <div>
          <div class="stats-strip-coloring"><?php echo number_format($totalXP); ?></div>
          <div class="stats-strip-coloring-two">Total XP Value</div>
        </div>
      </div>
    </div>
    </div>

    <!-- Action Bar -->
    <div class="action-bar">
      <form class="search-wrapper" method="GET">
        <span class="search-icon">🔍</span>
        <input type="text" name="q" class="search-input" placeholder="Search-modules..."
          value="<?php echo htmlspecialchars($search); ?>" onchange="this.form.submit()">
      </form>
      <button class="button-add" onclick="openAddModal()">
        <span>+</span> New Lesson
      </button>
    </div>

    <!-- Content Table -->
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Category</th>
            <th>XP</th>
            <th>Status</th>
            <th>Last Update</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lessons as $lesson): ?>
            <tr>
              <td style="color: var(--text-muted);"><?php echo htmlspecialchars($lesson['id']); ?></td>
              <td style="font-weight: bold; color: white;"><?php echo htmlspecialchars($lesson['title']); ?></td>
              <td><?php echo htmlspecialchars($lesson['category']); ?></td>
              <td><span style="color: var(--violet);"><?php echo $lesson['xp_value']; ?> XP</span></td>
              <td><span class="status-badge status-published">Published</span></td>
              <td style="color: var(--text-muted);"><?php echo date("Y-m-d"); ?></td>
              <td>
                <div class="action-row" style="justify-content: flex-end;">
                  <!-- Delete Action -->
                  <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete this lesson?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($lesson['id']); ?>">
                    <button class="action-icon action-delete" title="Delete" style="border: none;">🗑</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <!-- Simple Add Modal -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <h2>New Lesson</h2>
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <input type="text" name="lesson_id" placeholder="ID (e.g. basics2)" required>
        <input type="text" name="title" placeholder="Lesson title" required>
        <input type="text" name="category" placeholder="Category" required>
        <input type="number" name="xp" placeholder="XP Value" value="50">
        <input type="number" name="sort_order" placeholder="Sort Order" value="99">
        <div style="display: flex; justify-content: space-between; margin-top: 15px;">
          <button type="button" onclick="document.getElementById('addModal').style.display='none'"
            style="background: transparent; color: white; border: 1px solid var(--border); padding: 8px 16px; border-radius: 8px; cursor: pointer;">Cancel</button>
          <button type="submit"
            style="background: var(--violet); color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer;">Create</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    function openAddModal() {
      document.getElementById('addModal').style.display = 'flex';
    }
  </script>
</body>

</html>