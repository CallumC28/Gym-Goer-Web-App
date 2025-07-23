<?php
session_start();
if (!isset($_SESSION['loggedin'])) {
    header('Location: index.html');
    exit;
}

$user_id = $_SESSION['user_id'];

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "tracker";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Fetch all achievements
$sqlAch = "SELECT * FROM achievements";
$resAch = $conn->query($sqlAch);
$allAchievements = [];
while ($a = $resAch->fetch_assoc()) {
    $allAchievements[] = $a;
}

// 2. Fetch which achievements the user has earned
$sqlUserAch = "SELECT achievement_id FROM user_achievements WHERE user_id=?";
$stmtUA = $conn->prepare($sqlUserAch);
$stmtUA->bind_param("i", $user_id);
$stmtUA->execute();
$resUA = $stmtUA->get_result();
$userAchievementIds = [];
while ($ua = $resUA->fetch_assoc()) {
    $userAchievementIds[] = $ua['achievement_id'];
}
$stmtUA->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Achievements & Badges</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
  <style>
    @keyframes fadeSlide {
      0% { opacity: 0; transform: translateY(-10px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-slide {
      animation: fadeSlide 0.6s ease-out forwards;
    }
    /* Header Section */
    .header-section {
      background: linear-gradient(135deg, #3b82f6, #60a5fa);
      padding: 2rem;
      border-radius: 12px;
      text-align: center;
      margin-bottom: 2rem;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.6);
    }
    .header-section h1 {
      font-size: 3rem;
      margin-bottom: 0.5rem;
    }
    .header-section p {
      font-size: 1.25rem;
    }
    .download-btn {
      background: linear-gradient(45deg, #60a5fa, #3b82f6);
      padding: 0.75rem 1.5rem;
      border-radius: 9999px;
      font-weight: 600;
      color: white;
      transition: background 0.3s ease;
    }
    .download-btn:hover {
      background: linear-gradient(45deg, #3b82f6, #60a5fa);
    }
    
    .summary-card {
      background: #1f2937;
      padding: 1.5rem;
      border-radius: 12px;
      text-align: center;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.7);
    }
 
    .badge-card {
      background: #1f2937;
      border: 2px solid transparent;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.7);
      transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }
    .badge-card.earned {
      border-color: #34d399;
    }
    .badge-card:hover {
      transform: translateY(-5px) scale(1.02);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.8);
    }

    .earned-ribbon {
      position: absolute;
      top: 0.75rem;
      right: 0.75rem;
      background: #34d399;
      color: white;
      padding: 0.25rem 0.5rem;
      font-size: 0.75rem;
      border-radius: 9999px;
      font-weight: 600;
    }
 
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
    /* Toggle More Info */
    .hidden-info {
      max-height: 0;
      overflow: hidden;
      opacity: 0;
      transition: max-height 0.3s ease, opacity 0.3s ease;
    }
    .hidden-info.open {
      max-height: 500px;
      opacity: 1;
    }
    /* Filter Button Styling */
    .filter-btn {
      border-radius: 9999px;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .filter-btn.active {
      box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.5);
      transform: scale(1.05);
    }

    body { font-family: 'Poppins', sans-serif; }
  </style>
</head>
<body class="bg-gradient-to-r from-gray-900 to-gray-800 text-white font-sans">

  <nav class="bg-gray-800 bg-opacity-90 backdrop-blur-md shadow-lg fixed w-full top-0 z-50">
    <div class="max-w-6xl mx-auto px-4 flex justify-between items-center py-4 relative">
      <a href="Homepage.php" class="flex items-center space-x-2">
        <img src="assets/Logo_Fitness.png" alt="Fitness Tracker Logo" class="h-8">
        <span class="hidden sm:block text-white text-lg font-bold tracking-wide">Fitness Tracker</span>
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
    <div class="text-center">
      <h1 class="text-5xl font-bold text-blue-400">
        <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES) ?>'s Achievements
      </h1>
      <p class="text-lg text-gray-400 mt-4">Goals to work towards</p>
    </div>

  <!-- FILTER BUTTONS -->
    <div class="flex flex-wrap justify-center mb-8 space-x-4">
      <button 
        class="filter-btn inline-flex items-center gap-2 px-5 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-medium shadow-md transform transition hover:scale-105 hover:from-blue-400 hover:to-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-300"
        data-filter="all"
      >
        <i class="ri-menu-line"></i> All
      </button>
      <button 
        class="filter-btn inline-flex items-center gap-2 px-5 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white font-medium shadow-md transform transition hover:scale-105 hover:from-green-400 hover:to-green-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-300"
        data-filter="earned"
      >
        <i class="ri-check-fill"></i> Earned
      </button>
      <button 
        class="filter-btn inline-flex items-center gap-2 px-5 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white font-medium shadow-md transform transition hover:scale-105 hover:from-red-400 hover:to-red-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-300"
        data-filter="not-earned"
      >
        <i class="ri-close-fill"></i> Not Earned
      </button>
    </div>

    <!-- ACHIEVEMENTS GRID -->
    <?php if (empty($allAchievements)): ?>
      <p class="text-center text-gray-400">No achievements found.</p>
    <?php else: ?>
      <div id="achievements-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($allAchievements as $ach):
          $earned = in_array($ach['achievement_id'], $userAchievementIds);
          $earnedClass = $earned ? 'earned' : '';
        ?>
          <div 
            class="badge-card <?php echo $earnedClass; ?>"
            data-earned="<?php echo $earned ? 'true' : 'false'; ?>"
          >
            <?php if ($earned): ?>
              <span class="earned-ribbon">Earned</span>
            <?php endif; ?>
            <!-- Badge Icon -->
            <img src="images/trophy-svgrepo-com.svg" alt="Badge Icon" class="w-16 h-16 object-contain mb-2">
            <h2 class="text-lg font-bold mb-2"><?= htmlspecialchars($ach['title']) ?></h2>
            <p class="text-sm text-gray-400 mb-4"><?= htmlspecialchars($ach['description']) ?></p>
            
            <!-- Earned / Not Earned Label and Celebrate Button -->
            <?php if ($earned): ?>
              <div class="flex flex-col items-center">
                <button class="celebrate-btn bg-green-700 mt-2 px-3 py-1 rounded hover:bg-green-600 text-sm focus:outline-none">
                  Celebrate!
                </button>
              </div>
            <?php else: ?>
              <span class="text-red-500 font-semibold">Not Earned</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
  <script>
    // Mobile Menu Toggle
    const menuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    if (menuToggle && mobileMenu) {
      menuToggle.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
      });
      document.addEventListener('click', (event) => {
        if (!mobileMenu.contains(event.target) && !menuToggle.contains(event.target)) {
          mobileMenu.classList.add('hidden');
        }
      });
    }

    // Filter Achievements
    const filterButtons = document.querySelectorAll('.filter-btn');
    const achievementsGrid = document.getElementById('achievements-grid');
    const achievementCards = achievementsGrid.querySelectorAll('.badge-card');

    filterButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        filterButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const filter = btn.getAttribute('data-filter');
        achievementCards.forEach(card => {
          const earnedStatus = card.getAttribute('data-earned');
          if (filter === 'all') {
            card.classList.remove('hidden');
          } else if (filter === 'earned' && earnedStatus === 'true') {
            card.classList.remove('hidden');
          } else if (filter === 'not-earned' && earnedStatus === 'false') {
            card.classList.remove('hidden');
          } else {
            card.classList.add('hidden');
          }
        });
      });
    });

    // Celebrate Confetti on Celebrate Button Click
    const celebrateButtons = document.querySelectorAll('.celebrate-btn');
    celebrateButtons.forEach(button => {
      button.addEventListener('click', (event) => {
        const rect = button.getBoundingClientRect();
        const originX = (rect.left + rect.width / 2) / window.innerWidth;
        const originY = (rect.top + rect.height / 2) / window.innerHeight;
        confetti({
          particleCount: 200,
          spread: 100,
          origin: { x: originX, y: originY },
          colors: ['#FFC700', '#FF0000', '#2E3192', '#27AE60', '#9B59B6']
        });
      });
    });
  </script>
</body>
</html>
