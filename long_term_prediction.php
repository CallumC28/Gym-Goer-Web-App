<?php
session_start();
require 'vendor/autoload.php';

use Phpml\Regression\SVR;
use Phpml\SupportVectorMachine\Kernel;
use Phpml\Preprocessing\Imputer;
use Phpml\Preprocessing\Imputer\Strategy\MeanStrategy;

// Redirect if not logged in
if (!isset($_SESSION['loggedin'])) {
    header('Location: index.html');
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tracker";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'] ?? 1;

// Fetch exercise names for dropdown
$exercise_stmt = $conn->prepare("SELECT DISTINCT exercise_name FROM exercise_logs WHERE user_id = ? ORDER BY exercise_name");
$exercise_stmt->bind_param("i", $user_id);
$exercise_stmt->execute();
$exercise_result = $exercise_stmt->get_result();
$exercise_names = $exercise_result->fetch_all(MYSQLI_ASSOC);
$exercise_stmt->close();

// Initialise variables
$chosen_exercise = $custom_time_frame = null;
$most_recent_weight = $prediction_result = $exercise_data = null;
$common_sets = $common_reps = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exercise_name'])) {
    $chosen_exercise = htmlspecialchars($_POST['exercise_name'], ENT_QUOTES, 'UTF-8');
    $custom_time_frame = filter_var($_POST['custom_time_frame'] ?? 180, FILTER_VALIDATE_INT, ["options" => ["min_range" => 180, "max_range" => 365]]) ?: 180;

    // Fetch exercise data
    $stmt = $conn->prepare("SELECT log_date, weight, reps, sets FROM exercise_logs WHERE user_id = ? AND exercise_name = ? ORDER BY log_date ASC");
    $stmt->bind_param("is", $user_id, $chosen_exercise);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exercise_data[] = $row;
    }
    $stmt->close();

    if (!empty($exercise_data)) {
        $exercise_data = filterTopWeightsByDay($exercise_data);
        $most_recent_weight = end($exercise_data)['weight'];

        // Determine most common sets and reps
        $sets_counts = array_count_values(array_column($exercise_data, 'sets'));
        $reps_counts = array_count_values(array_column($exercise_data, 'reps'));
        $common_sets = array_search(max($sets_counts), $sets_counts) ?: 4;
        $common_reps = array_search(max($reps_counts), $reps_counts) ?: 12;

        $prediction_result = predictWeight($exercise_data, $custom_time_frame, $common_sets, $common_reps);
    }
}

// Filter function: Keep only the highest weight per day
function filterTopWeightsByDay($data) {
    $result = [];
    foreach ($data as $entry) {
        $date = substr($entry['log_date'], 0, 10);
        if (!isset($result[$date]) || $entry['weight'] > $result[$date]['weight']) {
            $result[$date] = $entry;
        }
    }
    return array_values($result);
}

// Prediction function for long-term (6 months to 1 year)
function predictWeight($data, $days_ahead, $common_sets = 4, $common_reps = 12) {
    if (count($data) < 5) {
        return ['error' => 'Need at least 5 data points for prediction'];
    }

    // Step 1: Calculate trend-based improvement rate using linear regression on recent 30 days (same as short-term)
    $last_date = strtotime(end($data)['log_date']);
    if ($last_date === false) {
        return ['error' => 'Invalid last date in data'];
    }
    $cutoff_date = $last_date - (30 * 24 * 60 * 60);
    $recent_data = array_filter($data, function($entry) use ($cutoff_date) {
        $date = strtotime($entry['log_date']);
        return $date !== false && $date >= $cutoff_date;
    });
    $recent_data = array_values($recent_data);
    $first_date = strtotime($data[0]['log_date']);
    if ($first_date === false) {
        return ['error' => 'Invalid first date in data'];
    }

    if (count($recent_data) > 1) {
        $first_recent_date = strtotime($recent_data[0]['log_date']);
        if ($first_recent_date === false) {
            return ['error' => 'Invalid first recent date in data'];
        }
        $x = [];
        $y = [];
        foreach ($recent_data as $entry) {
            $current_date = strtotime($entry['log_date']);
            if ($current_date === false) continue;
            $elapsed_days = ($current_date - $first_recent_date) / (60 * 60 * 24);
            $weight = filter_var($entry['weight'], FILTER_VALIDATE_FLOAT);
            if ($weight === false) continue;
            $x[] = $elapsed_days;
            $y[] = $weight;
        }
        if (count($x) < 2) {
            return ['error' => 'Insufficient valid data points in recent 30 days'];
        }
        $n = count($x);
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xy = 0;
        $sum_xx = 0;
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x[$i] * $y[$i];
            $sum_xx += $x[$i] * $x[$i];
        }
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_xx - $sum_x * $sum_x);
        $avg_daily_rate = $slope;
    } else {
        $x = [];
        $y = [];
        foreach ($data as $entry) {
            $current_date = strtotime($entry['log_date']);
            if ($current_date === false) continue;
            $elapsed_days = ($current_date - $first_date) / (60 * 60 * 24);
            $weight = filter_var($entry['weight'], FILTER_VALIDATE_FLOAT);
            if ($weight === false) continue;
            $x[] = $elapsed_days;
            $y[] = $weight;
        }
        if (count($x) < 2) {
            return ['error' => 'Insufficient valid data points in full dataset'];
        }
        $n = count($x);
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xy = 0;
        $sum_xx = 0;
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x[$i] * $y[$i];
            $sum_xx += $x[$i] * $x[$i];
        }
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_xx - $sum_x * $sum_x);
        $avg_daily_rate = $slope;
    }

    // Apply lighter tapering for long-term predictions (90% of trend rate)
    $tapered_daily_rate = $avg_daily_rate * 0.9;

    // Step 2: Prepare features for SVR model with weighting for recent data
    $samples = [];
    $targets = [];
    $weights = [];
    foreach ($data as $index => $entry) {
        $current_date = strtotime($entry['log_date']);
        if ($current_date === false) continue;
        $elapsed_days = ($current_date - $first_date) / (60 * 60 * 24);
        $weight = filter_var($entry['weight'], FILTER_VALIDATE_FLOAT);
        if ($weight === false) continue;
        $volume = $weight * ($entry['reps'] ?: 1) * ($entry['sets'] ?: 1);
        $samples[] = [$elapsed_days, $volume];
        $targets[] = $weight;
        $days_from_last = ($last_date - $current_date) / (60 * 60 * 24);
        $weights[] = exp(-$days_from_last / 30);
    }

    if (count($samples) < 2) {
        return ['error' => 'Insufficient valid data for SVR training'];
    }

    // Normalise features
    $days = array_column($samples, 0);
    $volumes = array_column($samples, 1);
    $minDays = min($days);
    $maxDays = max($days);
    $minVolume = min($volumes);
    $maxVolume = max($volumes);

    $normalizedSamples = array_map(function($sample) use ($minDays, $maxDays, $minVolume, $maxVolume) {
        $normDays = ($maxDays - $minDays) ? ($sample[0] - $minDays) / ($maxDays - $minDays) : 0;
        $normVolume = ($maxVolume - $minVolume) ? ($sample[1] - $minVolume) / ($maxVolume - $minVolume) : 0;
        return [$normDays, $normVolume];
    }, $samples);

    // Train SVR model (simulate weighting by duplicating recent samples)
    $weightedSamples = [];
    $weightedTargets = [];
    foreach ($samples as $i => $sample) {
        $num_copies = max(1, round($weights[$i] * 5));
        for ($j = 0; $j < $num_copies; $j++) {
            $weightedSamples[] = $normalizedSamples[$i];
            $weightedTargets[] = $targets[$i];
        }
    }

    $svr = new SVR(Kernel::RBF, 1000, 0.01);
    $svr->train($weightedSamples, $weightedTargets);

    // Step 3: Predict using SVR
    $last_entry = end($data);
    $last_date = strtotime($last_entry['log_date']);
    $last_elapsed = ($last_date - $first_date) / (60 * 60 * 24);
    $last_weight = filter_var($last_entry['weight'], FILTER_VALIDATE_FLOAT);
    $last_volume = $last_weight * ($last_entry['reps'] ?: 1) * ($last_entry['sets'] ?: 1);

    $future_elapsed = $last_elapsed + $days_ahead;
    $norm_future_elapsed = ($maxDays - $minDays) ? ($future_elapsed - $minDays) / ($maxDays - $minDays) : 0;
    $norm_last_volume = ($maxVolume - $minVolume) ? ($last_volume - $minVolume) / ($maxVolume - $minVolume) : 0;

    $svr_predicted_weight = $svr->predict([$norm_future_elapsed, $norm_last_volume]);

    // Step 4: Predict using the trend-based rate with tapering
    $rate_predicted_weight = $last_weight + $tapered_daily_rate * $days_ahead;

    // Step 5: Combine predictions (favor rate-based more for long-term)
    $recent_trend = array_slice($data, -3);
    $trend_increasing = true;
    for ($i = 1; $i < count($recent_trend); $i++) {
        $current_weight = filter_var($recent_trend[$i]['weight'], FILTER_VALIDATE_FLOAT);
        $prev_weight = filter_var($recent_trend[$i-1]['weight'], FILTER_VALIDATE_FLOAT);
        if ($current_weight === false || $prev_weight === false || $current_weight < $prev_weight) {
            $trend_increasing = false;
            break;
        }
    }
    $svr_weight = $trend_increasing ? 0.2 : 0.4; // Less SVR influence for long-term
    $rate_weight = 1 - $svr_weight;
    $combined_predicted_weight = ($svr_weight * $svr_predicted_weight + $rate_weight * $rate_predicted_weight);

    // Step 6: Calculate sets and reps progression 
    $last_reps = $last_entry['reps'] ?: $common_reps;
    $last_sets = $last_entry['sets'] ?: $common_sets;
    $progress_days = $days_ahead / 30;
    $predicted_sets = $last_sets + ceil($progress_days * 0.5);
    $predicted_reps = $last_reps + ceil($progress_days * 1.5);

    // Final daily rate for reference
    $final_daily_rate = $days_ahead ? ($combined_predicted_weight - $last_weight) / $days_ahead : 0;

    return [
        'weight' => round($combined_predicted_weight, 2),
        'days_ahead' => $days_ahead,
        'avg_daily_rate' => round($final_daily_rate, 4),
        'predicted_sets' => $predicted_sets,
        'predicted_reps' => $predicted_reps
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Fitness Tracker | Long-Term Prediction</title>
    <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2.2.1/dist/chartjs-plugin-annotation.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        @keyframes fade-slide { 
            0% { opacity: 0; transform: translateY(20px); } 
            100% { opacity: 1; transform: translateY(0); } 
        }
        .animate-fade-slide { animation: fade-slide 0.6s ease-out; }
        .chart-container { width: 100%; max-width: 1200px; margin: 0 auto; height: 500px; }
        canvas { background: transparent; width: 100% !important; height: 100% !important; }
        body { font-family: 'Poppins', sans-serif; }
        .prediction-card { transition: transform 0.2s; }
        .prediction-card:hover { transform: scale(1.03); }
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
        .milestone-card {
            background: #374151;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            text-align: center;
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
    <!-- Navigation Bar -->
    <nav class="bg-gray-800 bg-opacity-90 backdrop-blur-md shadow-lg fixed w-full top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 flex justify-between items-center py-4 relative">
            <a href="Homepage.php" class="flex items-center space-x-2">
                <img src="assets/Logo_Fitness.png" alt="Fitness Tracker Logo" class="h-8">
                <span class="text-white text-lg font-bold tracking-wide hidden sm:block">Fitness Tracker</span>
            </a>
            <div class="hidden md:flex space-x-8">
                <a href="profile.php" class="nav-link"><i class="ri-user-line text-lg"></i> Profile</a>
                <a href="Homepage.php" class="nav-link"><i class="ri-home-line text-lg"></i> Home</a>
                <a href="short_term_prediction.php" class="nav-link"><i class="ri-line-chart-line text-lg"></i> Short-Term Prediction</a>
                <a href="logout.php" class="nav-link text-red-500 hover:text-red-600"><i class="ri-logout-box-line text-lg"></i> Logout</a>
            </div>
            <button id="menu-toggle" class="md:hidden text-white focus:outline-none">
                <i class="ri-menu-3-line text-2xl"></i>
            </button>
            <div id="mobile-menu" class="absolute top-16 right-4 bg-gray-800 bg-opacity-95 backdrop-blur-md rounded-lg w-48 shadow-xl hidden">
                <a href="profile.php" class="mobile-nav-link"><i class="ri-user-line text-lg"></i> Profile</a>
                <a href="Homepage.php" class="mobile-nav-link"><i class="ri-home-line text-lg"></i> Home</a>
                <a href="short_term_prediction.php" class="mobile-nav-link"><i class="ri-line-chart-line text-lg"></i> Short-Term Prediction</a>
                <a href="logout.php" class="mobile-nav-link text-red-500 hover:text-red-600"><i class="ri-logout-box-line text-lg"></i> Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto px-4 mt-24 animate-fade-slide">
        <div class="bg-gray-800 rounded-lg p-6 shadow-lg">
            <div class="text-center mb-6">
                <h2 class="text-3xl font-bold text-blue-500">Long-Term Exercise Prediction</h2>
                <p class="text-gray-400">Predict your performance in the next 6–12 months</p>
            </div>

            <!-- Prediction Form -->
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="exercise_name" class="block text-lg">Choose an Exercise:</label>
                    <select id="exercise_name" name="exercise_name" class="mt-2 p-2 rounded bg-gray-700 text-white w-full">
                        <?php foreach ($exercise_names as $exercise): ?>
                            <option value="<?= htmlspecialchars($exercise['exercise_name']) ?>" <?= ($chosen_exercise === $exercise['exercise_name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($exercise['exercise_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="custom_time_frame" class="block text-lg">Prediction Time Frame (Days, 180–365):</label>
                    <input type="number" name="custom_time_frame" id="custom_time_frame" class="mt-2 p-2 rounded bg-gray-700 text-white w-full" min="180" max="365" value="<?= $custom_time_frame ?: 180 ?>" required />
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white p-3 rounded hover:bg-blue-500">Get Prediction</button>
                </div>
            </form>

            <!-- Display Prediction Results -->
            <?php if ($chosen_exercise && $prediction_result): ?>
                <?php if (isset($prediction_result['error'])): ?>
                    <div class="p-4 bg-red-900 rounded text-center">
                        <p><?= htmlspecialchars($prediction_result['error']) ?></p>
                    </div>
                <?php else: ?>
                    <div class="prediction-card p-4 bg-gray-700 rounded text-center">
                    <h3 class="text-xl font-semibold">Predicted Weight in <?= $prediction_result['days_ahead'] ?> Days</h3>
                    <p class="mt-1 text-blue-500 text-lg"><?= $prediction_result['weight'] ?> kg</p>
                    <p class="mt-2 text-gray-400">Based on your last weight: <?= round($most_recent_weight, 2) ?> kg</p>
                    <p class="text-sm text-gray-500">Avg. daily improvement rate: <?= $prediction_result['avg_daily_rate'] ?> kg/day</p>
                    <p class="text-sm text-gray-400">Predicted Sets/Reps: <?= $prediction_result['predicted_sets'] ?> sets of <?= $prediction_result['predicted_reps'] ?> reps</p>
                    </div>

                    <!-- Enhanced Chart Section -->
                    <?php if (!empty($exercise_data) && count($exercise_data) >= 5): ?>
                        <div class="mt-8">
                            <div class="flex flex-col items-center mb-4">
                                <label for="chartTypeSelector" class="mr-2 text-lg">Select Chart Type:</label>
                                <select id="chartTypeSelector" class="p-2 bg-gray-700 text-white rounded">
                                    <option value="line">Line Chart</option>
                                    <option value="bar">Bar Chart</option>
                                    <option value="radar">Radar Chart</option>
                                    <option value="pie">Pie Chart</option>
                                    <option value="doughnut">Ring Chart</option>
                                </select>
                            </div>
                            <div class="chart-container">
                                <canvas id="exerciseChart"></canvas>
                            </div>
                            <div class="text-center mt-4">
                                <button id="exportBtn" class="bg-blue-600 text-white p-2 rounded hover:bg-blue-500">Export as CSV/PDF</button>
                            </div>
                        </div>

        <!-- Milestone Breakdown -->
        <div class="mt-8">
          <h3 class="text-xl font-semibold text-center mb-4">Milestone Breakdown</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php
              $milestone_intervals = [180,272,365];
              $total_weight_increase = $prediction_result['weight'] - $most_recent_weight;
              $total_days = $prediction_result['days_ahead'];
              foreach ($milestone_intervals as $interval) {
                  if ($interval <= $prediction_result['days_ahead']) {
                      $proportion = $interval / $total_days;
                      $milestone_weight = $most_recent_weight + ($total_weight_increase * $proportion);
                      $milestone_weight = min($milestone_weight, $prediction_result['weight']);
                      $milestone_sets = min($common_sets, max(3, $prediction_result['predicted_sets'] - floor(($interval / 30) / 2)));
                      $milestone_reps = min($common_reps, max(5, $prediction_result['predicted_reps'] - floor($interval / 30)));
                      echo "<div class='milestone-card'>";
                      echo "<p class='text-lg font-semibold'>In $interval Days</p>";
                      echo "<p class='text-blue-500'>" . round($milestone_weight, 2) . " kg</p>";
                      echo "<p class='text-gray-400'>$milestone_sets sets of $milestone_reps reps</p>";
                      echo "</div>";
                  }
              }
            ?>
          </div>
        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Navigation toggle
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

        // Parse a date string "dd/mm/yyyy" into a Date object
        function parseDateFromLabel(label) {
            const parts = label.split('/');
            return new Date(parts[2], parts[1] - 1, parts[0]);
        }

        let chartInstance = null;

        function renderChart(chartType) {
            if (chartInstance) chartInstance.destroy();
            const ctx = document.getElementById('exerciseChart').getContext('2d');

            const labels = exerciseData.map(data => {
                const date = new Date(data.log_date);
                const day = ("0" + date.getDate()).slice(-2);
                const month = ("0" + (date.getMonth() + 1)).slice(-2);
                const year = date.getFullYear();
                return `${day}/${month}/${year}`;
            });
            const weights = exerciseData.map(data => data.weight);

            // Future label for prediction point
            const lastDate = new Date(exerciseData[exerciseData.length - 1].log_date);
            lastDate.setDate(lastDate.getDate() + prediction.days_ahead);
            const futureLabel = (() => {
                const day = ("0" + lastDate.getDate()).slice(-2);
                const month = ("0" + (lastDate.getMonth() + 1)).slice(-2);
                const year = lastDate.getFullYear();
                return `${day}/${month}/${year}`;
            })();

            const data = {
                labels: [...labels, futureLabel],
                datasets: [
                    {
                        label: 'Historical Weight (kg)',
                        data: weights,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        fill: false,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    },
                    {
                        label: `Predicted Weight (in ${prediction.days_ahead} days)`,
                        data: [...Array(weights.length).fill(null), prediction.weight],
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                        fill: false,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }
                ]
            };

            const options = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: "#fff" } },
                    tooltip: {
                        mode: 'nearest',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                const index = context.dataIndex;
                                const datasetLabel = context.dataset.label;
                                const value = context.raw;
                                if (datasetLabel === 'Historical Weight (kg)' && index < exerciseData.length) {
                                    const data = exerciseData[index];
                                    return [
                                        `${datasetLabel}: ${value} kg`,
                                        `Reps: ${data.reps}`,
                                        `Sets: ${data.sets}`,
                                        `Volume: ${(data.weight * data.reps * data.sets).toFixed(2)} kg`
                                    ];
                                }
                                return `${datasetLabel}: ${value} kg`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        suggestedMin: Math.min(...weights, prediction.weight) * 0.9,
                        suggestedMax: Math.max(...weights, prediction.weight) * 1.1,
                        ticks: { color: "#fff" },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    },
                    x: {
                        ticks: { color: "#fff", maxTicksLimit: 10 },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    }
                },
                interaction: { mode: 'nearest', intersect: false }
            };

            chartInstance = new Chart(ctx, { type: chartType, data, options });
        }

        document.getElementById('exportBtn').addEventListener('click', function() {
            const csvData = [];
            csvData.push(['Date', 'Weight (kg)', 'Reps', 'Sets']);
            exerciseData.forEach(data => {
                csvData.push([data.log_date, data.weight, data.reps, data.sets]);
            });
            csvData.push(['Predicted Date', `In ${prediction.days_ahead} days`, prediction.weight, prediction.predicted_reps, prediction.predicted_sets]);
            const csvContent = csvData.map(row => row.join(',')).join('\n');
            const csvBlob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const csvUrl = URL.createObjectURL(csvBlob);
            const csvLink = document.createElement('a');
            csvLink.href = csvUrl;
            csvLink.download = 'exercise_data.csv';
            csvLink.click();

            html2canvas(document.querySelector('.chart-container')).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();
                pdf.addImage(imgData, 'PNG', 10, 10, 190, 100);
                pdf.text('Exercise Data and Prediction', 10, 120);
                pdf.text(`Predicted Weight: ${prediction.weight} kg in ${prediction.days_ahead} days`, 10, 130);
                pdf.text(`Predicted Sets/Reps: ${prediction.predicted_sets} sets of ${prediction.predicted_reps} reps`, 10, 140);
                pdf.save('exercise_chart.pdf');
            });
        });

        document.getElementById('chartTypeSelector').addEventListener('change', function() {
            renderChart(this.value);
        });

        <?php if (!empty($exercise_data) && count($exercise_data) >= 5 && $prediction_result && !isset($prediction_result['error'])): ?>
            const exerciseData = <?= json_encode($exercise_data) ?>;
            const prediction = <?= json_encode($prediction_result) ?>;
            renderChart('line');
        <?php endif; ?>
    </script>
</body>
</html>