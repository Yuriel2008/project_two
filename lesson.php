<?php
// --- 1. SESSION & INIT ---
ob_start();
session_start();

// PREVENT BROWSER CACHING (Fixes "Same Position" on reload)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$lesson_id = $_GET['type'] ?? 'basics';

// --- 2. DATABASE CONNECTION ---
$dbHost = 'localhost';
$dbName = 'lingoflow_db';
$dbUser = 'root';
$dbPass = ''; // Update with your actual password

try {
  $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // --- 3. FETCH LESSON METADATA ---
  $stmt = $pdo->prepare("SELECT * FROM lessons WHERE id = ?");
  $stmt->execute([$lesson_id]);
  $lessonMeta = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$lessonMeta) {
    header("Location: index.php");
    exit();
  }

  // --- 4. HANDLE LESSON COMPLETION ---
  if (isset($_GET['action']) && $_GET['action'] === 'complete') {
    $pdo->beginTransaction();

    try {

      // A. Award XP
      $xp = $lessonMeta['xp_value'];
      $updateUser = $pdo->prepare("UPDATE users SET xp = xp + ?, last_login = NOW() WHERE id = ?");
      $updateUser->execute([$xp, $user_id]);

      // B. Mark Current Lesson as Completed
      $upSql = "INSERT INTO user_progress (user_id, lesson_id, status, last_attempt)
       VALUES (?, ?, 'completed',NOW())
        ON DUPLICATE KEY UPDATE status =  'completed', last_attempt = NOW()";
      $pdo->prepare($upSql)->execute([$user_id, $lesson_id]);

      // C. Unlock Next Lesson
      $currentOrder = $lessonMeta['sort_order'];
      $nextLessonStmt = $pdo->prepare("SELECT id FROM lessons WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1");
      $nextLessonStmt->execute([$currentOrder]);
      $nextLessonId = $nextLessonStmt->fetch(PDO::FETCH_COLUMN);

      if ($nextLessonId) {
        $unlockSql = "INSERT INTO user_progress (user_id, lesson_id, status) 
        VALUES (?, ?, 'active') 
      ON DUPLICATE KEY UPDATE status = IF(status = 'locked', 'active', status)";
        $pdo->prepare($unlockSql)->execute([$user_id, $nextLessonId]);
      }

      $pdo->commit();


      // D. REDIRECT
      ob_end_clean(); // Clear output buffer to prevent header issues
      if (!headers_sent()) {
        header("Location: index.php");
        exit;
      } else {
        echo '<script type="text/javascript">window.location.href="index.php";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=index.php"></noscript>';
        exit;
      }


    } catch (Exception $e) {
      $pdo->rollBack();
      die("Critical System Error: " . $e->getMessage());
    }


  }

  // --- 5. DEFINE QUESTIONS (EXTENSIVE FUN DATA) ---
  $allQuestions = [
    'basics' => [
      ['q' => "How do you say 'I' or 'Me'?", 'answers' => [['text' => "Tôi", 'correct' => true], ['text' => "Bạn", 'corect' => false], ['text' => "Họ", 'correct' => false], ['text' => "Nó", 'correct' => false]]],
      ['q' => "Translate 'Hello' to Vietnamese", 'answers' => [['text' => "Xin chào", 'correct' => true], ['text' => "Tạm biệt", 'correct' => false], ['text' => "Cảm ơn", 'correct' => false], ['text' => "Xin lỗi", 'correct' => false]]],
      ['q' => "How do you refer to 'You' (a friend)?", 'answers' => [['text' => "Bạn", 'correct' => true], ['text' => "Tôi", 'correct' => false], ['text' => "Chúng ta", 'correct' => false], ['text' => "Anh ấy", 'correct' => false]]],
      ['q' => "Translate 'We' (exclusive)", 'answers' => [['text' => "Chúng tôi", 'correct' => true], ['text' => "Họ", 'correct' => false], ['text' => "Các bạn", 'correct' => false], ['text' => "Mọi người", 'correct' => false]]],
      ['q' => "How do you say 'Goodbye'?", 'answers' => [['text' => "Tạm biệt", 'correct' => true], ['text' => "Xin chào", 'correct' => false], ['text' => "Hẹn gặp lại", 'correct' => false], ['text' => "Vâng", 'correct' => false]]]
    ],

    'greetings' => [
      ['q' => "Translate 'Nice to meet you!'", 'answers' => [['text' => "Rất vui được gặp bạn", 'correct' => true], ['text' => "Cảm ơn bạn nhiều", 'correct' => false], ['text' => "Tạm biệt bạn", 'correct' => false], ['text' => "Xin lỗi bạn", 'correct' => false]]],
      ['q' => "How do you ask 'What is your name?'", 'answers' => [['text' => "Bạn tên là gì?", 'correct' => true], ['text' => "Bạn khỏe không?", 'correct' => false], ['text' => "Bạn đến từ đâu?", 'correct' => false], ['text' => "Bạn bao nhiêu tuổi?", 'correct' => false]]],
      ['q' => "Translate 'My name is...'", 'answers' => [['text' => "Tôi tên là...", 'correct' => true], ['text' => "Tôi là sinh viên...", 'correct' => false], ['text' => "Tôi đến từ...", 'correct' => false], ['text' => "Tôi thích...", 'correct' => false]]],
      ['q' => "How to say 'See you again'?", 'answers' => [['text' => "Hẹn gặp lại", 'correct' => true], ['text' => "Xin chào", 'correct' => false], ['text' => "Tạm biệt", 'correct' => false], ['text' => "Không có chi", 'correct' => false]]],
      ['q' => "Translate 'Good morning'", 'answers' => [['text' => "Chào buổi sáng", 'correct' => true], ['text' => "Chào buổi tối", 'correct' => false], ['text' => "Chào buổi chiều", 'correct' => false], ['text' => "Chúc ngủ ngon", 'correct' => false]]]
    ],
    'respect' => [
      ['q' => "How to say 'Hello' respectfully to an older person?", 'answers' => [['text' => "Cháu chào bác", 'correct' => true], ['text' => "Ê bạn", 'correct' => false], ['text' => "Chào nhé", 'correct' => false], ['text' => "Hi", 'correct' => false]]],
      ['q' => "Translate 'Thank you' (Politely)", 'answers' => [['text' => "Cảm ơn ạ", 'correct' => true], ['text' => "Cảm ơn", 'correct' => false], ['text' => "Không có chi", 'correct' => false], ['text' => "Được rồi", 'correct' => false]]],
      ['q' => "How to say 'Excuse me' or 'Sorry' (Politely)?", 'answers' => [['text' => "Xin lỗi ạ", 'correct' => true], ['text' => "Sai rồi", 'correct' => false], ['text' => "Không sao", 'correct' => false], ['text' => "Đi đi", 'correct' => false]]],
      ['q' => "How to answer 'Yes' respectfully?", 'answers' => [['text' => "Dạ / Vâng", 'correct' => true], ['text' => "Ừ", 'correct' => false], ['text' => "Đúng", 'correct' => false], ['text' => "Ok", 'correct' => false]]],
      ['q' => "How to politely decline ('No, thank you')?", 'answers' => [['text' => "Dạ không, cảm ơn", 'correct' => true], ['text' => "Không thích", 'correct' => false], ['text' => "Chẳng cần", 'correct' => false], ['text' => "Chưa", 'correct' => false]]]
    ],

    'numbers1' => [
      ['q' => "What is the number '1'?", 'answers' => [['text' => "Một", 'correct' => true], ['text' => "Hai", 'correct' => false], ['text' => "Ba", 'correct' => false], ['text' => "Bốn", 'correct' => false]]],
      ['q' => "What is the number '10'?", 'answers' => [['text' => "Mười", 'correct' => true], ['text' => "Chín", 'correct' => false], ['text' => "Một", 'correct' => false], ['text' => "Không", 'correct' => false]]],
      ['q' => "Translate 'Twenty' (20)", 'answers' => [['text' => "Hai mươi", 'correct' => true], ['text' => "Mười hai", 'correct' => false], ['text' => "Ba mươi", 'correct' => false], ['text' => "Hai", 'correct' => false]]],
      ['q' => "What is the number '5'?", 'answers' => [['text' => "Năm", 'correct' => true], ['text' => "Sáu", 'correct' => false], ['text' => "Bảy", 'correct' => false], ['text' => "Tám", 'correct' => false]]],
      ['q' => "Translate 'Mười lăm' into digits", 'answers' => [['text' => "15", 'correct' => true], ['text' => "10", 'correct' => false], ['text' => "5", 'correct' => false], ['text' => "50", 'correct' => false]]]
    ],

    'food' => [
      ['q' => "How do you say 'Rice'?", 'answers' => [['text' => "Cơm", 'correct' => true], ['text' => "Phở", 'correct' => false], ['text' => "Bánh mì", 'correct' => false], ['text' => "Nước", 'correct' => false]]],
      ['q' => "Translate 'Water' to quench your thirst!", 'answers' => [['text' => "Nước", 'correct' => true], ['text' => "Bia", 'correct' => false], ['text' => "Trà", 'correct' => false], ['text' => "Cà phê", 'correct' => false]]],
      ['q' => "How do you say 'I am hungry'?", 'answers' => [['text' => "Tôi đói", 'correct' => true], ['text' => "Tôi khát", 'correct' => false], ['text' => "Tôi no", 'correct' => false], ['text' => "Tôi mệt", 'correct' => false]]],
      ['q' => "What is 'Bánh mì'?", 'answers' => [['text' => "Vietnamese Baguette", 'correct' => true], ['text' => "Noodle soup", 'correct' => false], ['text' => "Fried Rice", 'correct' => false], ['text' => "Sweet Cake", 'correct' => false]]],
      ['q' => "Translate 'Delicious!'", 'answers' => [['text' => "Ngon quá", 'correct' => true], ['text' => "Dở quá", 'correct' => false], ['text' => "Cay quá", 'correct' => false], ['text' => "Mặn quá", 'correct' => false]]]
    ],

    'travel' => [
      ['q' => "How do you say 'Airport'?", 'answers' => [['text' => "Sân bay", 'correct' => true], ['text' => "Bến xe", 'correct' => false], ['text' => "Ga tàu", 'correct' => false], ['text' => "Khách sạn", 'correct' => false]]],
      ['q' => "Crucial question: 'Where is the toilet?'", 'answers' => [['text' => "Nhà vệ sinh ở đâu?", 'correct' => true], ['text' => "Bao nhiêu tiền?", 'correct' => false], ['text' => "Đi đường nào?", 'correct' => false], ['text' => "Mấy giờ rồi?", 'correct' => false]]],
      ['q' => "Translate 'Hotel'", 'answers' => [['text' => "Khách sạn", 'correct' => true], ['text' => "Nhà hàng", 'correct' => false], ['text' => "Chợ", 'correct' => false], ['text' => "Bệnh viện", 'correct' => false]]],
      ['q' => "Taxi direction: 'Turn left'", 'answers' => [['text' => "Rẽ trái", 'correct' => true], ['text' => "Rẽ phải", 'correct' => false], ['text' => "Đi thẳng", 'correct' => false], ['text' => "Quay lại", 'correct' => false]]],
      ['q' => "Shopping phrase: 'How much is this?'", 'answers' => [['text' => "Cái này bao nhiêu tiền?", 'correct' => true], ['text' => "Nó ở đâu?", 'correct' => false], ['text' => "Tôi muốn mua", 'correct' => false], ['text' => "Rẻ quá", 'correct' => false]]]
    ],

    'emotions' => [
      ['q' => "How do you say 'Very Happy'?", 'answers' => [['text' => "Rất vui", 'correct' => true], ['text' => "Buồn", 'correct' => false], ['text' => "Tức giận", 'correct' => false], ['text' => "Mệt", 'correct' => false]]],
      ['q' => "Translate 'I am sad' 😢", 'answers' => [['text' => "Tôi buồn", 'correct' => true], ['text' => "Tôi vui", 'correct' => false], ['text' => "Tôi mệt", 'correct' => false], ['text' => "Tôi đói", 'correct' => false]]],
      ['q' => "Translate 'Angry' 😡", 'answers' => [['text' => "Tức giận", 'correct' => true], ['text' => "Sợ hãi", 'correct' => false], ['text' => "Bất ngờ", 'correct' => false], ['text' => "Vui vẻ", 'correct' => false]]],
      ['q' => "Translate 'Tired' 😴", 'answers' => [['text' => "Mệt", 'correct' => true], ['text' => "Khỏe", 'correct' => false], ['text' => "Yếu", 'correct' => false], ['text' => "Chán", 'correct' => false]]],
      ['q' => "How to say 'I love you'?", 'answers' => [['text' => "Tôi yêu bạn", 'correct' => true], ['text' => "Tôi ghét bạn", 'correct' => false], ['text' => "Tôi thích bạn", 'correct' => false], ['text' => "Tôi cần bạn", 'correct' => false]]]
    ]
  ];

  $currentQuestion = $allQuestions[$lesson_id] ?? $allQuestions['basics'];

} catch (PDOException $e) {
  die("Database Error: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LingoFlow v2 - <?php ?></title>
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
      --glass: rgba(255, 255, 255, 0.05);
      --border: rgba(255, 255, 255, 0.1);
    }

    body {
      margin: 0;
      font-family: "Segoe UI", sans-serif;
      background-color: var(--bg-deep);
      color: var(--text-main);
      height: 100vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      background-image: radial-gradient(circle at 50% 0%,
          rgba(139, 92, 246, 0.15) 0%,
          transparent 50%);
    }

    .top-bar {
      padding: 20px 40px;
      display: flex;
      align-items: center;
      gap: 30px;
      width: 100%;
      max-width: 1000px;
      margin: 0 auto;
    }

    .progress-container {
      flex: 1;
      height: 12px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 6px;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: var(--violet);
      width: 0%;
      transition: width 0.4s ease;
      box-shadow: 0 0 15px var(--violet-glow);
    }

    .quiz-area {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 20px;
      width: 100%;
      max-width: 700px;
      margin: 0 auto;
    }

    .options-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      width: 100%;
    }

    .options-button {
      background: var(--glass);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 24px;
      color: var(--text-main);
      font-size: 18px;
      cursor: pointer;
      transition: 0.2s;
      position: relative;
    }

    .options-button:hover {
      background: rgba(255, 255, 255, 0.1);
      transform: translateY(-3px);
    }

    .options-button.selected {
      border-color: var(--violet);
      background: rgba(139, 92, 246, 0.1);
    }

    .options-button.correct {
      background: rgba(34, 197, 94, 0.2);
      border-color: var(--green);
      color: var(--green);
    }

    .options-button.wrong {
      background: rgba(239, 68, 68, 0.2);
      border-color: var(--red);
      color: var(--red);
    }

    .bottom-bar {
      border-top: 1px solid var(--border);
      padding: 30px;
      display: flex;
      justify-content: center;
      background: rgba(2, 6, 23, 0.8);
    }

    .action-button {
      background: var(--violet);
      color: white;
      border: none;
      padding: 16px 60px;
      border-radius: 12px;
      font-size: 18px;
      font-weight: bold;
      cursor: pointer;
      opacity: 0.5s;
      pointer-events: none;
      transition: 0.2s;
    }

    .action-button.active {
      opacity: 1;
      pointer-events: auto;
    }

    .action-button.active:hover {
      background: #7c3aed;
      box-shadow: 0 0 15px var(--violet-glow);
      transform: translateY(-2px);
    }

    .feedback-msg {
      margin-right: auto;
      font-weight: bold;
      display: none;
      font-size: 18px;
    }

    /* Mobile responsiveness for options */
    @media (max-width: 600px) {
      .options-grid {
        grid-template-columns: 1fr;
      }

      .options-button {
        padding: 18px;
        font-size: 16px;
      }
    }
  </style>
</head>

<body>
  <header class="top-bar">
    <button onclick="window.location.href = 'index.php'" style="
          background: none;
          color: white;
          cursor: pointer;
          font-size: 24px;
          transition: 0.2s;
        " onmouseover="this.style.color = 'var(--red)'" onmouseout="this.style.color = 'white'">
      ✕
    </button>
    <div class="progress-container">
      <div class="progress-fill" id="progress-bar"></div>
    </div>
  </header>

  <main class="quiz-area">
    <h2 id="question-text" style="margin-bottom: 40px; text-align: center; font-size: 24px"></h2>

    <div class="options-grid" id="options-container">
      <!-- Options injected via JS -->
    </div>
  </main>

  <footer class="bottom-bar">
    <div style="
          width: 100%;
          max-width: 1000px;
          display: flex;
          align-items: center;
          justify-content: space-between;
        ">
      <div id="feedback" class="feedback-msg"></div>
      <button class="action-button" id="check-button" onclick="handleCheck()">
        Check Answer
      </button>
    </div>
  </footer>
  <script>
    // 1. Get raw data from PHP
    const questions = <?php echo json_encode($currentQuestion); ?>;
    const lessonId = "<?php echo $lesson_id; ?>";

    let currentIndex = 0;
    let selectedOption = null;
    let isAnswered = false;
    let lastCorrectPosition = -1; // To track where the correct answer was last time

    /**
     * True Random Shuffle (Fisher-Yates)
     */
    function shuffleArray(array) {
      for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
      }

      return array;
    }

    function loadQuestion() {
      isAnswered = false;
      selectedOption = null;
      document.getElementById("feedback").style.display = "none";

      const button = document.getElementById("check-button");
      button.innerText = "Check Answer";
      button.classList.remove("active");

      const q = questions[currentIndex];
      document.getElementById("question-text").innerText = q.q;

      const container = document.getElementById("options-container");
      container.innerHTML = "";

      // --- ABSOLUTE RANDOMIZATION LOGIC ---
      // 1. Copy the answers
      let currentAnswers = [...q.answers];

      // 2. Shuffle them
      currentAnswers = shuffleArray(currentAnswers);

      // 3. (Optional) Pattern Breaker:
      // If the correct answer ended up in the EXACT same visual slot as the previous question,
      // and we have more than 1 option, shuffle again one more time to try and move it.
      // This makes it feel "more random" to humans by avoiding repetition.
      if (currentAnswers.length > 1) {
        let newCorrectPos = currentAnswers.findIndex((a) => a.correct);
        if (newCorrectPos === lastCorrectPosition) {
          // Shuffle one more time to try and dislodge it
          currentAnswers = shuffleArray(currentAnswers);
        }
      }

      // 4. Update tracker for next time
      lastCorrectPosition = currentAnswers.findIndex((a) => a.correct);

      // 5. Render
      currentAnswers.forEach((ans) => {
        const b = document.createElement("button");
        b.className = "option-button";
        b.innerText = ans.text;

        b.onclick = () => {
          if (isAnswered) return;
          document
            .querySelectorAll(".option-button")
            .forEach((el) => el.classList.remove("selected"));
          b.classList.add("selected");
          selectedOption = b;
          button.classList.add("active");
        };

        // Store truth data on the button DOM element
        b.dataset.correct = ans.correct;
        container.appendChild(b);
      });

      // Update progress bar
      document.getElementById("progress-bar").style.width =
        `${(currentIndex / questions.length) * 100}%`;
    }
    function handleCheck() {
      const button = document.createElement("check-button");

      if (isAnswered) {
        // Go to next question or finish
        currentIndex++;
        if (currentIndex < questions.length) {
          loadQuestion();
        } else {
          // REDIRECT COMMAND
          button.innerText = "Syncing...";
          button.style.opacity = "0.5";
          button.style.pointerEvents = "none";
          window.location.replace(
            `lesson.php?type=${lessonId}&action=complete`,
          );
        }
        return;
      }

      // Logic to check answer
      isAnswered = true;
      const isCorrect = selectedOption.dataset.correct === "true";

      selectedOption.classList.remove("selected");
      selectedOption.classList.add(isCorrect ? "correct" : "wrong");

      // If wrong, highlight the correct one
      if (!isCorrect) {
        document.querySelectorAll(".option-button").forEach((b) => {
          if (b.dataset.correct === "true") b.classList.add("correct");
        });
      }

      // Feedback Text
      const feed = document.getElementById("feedback");
      feed.innerHTML = isCorrect ? "Correct! 🎉" : "Incorrect 💀";
      feed.style.color = isCorrect ? "var(--green)" : "var(--red)";
      feed.style.display = "block";

      button.innerText = "Continue";
    }

    //Initialize
    loadQuestion();
  </script>
</body>

</html>