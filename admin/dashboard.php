<?php
// <-- /**
//  * LingoFlow v2 - Admin Dashboard
//  * Core Logic: PDO Connection, Session Management, and Stats Aggregation
//  */ -->

session_start();
// <!-- // 1. Session & Access Control
// // Check if user is logged in AND is an admin -->
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../login.php');
  exit();
}

// <!-- // 2. Database Connection -->
$host = 'localhost';
$db = 'lingoflow_db';
$user = 'root';
$pass = '';
$port = "3306";
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;port=$port;charset=$charset";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

$error_msg = null;

try {
  $pdo = new PDO($dsn, $user, $pass, $options);

  // <!-- // 3. Fetch Statistics
//     // Total Users -->
  $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
  $total_users = $stmt->fetch()['count'];

  // <!-- // Total Lessons -->
  $stmt = $pdo->query("SELECT COUNT(*) as count FROM lessons");
  $total_lessons = $stmt->fetch()['count'];

  // <!-- // Total Questions -->
  $stmt = $pdo->query("SELECT COUNT(*) as count FROM questions");
  $total_questions = $stmt->fetch()['count'];

  // <!-- // Average XP -->
  $stmt = $pdo->query("SELECT AVG(xp) as avg_xp FROM users");
  $avg_xp = round($stmt->fetch()['avg_xp'] ?? 0);

  // <!-- // 4. Fetch Recent Activity (Newest Users) -->
  $stmt = $pdo->query("SELECT username, email, rank, xp FROM users ORDER BY id DESC LIMIT 5");
  $recent_users = $stmt->fetchAll();

  // <!-- // 5. Fetch Content Summary (Recent Lessons) -->
  $stmt = $pdo->query("SELECT title, type, id FROM lessons ORDER BY id DESC LIMIT 5");
  $recent_lessons = $stmt->fetchAll();

} catch (PDOException $e) {
  $error_msg = "Database Connection Failed: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LingoFlow Admin | Terminal</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    :root {
      --neon-cyan: #00f3ff;
      --neon-purple: #bc13fe;
      --dark-bg: #0a0b10;
      --glass: rgba(255, 255, 255, 0.05);
    }

    body {
      background: var(--dark-bg);
      background-image: radial-gradient(circle at 50% 50%,
          #1a1b26 0%,
          #0a0b10 100%);
      color: white;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
    }

    .glass-panel {
      background: var(--glass);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 16px;
      box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
    }

    .neon-border-cyan {
      border: 1px solid var(--neon-cyan);
      box-shadow: 0 0 10px rgba(0, 243, 255, 0.2);
    }

    .neon-text-cyan {
      color: var(--neon-cyan);
      text-shadow: 0 0 8px rgba(0, 243, 255, 0.5);
    }

    .sidebar-link:hover {
      background: rgba(0, 243, 255, 0.1);
      border-right: 3px solid var(--neon-cyan);
    }
  </style>
</head>

<body class="flex">
  <!--SideBar-->
  <aside class="w-64 h-screen glass-panel m-4 flex flex-col">
    <div class="p-6">
      <h1 class="text-2xl font-bold neon-text-cyan tracking-tighter">
        LINGO<span class="text-white">FLOW</span>
      </h1>
      <p class="text-xs text-gray-400 mt-1">ADMIN CONSOLE v2.0</p>
    </div>

    <nav class="flex-1 px-4 space-y-2 mt-4">
      <a href="dashboard.html" class="sidebar-link flex item-center p-3 text-cyan-400 bg-cyan-400/10 rounded-lg">
        <i class="fas fa-chart-line mr-3"></i> Dashboard
      </a>
      <a href="users.html" class="sidebar-link flex item-center p-3 text-cyan-400 rounded-lg transition-all">
        <i class="fas fa-users mr-3"></i> Manage users
      </a>
      <a href="content.html" class="sidebar-link flex item-center p-3 text-gray-400 rounded-lg transition-all">
        <i class="fas fa-book mr-3"></i> Lesson Content
      </a>
      <a href="../logout.html" class="sidebar-link flex item-center p-3 text-red-400 rounded-lg transition-all">
        <i class="fas fa-sign-out-alt mr-3"></i> Terminate Session
      </a>
    </nav>

    <div class="p-4 glass-panel m-4 mt-auto">
      <div class="flex items-center space-y-1">
        <div class="w-8 h-8 rounded-full bg-cyan-500 flex item-center justify-center mr-3">
          <i class="fas fa-user-shield text-xs"></i>
        </div>
        <div>
          <p class="text-xs font-bold"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></p>
          <p class="text-[10px] text-cyan-400">system Administrator</p>
        </div>
      </div>
    </div>
  </aside>

  <!--Main Content-->
  <main class="flex-1 p-8 overflow-y-auto">
    <!--Error Placeholder-->
    <?php if ($error_msg): ?>
      <div class="bg-red-500/20 border-red-500 text-red-200 p-4 rounded-lg mb-6 flex item-center">
        <i class="fas fa-exclamation-triangle mr-3"></i>
        <?php echo $error_msg; ?>
      </div>
    <?php endif; ?>

    <div class="flex justify-between item-end mb-8">
      <div>
        <h2 class="text-3xl font-bold">System Overview</h2>
        <p class="text-gray-400">
          Real-time metrics from LingoFlow neural network.
        </p>
      </div>
      <div class="text-right">
        <p class="text-xs text-gray-500 uppercase">System Status</p>
        <p class="text-green-400 font-mono">
          <i class="fas fa-circle text-[8px] mr-2 animate-pulse"></i>OPERATIONAL
        </p>
      </div>
    </div>

    <!--Stats Grid-->
    <div class="grid grid-cols-1 md:gri-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <div class="glass-panel p-6 neon-border-cyan">
        <div class="flex justify-between item-start">
          <div>
            <p class="text-gray-400 text-sm">Total Operatives</p>
            <h3 class="text-3xl font-bold mt-1"><?php echo number_format($total_users); ?></h3>
          </div>
          <div class="p-3 bg-cyan-500/10 rounded-lg text-cyan-400">
            <i class="fas fa-users-cog"></i>
          </div>
        </div>
        <div class="mt-4 text-xs text-cyan-500">
          <i class="fas fa-arrow-up mr-1"></i> New registrations active
        </div>
      </div>

      <div class="glass-panel p-6">
        <div class="flex justify-between item-start">
          <div>
            <p class="text-gray-400 text-sm">Active Modules</p>
            <h3 class="text-3xl font-bold mt-1"><?php echo $total_lessons; ?></h3>
          </div>
          <div class="p-3 bg-purple-500/10 rounded-lg text-purple-400">
            <i class="fas fa-brain"></i>
          </div>
        </div>
        <div class="mt-3 text-xs text-purple-500">
          <i class="fas fa-sync mr-1"></i> Data synced across DB
        </div>
      </div>

      <div class="glass-panel p-6">
        <div class="flex justify-between item-start">
          <div>
            <p class="text-gray-400 text-sm">Neural XP Avg</p>
            <h3 class="text-3xl font-bold mt-1"><?php echo number_format($avg_xp); ?></h3>
          </div>
          <div class="p-3 bg-yellow-500/10 rounded-lg text-yellow-400">
            <i class="fas fa-bolt"></i>
          </div>
        </div>
        <div class="mt-4 text-xs text-yellow-500">
          <i class="fas fa-chart-bar mr-1"></i> Global user performance
        </div>
      </div>

      <div class="glass-panel p-6">
        <div class="flex justify-between item-start">
          <div>
            <p class="text-gray-400 text-sm">Data Nodes</p>
            <h3 class="text-3xl font-bold mt-1"><?php echo $total_questions; ?></h3>
          </div>
          <div class="p-3 bg-green-500/10 rounded-lg text-green-400">
            <i class="fas fa-database"></i>
          </div>
        </div>
        <div class="mt-4 text-xs text-green-500">
          <i class="fas fa-check mr-1"></i> Questions ready
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- Recent Users -->
      <div class="glass-panel p-6">
        <h3 class="text-xl font-bold mb-4 flex item-center">
          <i class="fas fa-user-plus mr-3 text-cyan-400"></i> New Operatives
        </h3>
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr class="text-gray-500 border-b border-white/10">
                <th class="pb-3 font-medium">User</th>
                <th class="pb-3 font-medium text-center">Rank</th>
                <th class="pb-3 font-medium text-right">XP</th>
              </tr>
            </thead>
            <tbody class="divide-y divine-white/5">
              <?php foreach ($recent_users as $row): ?>
                <tr>
                  <td class="py-4">
                    <p class="font-bold text-sm"><?php echo htmlspecialchars($row['username']); ?></p>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($row['email']); ?></p>
                  </td>
                  <td class="px-4 text-center">
                    <span
                      class="px-2 py-1 bg-cyan-500/10 text-cyan-400 text-[10px] round border border-cyan-500/30 uppercase"><?php echo htmlspecialchars($row['rank']); ?></span>
                  </td>
                  <td class="py-4 text-center font-mono text-sm text-yellow-500">
                    >><?php echo number_format($row['xp']); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <a href="users.html"
          class="block w-full text-center mt-4 p-2 text-xs text-gray-400 hover:text-cyan-400 transition-colors uppercase tracking-widest">View
          All Users</a>
      </div>

      <!-- Recent Lesson -->
      <div class="glass-panel p-6">
        <h3 class="text-xl font-bold mb-4 flex items-center">
          <i class="fas fa-folder-open mr-3 text-purple-400"></i> Content
          Inventory
        </h3>
        <div class="space-y-4">
          <?php foreach ($recent_lessons as $lesson): ?>
            <div>
              <div
                class="flex item-center justify-between p-3 bg-white/5 rounded-lg border border-white/5 hover:border-purple-500/50 transition-all group">
                <div class="w-10 h-10 rounded bg-purple-500/20 flex item-center justify-center mr-4 text-purple-400">
                  <i class="fas <?php echo ($lesson['type'] == 'quiz' ? 'fa-vial' : 'fa-book-open'); ?>"></i>
                </div>
                <div>
                  <p class="font-bold text-sm"><?php echo htmlspecialchars($lesson['title']); ?></p>
                  <p class="text-[10px] text-gray-500 uppercase tracking-tighter">
                    ID: #00<?php echo $lesson['id']; ?> | Type <?php echo htmlspecialchars($lesson['type']); ?>
                  </p>
                </div>
              </div>

              <a href="content.html?edit=<?php echo $lesson['id']; ?>"
                class="opacity-0 group-hover:opacity-100 p-2 text-gray-400 hover:text-while transition-all">
                <i class="fas fa-edit"></i>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
        <a href="content.html"
          class="block w-full text-center mt-6 p-2 text-xs text-gray-400 hover:text-purple-400 transition-coloers uppercase tracking-widest">
          Manage All Content
        </a>
      </div>
    </div>
  </main>
</body>

</html>