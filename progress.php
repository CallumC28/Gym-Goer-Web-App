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
$date_filter = date('Y-m-d', strtotime("- days"));

// Fetch weight log data
$query_weight = "SELECT log_date, weight FROM body_weight_logs WHERE user_id = ? AND log_date >= ? ORDER BY log_date ASC";
$stmt_weight = $conn->prepare($query_weight);
$stmt_weight->bind_param("is", $user_id, $date_filter);
$stmt_weight->execute();
$result_weight = $stmt_weight->get_result();
$weight_data = [];
while ($row = $result_weight->fetch_assoc()) {
    $weight_data[] = $row;
}

// Fetch exercise log data
$query_exercise = "SELECT log_date, exercise_name, weight FROM exercise_logs WHERE user_id = ? ORDER BY log_date DESC";
$stmt_exercise = $conn->prepare($query_exercise);
$stmt_exercise->bind_param("i", $user_id);
$stmt_exercise->execute();
$result_exercise = $stmt_exercise->get_result();
$exercise_data = [];
while ($row = $result_exercise->fetch_assoc()) {
    $exercise_data[] = $row;
}

// Fetch distinct exercise names for the dropdown menu
$query_exercise_names = "SELECT DISTINCT exercise_name FROM exercise_logs WHERE user_id = ?";
$stmt_exercise_names = $conn->prepare($query_exercise_names);
$stmt_exercise_names->bind_param("i", $user_id);
$stmt_exercise_names->execute();
$result_exercise_names = $stmt_exercise_names->get_result();
$exercise_names = [];
while ($row = $result_exercise_names->fetch_assoc()) {
    $exercise_names[] = $row['exercise_name'];
}

// Fetch the first (earliest) weight entry
$query_weight_start = "SELECT weight FROM body_weight_logs WHERE user_id = ? ORDER BY log_date ASC LIMIT 1";
$stmt_weight_start = $conn->prepare($query_weight_start);
$stmt_weight_start->bind_param("i", $user_id);
$stmt_weight_start->execute();
$result_weight_start = $stmt_weight_start->get_result();
$start_weight_row = $result_weight_start->fetch_assoc();
$start_weight = $start_weight_row ? $start_weight_row['weight'] : null;

// Fetch the most recent (latest) weight entry
$query_weight_latest = "SELECT weight FROM body_weight_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT 1";
$stmt_weight_latest = $conn->prepare($query_weight_latest);
$stmt_weight_latest->bind_param("i", $user_id);
$stmt_weight_latest->execute();
$result_weight_latest = $stmt_weight_latest->get_result();
$current_weight_row = $result_weight_latest->fetch_assoc();
$current_weight = $current_weight_row ? $current_weight_row['weight'] : null;

// Fetch the best PR lift (highest weight lifted)
$query_best_pr = "SELECT exercise_name, weight FROM exercise_logs WHERE user_id = ? ORDER BY weight DESC LIMIT 1";
$stmt_best_pr = $conn->prepare($query_best_pr);
$stmt_best_pr->bind_param("i", $user_id);
$stmt_best_pr->execute();
$result_best_pr = $stmt_best_pr->get_result();
$best_pr = $result_best_pr->fetch_assoc();
$best_exercise = $best_pr['exercise_name'] ?? "No data available";
$best_weight = $best_pr['weight'] ?? "--";

// Close all prepared statements
$stmt_weight_start->close();
$stmt_weight_latest->close();
$stmt_best_pr->close();
$stmt_weight->close();
$stmt_exercise->close();
$stmt_exercise_names->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Fitness Tracker | Progress</title>
    <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet" />
    <style>
        .glassmorphism {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
        }
        .fade-in {
            animation: fadeIn 0.8s ease-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .badge {
            background: linear-gradient(45deg, #60A5FA, #3B82F6);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 14px;
            color: white;
        }

        .nav-link {
            position: relative;
            color: white;
            font-size: 16px;
            font-weight: 500;
            transition: color 0.3s ease-in-out;
        }
        .nav-link:hover {
            color: #60a5fa;
        }
        .nav-link::after {
            content: "";
            display: block;
            width: 0;
            height: 2px;
            background: #60a5fa;
            transition: width 0.3s ease-in-out;
        }
        .nav-link:hover::after {
            width: 100%;
        }

        .mobile-nav-link {
            display: block;
            padding: 12px 16px;
            color: white;
            font-weight: 500;
            transition: background 0.3s ease-in-out;
        }
        .mobile-nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 768px) {
        .chart-container {
            width: 100% !important;
            min-height: 400px; /* Increased minimum height for a larger chart display */
            height: auto !important;
            position: relative;
        }
        .chart-container canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100% !important;
            height: 100% !important;
        }
        /* Hide the size sliders on mobile */
        #weight-size-slider,
        #exercise-size-slider {
            display: none;
        }
        }

        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-900 text-white">

<nav class="bg-gray-800 bg-opacity-90 backdrop-blur-md shadow-lg fixed w-full top-0 left-0 z-50">
  <div class="max-w-6xl mx-auto px-4 flex justify-between items-center py-4 relative">
    <a href="Homepage.php" class="flex items-center space-x-2">
      <img src="assets/Logo_Fitness.png" alt="Fitness Tracker Logo" class="h-8">
      <span class="text-white text-lg font-bold tracking-wide hidden sm:block">Fitness Tracker</span>
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

<!-- Progress Section -->
<main class="max-w-6xl mx-auto px-6 mt-24 fade-in">
    <h2 class="text-5xl font-bold text-blue-400 text-center">
        <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES) ?>'s Progress
    </h2>

    <!-- Progress Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
        <div class="glassmorphism p-6 text-center">
            <h3 class="text-xl">Total Weight Change</h3>
            <?php 
            if (count($weight_data) >= 2) {
                $start_weight = (float) $weight_data[0]['weight'];
                $current_weight = (float) end($weight_data)['weight'];
                $weight_difference = $current_weight - $start_weight;
                $weight_sign = ($weight_difference > 0) ? "+" : "";
                $weight_color = ($weight_difference > 0) ? "text-green-400" : (($weight_difference < 0) ? "text-red-400" : "text-gray-400");
            } else {
                $weight_difference = 0;
                $weight_sign = "";
                $weight_color = "text-gray-400";
            }
            ?>
            <p class="text-2xl font-bold <?= $weight_color ?>">
                <?= $weight_sign . number_format($weight_difference, 2); ?> kg
            </p>
        </div>
        <div class="glassmorphism p-6 text-center">
            <h3 class="text-xl">Best PR Lift</h3>
            <p class="text-3xl font-bold text-blue-400">
                <?= $best_weight ?> kg
            </p>
            <p class="text-sm text-gray-300 mt-1">
                <?= htmlspecialchars($best_exercise); ?>
            </p>
        </div>
        <div class="glassmorphism p-6 text-center">
            <h3 class="text-xl">Consistency Streak</h3>
            <p class="text-2xl font-bold text-blue-400"><?= count($exercise_data) ?> Days</p>
        </div>
    </div>

    <!-- Chart Type Selection -->
    <div class="mt-8 flex justify-center space-x-4">
        <label for="chart-type" class="text-white">Select Chart Type:</label>
        <select id="chart-type" class="bg-gray-700 text-white p-2 rounded-md" onchange="updateChartType()">
            <option value="line">Line Chart</option>
            <option value="bar">Bar Chart</option>
            <option value="radar">Radar Chart</option>
        </select>
    </div>

    <!-- Time Frame Selection for Weight Chart -->
    <div class="mt-4 flex justify-center space-x-4">
        <label for="weight-time-frame" class="text-white">Select Time Frame:</label>
        <select id="weight-time-frame" class="bg-gray-700 text-white p-2 rounded-md" onchange="renderWeightChart()">
            <option value="30">Last 30 Days</option>
            <option value="90">Last 90 Days</option>
            <option value="365">Last 365 Days</option>
            <option value="all">All Data</option>
        </select>
    </div>

    <!-- Weight Chart + Slider -->
    <div class="mt-8">
        <h3 class="text-xl text-blue-400">Weight Progress</h3>
        <!-- Slider to resize the chart -->
        <div class="flex items-center space-x-2 mt-2">
            <label for="weight-size-slider" class="text-white">Size:</label>
            <input 
                id="weight-size-slider" 
                type="range" 
                min="0.5" max="2" step="0.1" 
                value="1" 
                oninput="updateWeightChartSize(this.value)" 
                class="w-48"
            />
        </div>
        <div id="weightChartContainer" class="chart-container">
            <canvas id="weightChart"></canvas>
        </div>
    </div>

    <!-- Time Frame Selection for Exercise Chart -->
    <div class="mt-4 flex justify-center space-x-4">
        <label for="exercise-time-frame" class="text-white">Select Time Frame:</label>
        <select id="exercise-time-frame" class="bg-gray-700 text-white p-2 rounded-md" onchange="updateExerciseChart()">
            <option value="30">Last 30 Days</option>
            <option value="90">Last 90 Days</option>
            <option value="365">Last 365 Days</option>
            <option value="all">All Data</option>
        </select>
    </div>

    <!-- Exercise Progress + Slider -->
    <div class="mt-8">
        <h3 class="text-xl text-blue-400">Exercise Progress</h3>
        <select id="exercise-name" class="bg-gray-700 text-white p-2 rounded-md" onchange="updateExerciseChart()">
            <option value="">-- Select an Exercise --</option>
            <?php foreach ($exercise_names as $exercise_name) { ?>
                <option value="<?= $exercise_name; ?>"><?= $exercise_name; ?></option>
            <?php } ?>
        </select>
        <!-- Slider to resize the chart -->
        <div class="flex items-center space-x-2 mt-2">
            <label for="exercise-size-slider" class="text-white">Size:</label>
            <input 
                id="exercise-size-slider" 
                type="range" 
                min="0.5" max="2" step="0.1" 
                value="1" 
                oninput="updateExerciseChartSize(this.value)" 
                class="w-48"
            />
        </div>
        <div id="exerciseChartContainer" class="chart-container">
            <canvas id="exerciseChart"></canvas>
        </div>
    </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const menuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');

    if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', function () {
            mobileMenu.classList.toggle('hidden');
        });

        // Close menu when clicking outside
        document.addEventListener('click', function (event) {
            if (!mobileMenu.contains(event.target) && !menuToggle.contains(event.target)) {
                mobileMenu.classList.add('hidden');
            }
        });
    }
});

//Chart Data
let weightData = <?= json_encode($weight_data); ?>;
let exerciseData = <?= json_encode($exercise_data); ?>;
let chartType = "line";
let weightChartInstance = null;
let exerciseChartInstance = null;

// Chart Options
function getChartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false, //allow chart to resize with container
        animation: {
            duration: 800,
            easing: "easeInOutQuart"
        },
        plugins: {
            legend: {
                display: true,
                labels: {
                    color: "#ffffff",
                    font: { size: 14 }
                }
            },
            tooltip: {
                backgroundColor: "rgba(0, 0, 0, 0.8)",
                titleFont: { size: 14, weight: "bold" },
                bodyFont: { size: 12 },
                borderWidth: 1,
                borderColor: "#60A5FA"
            }
        },
        scales: {
            x: {
                grid: { color: "rgba(255, 255, 255, 0.1)" },
                ticks: { color: "#ffffff", font: { size: 12 } }
            },
            y: {
                grid: { color: "rgba(255, 255, 255, 0.2)" },
                ticks: { color: "#ffffff", font: { size: 12 } },
                beginAtZero: false
            }
        }
    };
}

//Filter Weight Data By Time Frame
function filterDataByTimeFrameWeight(data, days) {
    if (days === "all") return data;
    let cutoffDate = new Date();
    cutoffDate.setDate(cutoffDate.getDate() - parseInt(days));
    return data.filter(entry => {
        let entryDate = new Date(entry.log_date.replace(/-/g, '/'));
        return entryDate >= cutoffDate;
    });
}

// Filter Exercise Data By Time Frame
function filterDataByTimeFrameExercise(data, days) {
    if (days === "all") return data;
    if (data.length === 0) return [];

    // Get the most recent log date for the selected exercise
    let mostRecentLogDate = new Date(data[0].log_date.replace(/-/g, '/'));
    let cutoffDate = new Date(mostRecentLogDate);
    cutoffDate.setDate(cutoffDate.getDate() - parseInt(days));

    return data.filter(entry => {
        let entryDate = new Date(entry.log_date.replace(/-/g, '/'));
        return entryDate >= cutoffDate;
    });
}

//Format Date to dd/mm/yyyy
function formatDate(dateString) {
    let dateObj = new Date(dateString);
    return dateObj.toLocaleDateString('en-GB');
}

//Update Chart Type (Line/Bar/Radar)
function updateChartType() {
    chartType = document.getElementById("chart-type").value;
    renderWeightChart();
    updateExerciseChart();
}

//Render Weight Chart
function renderWeightChart() {
    let selectedTimeFrame = document.getElementById("weight-time-frame").value;
    let filteredData = filterDataByTimeFrameWeight(weightData, selectedTimeFrame);

    let weightLabels = filteredData.map(data => formatDate(data.log_date));
    let weightValues = filteredData.map(data => data.weight);

    let ctx = document.getElementById("weightChart").getContext("2d");

    if (weightChartInstance) weightChartInstance.destroy();
    weightChartInstance = new Chart(ctx, {
        type: chartType,
        data: {
            labels: weightLabels,
            datasets: [{
                label: "Weight (kg)",
                data: weightValues,
                borderColor: "#60A5FA",
                backgroundColor: "rgba(96, 165, 250, 0.2)",
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: "#ffffff",
                fill: true
            }]
        },
        options: getChartOptions()
    });
}
renderWeightChart();

// Update Exercise Chart
function updateExerciseChart() {
    let exerciseName = document.getElementById("exercise-name").value;
    let selectedTimeFrame = document.getElementById("exercise-time-frame").value;
    if (!exerciseName) return;

    let filteredData = exerciseData.filter(entry => entry.exercise_name === exerciseName);
    filteredData = filterDataByTimeFrameExercise(filteredData, selectedTimeFrame);
    if (filteredData.length === 0) return;

    // Reverse to get chronological order (oldest -> newest)
    filteredData.reverse();

    let ctx = document.getElementById("exerciseChart").getContext("2d");

    if (exerciseChartInstance) exerciseChartInstance.destroy();
    exerciseChartInstance = new Chart(ctx, {
        type: chartType,
        data: {
            labels: filteredData.map(e => formatDate(e.log_date)),
            datasets: [{
                label: exerciseName,
                data: filteredData.map(e => e.weight),
                borderColor: "#FFA500",
                backgroundColor: "rgba(255, 165, 0, 0.2)",
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: "#ffffff",
                fill: true
            }]
        },
        options: getChartOptions()
    });
}

// Resize Sliders for Each Chart
function updateWeightChartSize(scaleValue) {
    const container = document.getElementById('weightChartContainer');
    container.style.width = (600 * scaleValue) + 'px';
    container.style.height = (400 * scaleValue) + 'px';
    // After adjusting container size, force the chart to resize
    if (weightChartInstance) weightChartInstance.resize();
}

function updateExerciseChartSize(scaleValue) {
    const container = document.getElementById('exerciseChartContainer');
    container.style.width = (600 * scaleValue) + 'px';
    container.style.height = (400 * scaleValue) + 'px';
    // After adjusting container size, force the chart to resize
    if (exerciseChartInstance) exerciseChartInstance.resize();
}

//Ensure Charts Load on Page Load
window.onload = function () {
    document.getElementById("exercise-name").value = "";
    updateExerciseChart();
};
</script>
</body>
</html>
