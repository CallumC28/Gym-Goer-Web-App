<?php
session_start();
if (!isset($_SESSION['loggedin'])) {
    header('Location: index.html');
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tracker";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

if (!function_exists('calculateImprovement')) {
    function calculateImprovement($old, $new) {
        if ($old == 0) return 0;
        return round((($new - $old) / $old) * 100, 2);
    }
}

$exerciseProgress = [];
$sql = "SELECT DISTINCT exercise_name FROM exercise_logs WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $exercise_name = $row['exercise_name'];

    // Get the first log for the exercise
    $queryFirst = "SELECT log_date, weight, sets, reps FROM exercise_logs WHERE user_id = ? AND exercise_name = ? ORDER BY log_date ASC LIMIT 1";
    $stmtFirst = $conn->prepare($queryFirst);
    $stmtFirst->bind_param("is", $user_id, $exercise_name);
    $stmtFirst->execute();
    $firstResult = $stmtFirst->get_result()->fetch_assoc();

    // Get the most recent log for the exercise
    $queryRecent = "SELECT log_date, weight, sets, reps FROM exercise_logs WHERE user_id = ? AND exercise_name = ? ORDER BY log_date DESC LIMIT 1";
    $stmtRecent = $conn->prepare($queryRecent);
    $stmtRecent->bind_param("is", $user_id, $exercise_name);
    $stmtRecent->execute();
    $recentResult = $stmtRecent->get_result()->fetch_assoc();

    // Save only the first and the most recent log (for the charts)
    $logs = [$firstResult, $recentResult];

    if ($firstResult && $recentResult) {
        $progress = [
            'exercise_name'  => $exercise_name,
            'first_date'     => date("d M Y", strtotime($firstResult['log_date'])),
            'recent_date'    => date("d M Y", strtotime($recentResult['log_date'])),
            'first_weight'   => $firstResult['weight'],
            'recent_weight'  => $recentResult['weight'],
            'weight_change'  => calculateImprovement($firstResult['weight'], $recentResult['weight']),
            'first_sets'     => $firstResult['sets'],
            'recent_sets'    => $recentResult['sets'],
            'sets_change'    => calculateImprovement($firstResult['sets'], $recentResult['sets']),
            'first_reps'     => $firstResult['reps'],
            'recent_reps'    => $recentResult['reps'],
            'reps_change'    => calculateImprovement($firstResult['reps'], $recentResult['reps']),
            'logs'           => $logs
        ];
        $exerciseProgress[] = $progress;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Exercise Progress</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .fade-in { animation: fadeIn 0.8s ease-in-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    
    .card {
      background: #1F2937;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
    }
    .card:hover {
      transform: scale(1.03);
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    
    .progress-bar {
      transition: width 1.5s ease-in-out;
    }
    .increase { background-color: #34D399; } 
    .decrease { background-color: #EF4444; }
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
  </style>
</head>
<body class="bg-gray-900 text-white fade-in">
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

<main class="max-w-6xl mx-auto px-4 mt-24 pb-10 fade-in">
  <h1 class="text-3xl sm:text-4xl font-bold text-center text-blue-400">Your Exercise Progress</h1>
  <!-- Search and Sort -->
<div class="max-w-2xl mx-auto mt-20 mb-6 px-2">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
    <input type="text" id="search-bar" placeholder="Search exercises..." 
           class="w-full md:w-1/2 p-3 rounded-md bg-gray-700 text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500">
    <select id="sort-select" class="w-full md:w-1/3 p-3 rounded-md bg-gray-700 text-white focus:ring-2 focus:ring-blue-500">
         <option value="name">Sort by Name</option>
         <option value="weight_change">Sort by Weight Change</option>
         <option value="sets_change">Sort by Sets Change</option>
         <option value="reps_change">Sort by Reps Change</option>
    </select>
  </div>
</div>
  <div id="exercise-list" class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <?php foreach ($exerciseProgress as $progress): 
          // Create a slug for unique element IDs
          $slug = preg_replace('/\s+/', '-', strtolower($progress['exercise_name']));
    ?>
      <div class="card shadow-lg transition-transform duration-300" 
           data-name="<?= strtolower($progress['exercise_name']) ?>"
           data-weight_change="<?= $progress['weight_change'] ?>"
           data-sets_change="<?= $progress['sets_change'] ?>"
           data-reps_change="<?= $progress['reps_change'] ?>">
        <h2 class="text-2xl font-bold"><?= htmlspecialchars($progress['exercise_name']) ?></h2>
        <p class="text-sm text-gray-400 mb-4">Started: <?= $progress['first_date'] ?> | Latest: <?= $progress['recent_date'] ?></p>

        <?php 
          $metrics = ['Weight' => 'weight', 'Sets' => 'sets', 'Reps' => 'reps'];
          foreach ($metrics as $label => $key):
            $change = $progress["{$key}_change"];
            $firstValue = $progress["first_{$key}"];
            $recentValue = $progress["recent_{$key}"];
        ?>
          <p class="mt-4"><?= $label ?>: <?= $firstValue ?> ➝ <?= $recentValue ?></p>
          <div class="relative w-full bg-gray-700 h-6 rounded overflow-hidden">
            <div class="absolute top-0 left-0 h-6 progress-bar <?= $change >= 0 ? 'increase' : 'decrease' ?>" 
                 style="width: <?= min(100, abs($change)) ?>%;"></div>
          </div>
          <p class="text-lg font-bold <?= $change >= 0 ? 'text-green-400' : 'text-red-400' ?>">
            <?= $change >= 0 ? '+' : '' ?><?= $change ?>% <?= $change >= 0 ? 'Increase' : 'Decrease' ?>
          </p>
        <?php endforeach; ?>

        <!-- Toggle Details Button -->
        <button class="toggle-details mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded transition-colors">
          View Detailed Progress
        </button>

        <!-- Detailed Chart Section (initially hidden) -->
        <div class="details hidden mt-4" data-logs='<?= htmlspecialchars(json_encode($progress['logs'])) ?>'>
          <h3 class="text-xl font-semibold mb-2">Progress Charts</h3>
          <div class="space-y-6">
            <div>
              <h4 class="font-medium mb-1">Weight Progress</h4>
              <canvas id="chart-<?= $slug ?>-weight" height="150"></canvas>
            </div>
            <div>
              <h4 class="font-medium mb-1">Sets Progress</h4>
              <canvas id="chart-<?= $slug ?>-sets" height="150"></canvas>
            </div>
            <div>
              <h4 class="font-medium mb-1">Reps Progress</h4>
              <canvas id="chart-<?= $slug ?>-reps" height="150"></canvas>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <p id="no-results" class="text-center text-gray-400 hidden">No exercises found.</p>
</main>

<script>
  //Search Functionality
  document.getElementById("search-bar").addEventListener("input", function() {
    let searchValue = this.value.toLowerCase();
    let cards = document.querySelectorAll("#exercise-list .card");
    let noResults = document.getElementById("no-results");
    let found = false;

    cards.forEach(card => {
      let name = card.getAttribute("data-name");
      if (name.includes(searchValue)) {
        card.style.display = "block";
        found = true;
      } else {
        card.style.display = "none";
      }
    });

    noResults.classList.toggle("hidden", found);
  });

  //Sorting Functionality
  document.getElementById("sort-select").addEventListener("change", function() {
    let sortBy = this.value;
    let cardsContainer = document.getElementById("exercise-list");
    let cards = Array.from(cardsContainer.querySelectorAll(".card"));

    cards.sort((a, b) => {
      if (sortBy === "name") {
        let nameA = a.getAttribute("data-name");
        let nameB = b.getAttribute("data-name");
        return nameA.localeCompare(nameB);
      } else {
        let valA = parseFloat(a.getAttribute("data-" + sortBy));
        let valB = parseFloat(b.getAttribute("data-" + sortBy));
        return valB - valA;
      }
    });

    cards.forEach(card => cardsContainer.appendChild(card));
  });

  //Toggle Detailed Chart View & Initialise Charts
  document.querySelectorAll(".toggle-details").forEach(button => {
    button.addEventListener("click", function() {
      let card = this.closest(".card");
      let details = card.querySelector(".details");
      if (details.classList.contains("hidden")) {
        details.classList.remove("hidden");
        this.textContent = "Hide Detailed Progress";
        initializeCharts(details);
      } else {
        details.classList.add("hidden");
        this.textContent = "View Detailed Progress";
      }
    });
  });

  //Initialise charts only once per details section
  function initializeCharts(details) {
    if (details.getAttribute("data-charts-initialized") === "true") return;

    let logs = JSON.parse(details.getAttribute("data-logs"));
    //logs contains exactly two entries: the first and the most recent log.
    let labels = ["First", "Recent"];

    // Prepare data arrays for each metric using the two logs
    let weightData = [parseFloat(logs[0].weight), parseFloat(logs[1].weight)];
    let setsData   = [parseFloat(logs[0].sets), parseFloat(logs[1].sets)];
    let repsData   = [parseFloat(logs[0].reps), parseFloat(logs[1].reps)];

    //Define color pairs for each metric:
    //First logged data: darker color; most recent data: brighter color.
    const weightColors = ["#1E40AF", "#3B82F6"]; // Dark Blue, Bright Blue
    const setsColors   = ["#4C1D95", "#8B5CF6"]; // Dark Purple, Bright Purple
    const repsColors   = ["#B91C1C", "#F87171"]; //Dark Red, Bright Red

    // Get the canvases from the details section
    let canvasWeight = details.querySelector("canvas[id*='-weight']");
    let canvasSets = details.querySelector("canvas[id*='-sets']");
    let canvasReps = details.querySelector("canvas[id*='-reps']");

    //Weight Chart
    //Create Doughnut Charts for each metric
    new Chart(canvasWeight.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          label: 'Weight',
          data: weightData,
          backgroundColor: weightColors,
          hoverBackgroundColor: weightColors
        }]
      },
      options: {
        scales: {
          x: { display: true },
          y: { display: true, beginAtZero: true }
        }
      }
    });

    //Sets Chart
    new Chart(canvasSets.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          label: 'Sets',
          data: setsData,
          backgroundColor: setsColors,
          hoverBackgroundColor: setsColors
        }]
      },
      options: {
        scales: {
          x: { display: true },
          y: { display: true, beginAtZero: true }
        }
      }
    });

    //Reps Chart
    new Chart(canvasReps.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          label: 'Reps',
          data: repsData,
          backgroundColor: repsColors,
          hoverBackgroundColor: repsColors
        }]
      },
      options: {
        scales: {
          x: { display: true },
          y: { display: true, beginAtZero: true }
        }
      }
    });

    details.setAttribute("data-charts-initialized", "true");
  }
  
</script>
<script>
     //Mobile Menu Toggle
     document.getElementById('menu-toggle').addEventListener('click', function() {
      const menu = document.getElementById('mobile-menu');
      menu.classList.toggle('hidden');
    });
    //Close menu when clicking outside
    document.addEventListener('click', function(event) {
      const menu = document.getElementById('mobile-menu');
      const button = document.getElementById('menu-toggle');
      if (!menu.contains(event.target) && !button.contains(event.target)) {
          menu.classList.add('hidden');
      }
    });
</script>
</body>
</html>
