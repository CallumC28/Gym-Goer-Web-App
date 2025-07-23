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

// Get the Latest Workout Date for the User
$user_id = $_SESSION['user_id'];
$query_latest_date = "SELECT log_date FROM exercise_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT 1";
$stmt = $conn->prepare($query_latest_date);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$latest_date_row = $result->fetch_assoc();
$latest_date = $latest_date_row ? $latest_date_row['log_date'] : null;
$stmt->close();

// Get the Most Recent Entry per Exercise on the Latest Workout Date
$latest_exercises = [];
if ($latest_date) {
    $query_latest_workout = "
        SELECT exercise_name, weight, reps, sets, log_date 
        FROM exercise_logs 
        WHERE user_id = ? AND log_date = ?
        ORDER BY exercise_name ASC, log_date DESC";
    $stmt = $conn->prepare($query_latest_workout);
    $stmt->bind_param("is", $user_id, $latest_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $exercise_seen = [];  // Track already added exercises

    while ($row = $result->fetch_assoc()) {
        $exercise_name = $row['exercise_name'];
        if (!isset($exercise_seen[$exercise_name])) {
            $query_most_recent_date = "
                SELECT MAX(log_date) as latest_log_date 
                FROM exercise_logs 
                WHERE user_id = ? AND exercise_name = ?";
            $stmt_recent_date = $conn->prepare($query_most_recent_date);
            $stmt_recent_date->bind_param("is", $user_id, $exercise_name);
            $stmt_recent_date->execute();
            $result_recent_date = $stmt_recent_date->get_result();
            $recent_date_row = $result_recent_date->fetch_assoc();
            $most_recent_date = $recent_date_row['latest_log_date'] ?? null;
            $stmt_recent_date->close();

            if ($most_recent_date) {
                $date_filter = date('Y-m-d', strtotime("$most_recent_date -30 days"));
                $query_exercise_history = "
                    SELECT log_date, weight FROM exercise_logs 
                    WHERE user_id = ? 
                    AND exercise_name = ? 
                    AND log_date BETWEEN ? AND ?
                    ORDER BY log_date ASC";
                $stmt_history = $conn->prepare($query_exercise_history);
                $stmt_history->bind_param("isss", $user_id, $exercise_name, $date_filter, $most_recent_date);
                $stmt_history->execute();
                $history_result = $stmt_history->get_result();
                $exercise_history = $history_result->fetch_all(MYSQLI_ASSOC);
                $stmt_history->close();
            } else {
                $exercise_history = [];
            }

            $latest_exercises[] = [
                'exercise_name' => $exercise_name,
                'weight' => $row['weight'],
                'reps' => $row['reps'],
                'sets' => $row['sets'],
                'log_date' => $row['log_date'],
                'history' => $exercise_history
            ];
            $exercise_seen[$exercise_name] = true;
        }
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Fitness Tracker | Dashboard</title>
  <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css"/>
  <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* Global Animation */
    @keyframes fadeSlide {
      0% { opacity: 0; transform: translateY(-10px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-slide { animation: fadeSlide 0.6s ease-out forwards; }

    /* Card Glassmorphism Style */
    .glassmorphism {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .hover-scale:hover {
      transform: scale(1.05);
      transition: 0.3s;
    }

    /* Navigation Styles */
    .nav-link {
      position: relative;
      color: #f3f4f6;
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
      color: #f3f4f6;
      font-weight: 500;
      transition: background 0.3s ease-in-out;
    }
    .mobile-nav-link:hover { background: rgba(255, 255, 255, 0.1); }

    /* Welcome Section with Bounce Animation */
    .welcome-heading {
      background: linear-gradient(90deg, #60a5fa, #3b82f6);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      animation: bounce 2s infinite;
    }
    @keyframes bounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }

    /* Swiper Container Styling */
    .swiper-container {
      border: 2px solid rgba(96, 165, 250, 0.4);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(96, 165, 250, 0.3);
      transition: transform 0.3s ease;
    }
    .swiper-container:hover { transform: scale(1.02); }
    .swiper-slide { padding: 1rem; }

    /* Dashboard Grid Enhancements */
    .dashboard-grid a {
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .dashboard-grid a:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(96, 165, 250, 0.5);
    }

    /* Drop Zones for Drag & Drop */
    .drop-zone {
      position: fixed;
      top: 0;
      bottom: 0;
      width: 390px; /* Wider drop zone */
      background: transparent; /* Invisible background */
      border: none;
      color: rgba(255, 255, 255, 0.5);
      font-size: 1.2rem;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 100;
      transition: background-color 0.2s ease;
    }
    /* Left zone default on left; right zone on right */
    #left-drop-zone { left: 0; }
    #right-drop-zone { right: 0; }

    /* Visual cue when dragging over */
    .drop-zone.dragover {
      background-color: rgba(255, 255, 255, 0.1);
    }
    
    /* Hide drop zones on mobile view */
    @media (max-width: 1366px) {
      .drop-zone { display: none; }
    }
    /* Style for the close button inside drop zones */
    .close-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      background: rgba(0, 0, 0, 0.5);
      color: #fff;
      border: none;
      border-radius: 50%;
      width: 30px;
      height: 30px;
      cursor: pointer;
      font-size: 1rem;
      line-height: 30px;
      text-align: center;
      z-index: 101;
    }

    body { font-family: 'Poppins', sans-serif; }
  </style>
</head>
<body class="bg-gray-900 text-white">
  <!-- Navigation Bar -->
  <nav class="bg-gray-800 bg-opacity-90 backdrop-blur-md shadow-lg fixed w-full top-0 z-50 opacity-0 animate-fade-slide">
    <div class="max-w-6xl mx-auto px-4 flex justify-between items-center py-4">
      <!-- Logo -->
      <a href="Homepage.php" class="flex items-center space-x-2" aria-label="Fitness Tracker Home">
        <img src="assets/Logo_Fitness.png" alt="Fitness Tracker Logo" class="h-8">
        <span class="hidden sm:block text-white text-lg font-bold tracking-wide">Fitness Tracker</span>
      </a>
      <!-- Desktop Navigation -->
      <div class="hidden md:flex space-x-8">
        <a href="profile.php" class="nav-link" aria-label="View Profile"><i class="ri-user-line text-lg"></i> Profile</a>
        <a href="Homepage.php" class="nav-link" aria-label="Homepage"><i class="ri-home-line text-lg"></i> Home</a>
        <a href="logout.php" class="nav-link text-red-500 hover:text-red-600" aria-label="Logout"><i class="ri-logout-box-line text-lg"></i> Logout</a>
      </div>
      <!-- Mobile Menu Button -->
      <button id="menu-toggle" class="md:hidden text-white focus:outline-none" aria-label="Toggle Navigation Menu">
        <i class="ri-menu-3-line text-2xl"></i>
      </button>
      <!-- Mobile Dropdown Menu -->
      <div id="mobile-menu" class="absolute top-16 right-4 bg-gray-800 bg-opacity-95 backdrop-blur-md rounded-lg w-48 shadow-xl hidden">
        <a href="profile.php" class="mobile-nav-link" aria-label="Profile"><i class="ri-user-line text-lg"></i> Profile</a>
        <a href="Homepage.php" class="mobile-nav-link" aria-label="Home"><i class="ri-home-line text-lg"></i> Home</a>
        <a href="logout.php" class="mobile-nav-link text-red-500 hover:text-red-600" aria-label="Logout"><i class="ri-logout-box-line text-lg"></i> Logout</a>
      </div>
    </div>
  </nav>

  <!-- Drop Zones for Drag & Drop Features (hidden on mobile) -->
  <div id="left-drop-zone" class="drop-zone">Drag &amp; Drop</div>
  <div id="right-drop-zone" class="drop-zone">Drag &amp; Drop</div>

  <!-- Welcome Section -->
  <main class="max-w-6xl mx-auto px-6 mt-24 opacity-0 animate-fade-slide">
    <div class="text-center">
      <h1 class="welcome-heading text-5xl font-bold drop-shadow-lg">
        Welcome Back, <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES) ?>!
      </h1>
      <p class="text-lg text-gray-400 mt-4">
        Let’s crush your fitness goals today! 💪
      </p>
    </div>

    <!-- Latest Workout & Progress Chart Section -->
    <section class="latest-workout-container mt-10">
      <h2 class="text-3xl font-bold text-blue-400 text-center">🔥 Latest Workout</h2>
      <?php if (!empty($latest_exercises)) : ?>
        <div class="swiper-container latest-workout-swiper overflow-hidden mt-6">
          <div class="swiper-wrapper">
            <?php foreach ($latest_exercises as $exercise) : ?>
              <div class="swiper-slide flex flex-col md:flex-row items-center md:items-start gap-6">
                <!-- Workout Info Card -->
                <div class="glassmorphism p-6 w-full md:w-1/2 text-center md:text-left">
                  <h3 class="text-2xl font-semibold text-blue-300">
                    <?= htmlspecialchars($exercise['exercise_name']); ?>
                  </h3>
                  <p class="text-lg text-gray-300 mt-2">
                    <span class="font-semibold">Weight:</span> <?= htmlspecialchars($exercise['weight']); ?> kg
                  </p>
                  <p class="text-lg text-gray-300">
                    <span class="font-semibold">Reps:</span> <?= htmlspecialchars($exercise['reps']); ?>
                  </p>
                  <p class="text-lg text-gray-300">
                    <span class="font-semibold">Sets:</span> <?= htmlspecialchars($exercise['sets']); ?>
                  </p>
                  <p class="text-sm text-gray-400 mt-1">
                    <i class="ri-calendar-line"></i> <?= date('d-m-Y', strtotime($exercise['log_date'])); ?>
                  </p>
                </div>
                <!-- Chart Container -->
                <div class="w-full md:w-1/2 h-[300px] relative">
                  <canvas id="chart-<?= str_replace(' ', '-', htmlspecialchars($exercise['exercise_name'])); ?>"></canvas>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="swiper-pagination"></div>
        </div>
      <?php else : ?>
        <p class="text-center text-gray-400 mt-4">No recent workouts logged.</p>
      <?php endif; ?>
    </section>

    <!-- Dashboard Grid for Site Features -->
    <?php 
      $features = [
        ['workouts.php', 'fas fa-dumbbell', 'text-green-500', 'Log Workouts'],
        ['nutrition.php', 'ri-calculator-line', 'text-blue-500', 'BMR Calculator'],
        ['progress.php', 'ri-bar-chart-line', 'text-yellow-500', 'Progression'],
        ['improvement.php', 'ri-clipboard-line', 'text-gray-400', 'Exercise Summary'],
        ['calendar.php', 'ri-calendar-line', 'text-purple-500', 'Calendar'],
        ['WeightLogger.php', 'ri-scales-line', 'text-purple-500', 'Log Body Weight'],
        ['weather.php', 'ri-sun-cloudy-line', 'text-orange-500', 'Weather'],
        ['Model_selector.php', 'ri-line-chart-line', 'text-pink-500', 'Predictions'],
        ['running.php', 'ri-run-line', 'text-red-500', 'Plan Your Run'],
        ['leaderboard.php', 'ri-trophy-line', 'text-yellow-200', 'Leaderboard'],
        ['videos.php', 'ri-disc-line', 'text-yellow-200', 'Educational Videos'],
        ['3D.php', 'ri-body-scan-line', 'text-pink-200', '3D Model'],
        ['achievements.php', 'ri-medal-line', 'text-yellow-400', 'Achievements'],
        ['goal-setting.php', 'ri-flag-line', 'text-red-400', 'Personal Goals'],
        ['workout_templates.php', 'ri-list-check-2', 'text-blue-500', 'Workout Templates']
      ];
    ?>
    <div class="grid dashboard-grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mt-8 opacity-0 animate-fade-slide">
      <?php 
        foreach ($features as $feature) {
          echo "<a href='{$feature[0]}' class='glassmorphism p-6 rounded-lg text-center hover-scale feature-tab' draggable='true' aria-label='{$feature[3]}'>
                  <i class='{$feature[1]} text-4xl {$feature[2]}'></i>
                  <h3 class='text-xl font-semibold mt-4'>{$feature[3]}</h3>
                </a>";
        }
      ?>
    </div>
  </main>
  <script>
    // Initialise Swiper for Latest Workout Slides
    var swiper = new Swiper('.swiper-container', {
      slidesPerView: 1,
      spaceBetween: 10,
      loop: true,
      autoplay: { delay: 4000, disableOnInteraction: false },
      speed: 1000,
      pagination: { el: '.swiper-pagination', clickable: true },
      allowTouchMove: true,
      watchOverflow: true,
      grabCursor: true,
    });

    // Mobile Menu Toggle with ARIA Accessibility
    const menuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    menuToggle.addEventListener('click', function() {
      mobileMenu.classList.toggle('hidden');
      menuToggle.setAttribute('aria-expanded', mobileMenu.classList.contains('hidden') ? 'false' : 'true');
    });
    document.addEventListener('click', function(event) {
      if (!mobileMenu.contains(event.target) && !menuToggle.contains(event.target)) {
          mobileMenu.classList.add('hidden');
          menuToggle.setAttribute('aria-expanded', 'false');
      }
    });

    // Initialise Charts for Exercise History
    document.addEventListener("DOMContentLoaded", function () {
      const exerciseData = <?= json_encode($latest_exercises); ?>;
      exerciseData.forEach(exercise => {
          const canvasId = `chart-${exercise.exercise_name.replace(/\s+/g, '-')}`;
          const ctx = document.getElementById(canvasId)?.getContext('2d');
          if (!ctx) return;

          if (!exercise.history || exercise.history.length === 0) return;

          const labels = exercise.history.map(entry => {
              const dateObj = new Date(entry.log_date);
              return dateObj.toLocaleDateString('en-GB');
          });
          const weights = exercise.history.map(entry => entry.weight);

          new Chart(ctx, {
              type: 'line',
              data: {
                  labels: labels,
                  datasets: [{
                      label: `${exercise.exercise_name} Progress (kg) Over 30 Days`,
                      data: weights,
                      borderColor: '#60A5FA',
                      backgroundColor: 'rgba(96, 165, 250, 0.2)',
                      fill: true,
                      tension: 0.3
                  }]
              },
              options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  scales: {
                      y: { beginAtZero: false },
                      x: { ticks: { autoSkip: true, maxTicksLimit: 5 } }
                  }
              }
          });
      });
    });

    // Drag and Drop Functionality for Feature Tabs
    document.querySelectorAll('.feature-tab').forEach(tab => {
      tab.addEventListener('dragstart', e => {
        e.dataTransfer.setData('text/plain', tab.getAttribute('href'));
      });
    });

    // Enable drop zones for left and right white space areas
    ['left-drop-zone', 'right-drop-zone'].forEach(zoneId => {
      const zone = document.getElementById(zoneId);
      
      zone.addEventListener('dragover', e => {
        e.preventDefault();
        zone.classList.add('dragover');
      });
      
      zone.addEventListener('dragleave', e => {
        zone.classList.remove('dragover');
      });
      
      zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('dragover');
        const url = e.dataTransfer.getData('text/plain');
        openFeatureInZone(url, zone);
      });
    });

    function openFeatureInZone(url, zone) {
      // Clear any previously loaded content in this drop zone
      zone.innerHTML = '';
      // Create an iframe to load the feature page
      const iframe = document.createElement('iframe');
      iframe.src = url;
      iframe.style.width = '100%';
      iframe.style.height = '100%';
      iframe.style.border = 'none';
      zone.appendChild(iframe);
      
      // Create a close button for this drop zone
      const closeButton = document.createElement('button');
      closeButton.className = 'close-btn';
      closeButton.innerHTML = '&times;';
      closeButton.addEventListener('click', () => {
          zone.innerHTML = 'Drag & Drop';
      });
      zone.appendChild(closeButton);
    }
  </script>
</body>
</html>