<?php
/**
 * LingoFlow v2 - User Management Admin Terminal
 * Logic: Search, Stats, and Account Status Management
 */

session_start();

// 1. Session & Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../login.php');
  exit();
}

// 2. Database Connection
$host = 'localhost';
$db = 'lingoflow_db';
$user = 'root';
$pass = '';
$port = '3306';

$error_msg = null;

try {
  $dsn = "mysql:host=$host;dbname=$db;port=$port; charset=utf8mb4";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);

  // 3. Handle Actions (Ban/Unban)
  if (isset($_POST['action']) && isset($_POST['target_id'])) {
    $target_id = (int) $_POST['target_id'];
    $new_status = ($_POST['action'] === 'ban') ? 'banned' : 'active';

    // Prevent admin from banning themselves
    if ($target_id !== $_SESSION['user_id']) {
      $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
      $stmt->execute([$new_status, $target_id]);
    }
  }

  // 4. Fetch Stats for the Mini-Strip
  $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
  $banned_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'banned'")->fetchColumn();
  // Simulate "Online Now" based on activity in the last 15 mins (requires last_login column)
  // For this prototype, we'll use a count of users with high XP as a proxy or a static/random mock if column missing
  $online_now = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active' LIMIT 1")->fetchColumn();

  // 5. Build Search Query
  $search = $_GET['q'] ?? '';
  $query_str = "SELECT * FROM users";
  $params = [];

  if (!empty($search)) {
    $query_str .= " WHERE username LIKE ? OR email LIKE ?";
    $params = ["%$search%", "%$search%"];
  }
  $query_str .= " ORDER BY id DESC";

  $stmt = $pdo->prepare($query_str);
  $stmt->execute($params);
  $users_list = $stmt->fetchAll();
} catch (PDOException $e) {
  $error_msg = "Critical Database Error: " . $e->getMessage();
}

/**
 * Helper to generate consistent colors for avatars
 */

function getAvatarColor($char)
{
  $colors = ['#8b5cf6', '#06b6d4', '#22c55e', '#ef4444', '#fb923c', '#ec4899'];
  $index = ord(strtolower($char)) % count($colors);
  return $colors[$index];
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LingoFlow v2 - User Management</title>
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
      background-image: radial-gradient(circle at 50% 0%, rgba(139, 92, 246, 0.15) 0%, transparent 50%);
      height: 100vh;
      overflow: hidden;
      display: grid;
      grid-template-columns: 80px 1fr;
    }

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
    }

    .icon-button {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      border: none;
      background: transparent;
      color: #64748b;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .icon-button:hover {
      background: rgba(255, 255, 255, 0.1);
      color: white;
    }

    .icon-button.active {
      background: var(--violet);
      color: white;
      box-shadow: 0 0 15px var(--violet-glow);
    }

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
    }

    .subtitle {
      color: var(--text-muted);
      font-size: 14px;
    }

    .action-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }

    .search-wrapper {
      position: relative;
      width: 350px;
    }

    .search-input {
      width: 100%;
      background: var(--glass);
      border: 1px solid var(--border);
      padding: 12px 15px 12px 40px;
      border-radius: 12px;
      color: white;
      outline: none;
    }

    .search-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
    }

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

    th {
      text-align: left;
      padding: 20px;
      font-size: 13px;
      color: var(--text-muted);
      text-transform: uppercase;
      border-bottom: 1px solid var(--border);
    }

    td {
      padding: 20px;
      font-size: 14px;
      border-bottom: 1px solid var(--border);
    }

    tr:hover {
      background: var(--glass-hover);
    }

    .user-cell {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .user-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: white;
    }

    .user-name {
      font-weight: bold;
      display: block;
    }

    .user-email {
      font-size: 12px;
      color: var(--text-muted);
    }

    .status-badge {
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: bold;
      text-transform: uppercase;
    }

    .status-active {
      background: rgba(34, 197, 94, 0.1);
      color: var(--green);
      border: 1px solid rgba(34, 197, 94, 0.2);
    }


    .status-banned {
      background: rgba(239, 68, 68, 0.1);
      color: var(--red);
      border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .action-row {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
    }

    .action-button {
      background: none;
      border: none;
      cursor: pointer;
      padding: 0;
    }

    .action-icon {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      align-items: center;
      justify-content: center;
    }

    .action-ban {
      background: rgba(239, 68, 68, 0.1);
      color: var(--green);
    }

    .action-unban {
      background: rgba(34, 197, 94, 0.1);
      color: var(--green);
    }

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

    .echo-strip {
      font-weight: bold;
      font-size: 18px;
    }

    .text-strip {
      font-size: 12px;
      color: var(--text-muted);
    }
  </style>
</head>

<body>
  <nav class="sidebar">
    <div class="logo-boxes">A</div>
    <button class="icon-button" onclick="window.location.href = 'dashboard.php'">
      <!-- License: MIT. Made by framework7io: https://github.com/framework7io/framework7-icons -->
      <img src="grid-icon.svg" alt="Grid Layout Icon" width="24" height="24">
    </button>
    <button class="icon-button active">
      <!-- License: MIT. Made by Primer: https://github.com/primer/octicons -->
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
    <button class="icon-button" onclick="window.location.href = 'content.php'">
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
    <button class="icon-button" onclick="window.location.href = '../login.php'">
      <!-- License: PD. Made by CoreyGinnivan: https://github.com/CoreyGinnivan/system-uicons/ -->
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

  <main class="main-content">
    <?php if ($error_msg): ?>
      <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid var(--red); padding: 15px; border-radius: 10px;">
        <?php echo $error_msg; ?>
      </div>
    <?php endif; ?>

    <header>
      <h1>User Database</h1>
      <p class="subtitle">Monitor and manage learner accounts.</p>
    </header>

    <div class="stats-strip">
      <div class="mini-stat">
        <div class="mini-icon">👥</div>
        <div>
          <div class="echo-strip"><?php echo number_format($total_users); ?></div>
          <div class="text-strip">Total Users</div>
        </div>
      </div>
      <div class="mini-stat">
        <div class="mini-icon" style="color: var(--green);">✨</div>
        <div>
          <div class="echo-strip"><?php echo $online_now; ?></div>
          <div class="text-strip">Active Hubs</div>
        </div>
      </div>
      <div class="mini-stat">
        <div class="mini-icon" style="color: var(--cyan);">📈</div>
        <div>
          <div>+15%</div>
          <div>New Growth</div>
        </div>
      </div>
      <div class="mini-stat">
        <div class="mini-icon" style="color: var(--red);">⛔</div>
        <div>
          <div class="echo-strip"><?php echo $banned_users; ?></div>
          <div class="text-strip">Restricted</div>
        </div>
      </div>
    </div>

    <div class="action-bar">
      <form class="search-wrapper" method="GET">
        <span class="search-icon">🔍</span>
        <input type="text" name="q" class="search-input" placeholder="Search by username or email..."
          value="<?php echo htmlspecialchars($search); ?>" />
      </form>
    </div>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>User</th>
            <th>Status</th>
            <th>Rank</th>
            <th>Streak</th>
            <th>XP</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users_list as $user_row): ?>
            <tr>
              <td>
                <div class="user-cell">
                  <div class="user-avatar" style=" <?php echo getAvatarColor($user_row['username'][0]); ?>">
                    <?php echo strtoupper($user_row['username'][0]); ?>
                  </div>
                  <div>
                    <span class="user-name"><?php echo htmlspecialchars($user_row['username']); ?></span>
                    <span class="user-email"><?php echo htmlspecialchars($user_row['email']); ?></span>
                  </div>
                </div>
              </td>
              <td>
                <span
                  class="status-badge <?php echo ($user_row['status'] === 'banned') ? 'status-banned' : 'status-active'; ?>">
                  <?php echo $user_row['status'] ?? 'active'; ?> </span>
              </td>
              <td style="color: var(--cyan);"><?php echo htmlspecialchars($user_row['rank'] ?? 'Unranked'); ?></td>
              <td style="color: var(--orange);">
                <?php echo $user_row['streak']; ?>
                🔥
              </td>
              <td style="color: var(--text-muted);"><?php echo number_format($user_row['xp']); ?></td>
              <td>
                <div class="action-row">
                  <form method="POST" onsubmit="
                      return confirm('Change status for this operative?');
                    ">
                    <input type="hidden" name="target_id" value="<?php echo $user_row['id']; ?>" />
                    <?php if (($user_row['status'] ?? 'active') === 'banned')
                      : ?>
                    <button type="submit" name="action" value="unban" class="action-button" title="Unban Operative">
                      <div class="action-icon action-unban">🔓</div>
                    </button>
                    <?php else: ?>
                    <button type="submit" name="action" value="ban" class="action-button" title="Ban Operative" <?php echo ($user_row['id'] == $_SESSION['user_id']) ? 'disabled style="opacity:0.3"' : ''; ?>>
                      <div class="action-icon action-ban">⛔</div>
                    </button>
                    <?php endif; ?>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>

</html>