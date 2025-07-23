<?php
session_start();
if (!isset($_SESSION['loggedin'])) {
    header('Location: index.html');
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tracker";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$user_id = $_SESSION['user_id'];
$recommendations = [];

// Get a list of distinct exercises logged by the user
$stmt = $conn->prepare("SELECT DISTINCT exercise_name FROM exercise_logs WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$exercises = [];
while ($row = $result->fetch_assoc()) {
    $exercises[] = $row['exercise_name'];
}
$stmt->close();

// For each exercise, analyse the latest logs to generate a recommendation
foreach ($exercises as $exercise) {
    // Get the most recent 3 logs for this exercise
    $stmt = $conn->prepare("SELECT weight, log_date FROM exercise_logs WHERE user_id = ? AND exercise_name = ? ORDER BY log_date DESC LIMIT 3");
    $stmt->bind_param("is", $user_id, $exercise);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // If not enough data is available, ask for more logs.
    if (count($logs) < 2) {
        $recommendations[$exercise] = [
            'message' => "Not enough data for '$exercise'. Keep logging your workouts for personalised tips.",
            'detail'  => "Consistent tracking allows us to deliver more accurate and actionable recommendations."
        ];
        continue;
    }

    // When 3 logs are available, use them for a more nuanced recommendation
    if (count($logs) >= 3) {
        $log0 = $logs[0]; // Most recent
        $log1 = $logs[1];
        $log2 = $logs[2];

        // Calculate differences between consecutive logs
        $diff1 = floatval($log0['weight']) - floatval($log1['weight']);
        $diff2 = floatval($log1['weight']) - floatval($log2['weight']);

        if ($diff1 < 0 || $diff2 < 0) {
            $recommendations[$exercise] = [
                'message' => "Your performance on '$exercise' has declined.",
                'detail'  => "Review your form, ensure proper recovery, and consider adjusting your training load."
            ];
        } elseif ($diff1 < 0.5 && $diff2 < 0.5) {
            $recommendations[$exercise] = [
                'message' => "Your '$exercise' progress appears to have plateaued.",
                'detail'  => "Try slight increases in weight or rep variations to break through the plateau."
            ];
        } elseif ($diff1 >= 0.5 && $diff2 >= 0.5) {
            $recommendations[$exercise] = [
                'message' => "Excellent, consistent progress on '$exercise'!",
                'detail'  => "Keep challenging yourself while maintaining proper form to avoid injury."
            ];
        } else {
            $recommendations[$exercise] = [
                'message' => "Your '$exercise' performance shows mixed trends.",
                'detail'  => "Focus on consistency and proper technique. More sessions may help fine-tune your approach."
            ];
        }
    } else {
        // When only 2 logs are available.
        $latest = $logs[0];
        $previous = $logs[1];
        $diff = floatval($latest['weight']) - floatval($previous['weight']);
        if ($diff < 0) {
            $recommendations[$exercise] = [
                'message' => "A decline in performance for '$exercise' has been detected.",
                'detail'  => "Reassess your workout strategy and ensure proper recovery to improve results."
            ];
        } elseif ($diff < 0.5) {
            $recommendations[$exercise] = [
                'message' => "Progress on '$exercise' is minimal.",
                'detail'  => "Challenge yourself with a small increase or try a different variation to stimulate growth."
            ];
        } else {
            $recommendations[$exercise] = [
                'message' => "Good progress on '$exercise'!",
                'detail'  => "Keep up the effort and consider a gradual increase to build further strength."
            ];
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Fitness Tracker | AI-Powered Recommendations</title>
  <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
  <style>
    /* Fade/Slide Animation */
    @keyframes fadeSlide {
      0% { opacity: 0; transform: translateY(-10px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-slide { animation: fadeSlide 0.6s ease-out forwards; }
    
    /* Card Styling */
    .glassmorphism {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .rec-card {
      background: rgba(255, 255, 255, 0.05);
      padding: 1.5rem;
      border-radius: 0.75rem;
      box-shadow: 0 4px 8px rgba(0,0,0,0.3);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      position: relative;
    }
    .rec-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 16px rgba(0,0,0,0.5);
    }
    .rec-title {
      font-size: 1.5rem;
      font-weight: 600;
      margin-bottom: 0.75rem;
    }
    .rec-text {
      font-size: 1rem;
      line-height: 1.5;
    }
    .rec-positive { color: #34D399; }
    .rec-warning { color: #FBBF24; }
    
    /* "Learn More" Toggle */
    .more-info {
      max-height: 0;
      overflow: hidden;
      opacity: 0;
      transition: max-height 0.3s ease, opacity 0.3s ease;
      font-size: 0.9rem;
      margin-top: 0.5rem;
      color: #a0aec0;
    }
    .more-info.open { max-height: 200px; opacity: 1; }
    
    /* Search Bar */
    .search-bar {
      width: 100%;
      max-width: 400px;
      margin: 1.5rem auto;
      position: relative;
    }
    .search-bar input {
      width: 100%;
      padding: 0.75rem 1rem;
      border-radius: 9999px;
      border: none;
      outline: none;
      font-size: 1rem;
      background: rgba(255, 255, 255, 0.1);
      color: white;
    }
    .search-bar input::placeholder {
      color: #9ca3af;
    }
    
    /* Filter Buttons */
    .filter-group {
      display: flex;
      justify-content: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .filter-btn {
      padding: 0.5rem 1rem;
      border-radius: 9999px;
      border: none;
      background: rgba(255, 255, 255, 0.1);
      color: white;
      cursor: pointer;
      transition: background 0.3s ease, transform 0.3s ease;
    }
    .filter-btn.active,
    .filter-btn:hover {
      background: #60a5fa;
      transform: scale(1.05);
    }
    
    /* Navigation Styling */
    .nav-link {
      position: relative;
      color: white;
      font-size: 16px;
      font-weight: 500;
      transition: color 0.3s ease-in-out;
    }
    .nav-link:hover { color: #60a5fa; }
    .nav-link::after {
      content: "";
      display: block;
      width: 0;
      height: 2px;
      background: #60a5fa;
      transition: width 0.3s ease-in-out;
    }
    .nav-link:hover::after { width: 100%; }
    .mobile-nav-link {
      display: block;
      padding: 12px 16px;
      color: white;
      font-weight: 500;
      transition: background 0.3s ease-in-out;
    }
    .mobile-nav-link:hover { background: rgba(255, 255, 255, 0.1); }
    
    body { font-family: 'Poppins', sans-serif; }
    main { padding-bottom: 2rem; }
  </style>
</head>
<body class="bg-gray-900 text-white">

  <nav class="bg-gray-800 bg-opacity-90 backdrop-blur-md shadow-lg fixed w-full top-0 z-50 animate-fade-slide">
    <div class="max-w-6xl mx-auto px-4 flex justify-between items-center py-4">
      <a href="Homepage.php" class="flex items-center space-x-2">
        <img src="assets/Logo_Fitness.png" alt="Fitness Tracker Logo" class="h-8">
        <span class="hidden sm:block text-white text-lg font-bold">Fitness Tracker</span>
      </a>
      <div class="hidden md:flex space-x-8">
        <a href="profile.php" class="nav-link"><i class="ri-user-line text-lg"></i> Profile</a>
        <a href="Homepage.php" class="nav-link"><i class="ri-home-line text-lg"></i> Home</a>
        <a href="logout.php" class="nav-link text-red-500 hover:text-red-600"><i class="ri-logout-box-line text-lg"></i> Logout</a>
      </div>
      <button id="menu-toggle" class="md:hidden text-white focus:outline-none">
        <i class="ri-menu-3-line text-2xl"></i>
      </button>
      <div id="mobile-menu" class="absolute top-16 right-4 bg-gray-800 bg-opacity-95 backdrop-blur-md rounded-lg w-48 shadow-xl hidden">
        <a href="profile.php" class="mobile-nav-link"><i class="ri-user-line text-lg"></i> Profile</a>
        <a href="Homepage.php" class="mobile-nav-link"><i class="ri-home-line text-lg"></i> Home</a>
        <a href="logout.php" class="mobile-nav-link text-red-500 hover:text-red-600"><i class="ri-logout-box-line text-lg"></i> Logout</a>
      </div>
    </div>
  </nav>

  <main class="max-w-6xl mx-auto px-6 mt-24 animate-fade-slide">
    <h1 class="text-4xl font-bold text-blue-400 mb-4 text-center">AI-Powered Recommendations</h1>
    
    <!-- Search Bar -->
    <div class="search-bar">
      <input type="text" id="search-input" placeholder="Search for an exercise..." />
    </div>
    
    <!-- Filter Buttons -->
    <div class="filter-group">
      <button class="filter-btn active" data-filter="all">All</button>
      <button class="filter-btn" data-filter="positive">Good Progress</button>
      <button class="filter-btn" data-filter="warning">Needs Improvement</button>
    </div>
    
    <?php if (empty($exercises)): ?>
      <div class="rec-card mx-auto max-w-lg text-center">
        <p class="rec-text">You haven't logged any workouts yet. Start tracking your exercises to receive personalised recommendations!</p>
        <div class="rec-action">
          <a href="workouts.php" class="feedback-btn">Log a Workout</a>
        </div>
      </div>
    <?php else: ?>
      <div id="rec-grid" class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <?php foreach ($recommendations as $exerciseName => $rec): 
          $filterType = (stripos($rec['message'], 'plateau') !== false || stripos($rec['message'], 'decline') !== false || stripos($rec['message'], 'drop') !== false) ? 'warning' : 'positive';
        ?>
          <div class="rec-card" data-exercise="<?= htmlspecialchars(strtolower($exerciseName)) ?>" data-type="<?= $filterType ?>">
            <h2 class="rec-title"><?= htmlspecialchars($exerciseName) ?></h2>
            <p class="rec-text <?= ($filterType === 'warning' ? 'rec-warning' : 'rec-positive') ?>"><?= htmlspecialchars($rec['message']) ?></p>
            <button class="mt-2 text-blue-400 hover:text-blue-300 text-sm focus:outline-none toggle-more">
              Learn More <i class="ri-arrow-down-s-line transition-transform duration-300"></i>
            </button>
            <div class="more-info">
              <p><?= htmlspecialchars($rec['detail']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
  <script>
    // Mobile Menu Toggle
    document.getElementById('menu-toggle').addEventListener('click', function() {
      const menu = document.getElementById('mobile-menu');
      menu.classList.toggle('hidden');
    });
    document.addEventListener('click', function(event) {
      const menu = document.getElementById('mobile-menu');
      const button = document.getElementById('menu-toggle');
      if (!menu.contains(event.target) && !button.contains(event.target)) {
          menu.classList.add('hidden');
      }
    });

    // Toggle "Learn More" in each recommendation card
    const toggleButtons = document.querySelectorAll('.toggle-more');
    toggleButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const moreInfo = btn.nextElementSibling;
        moreInfo.classList.toggle('open');
        btn.querySelector('i').classList.toggle('rotate-180');
      });
    });

    // Search and Filter Functionality
    const searchInput = document.getElementById('search-input');
    const recGrid = document.getElementById('rec-grid');
    const filterButtons = document.querySelectorAll('.filter-btn');
    let selectedFilter = 'all';

    function filterCards() {
      const query = searchInput.value.trim().toLowerCase();
      const cards = recGrid.querySelectorAll('.rec-card');
      cards.forEach(card => {
        const exercise = card.getAttribute('data-exercise');
        const type = card.getAttribute('data-type');
        const matchesQuery = exercise.includes(query);
        const matchesFilter = (selectedFilter === 'all' || type === selectedFilter);
        if (matchesQuery && matchesFilter) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    }

    searchInput.addEventListener('input', filterCards);

    filterButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        filterButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedFilter = btn.getAttribute('data-filter');
        filterCards();
      });
    });
  </script>
</body>
</html>
