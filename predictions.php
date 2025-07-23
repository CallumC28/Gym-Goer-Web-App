<?php
session_start();
if (!isset($_SESSION['loggedin'])) {
    header('Location: index.html');
    exit;
}

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "tracker";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Retrieve Distinct Exercise Names
$exercise_query = "SELECT DISTINCT exercise_name FROM exercise_logs WHERE user_id = ?";
$exercise_stmt  = $conn->prepare($exercise_query);
$exercise_stmt->bind_param("i", $user_id);
$exercise_stmt->execute();
$exercise_result = $exercise_stmt->get_result();
$exercise_names  = [];
while ($row = $exercise_result->fetch_assoc()) {
    $exercise_names[] = $row['exercise_name'];
}
$exercise_stmt->close();

// Variable Initialization
$chosen_exercise    = null;   
$custom_time_frame  = null;   
$target_weight      = null;   
$target_reps        = null;   
$target_sets        = null;   
$most_recent_weight = null;   
$predicted_weight   = null;   
$predicted_reps     = null;   
$predicted_sets     = null;   
$day_to_target      = null;   
$exercise_data      = [];     

// Handle Form Submission & Data Retrieval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exercise_name'])) {
    // Get user inputs from the form.
    $chosen_exercise   = $_POST['exercise_name'];
    $custom_time_frame = isset($_POST['custom_time_frame']) ? intval($_POST['custom_time_frame']) : 7;
    $target_weight     = !empty($_POST['target_weight']) ? floatval($_POST['target_weight']) : null;
    $target_reps       = !empty($_POST['target_reps'])   ? intval($_POST['target_reps']) : null;
    $target_sets       = !empty($_POST['target_sets'])   ? intval($_POST['target_sets']) : null;
    
    //Retrieve all logs (date, weight, reps, sets) for the chosen exercise in chronological order.
    $query = "SELECT log_date, weight, reps, sets FROM exercise_logs WHERE user_id = ? AND exercise_name = ? ORDER BY log_date ASC";
    $stmt  = $conn->prepare($query);
    $stmt->bind_param("is", $user_id, $chosen_exercise);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exercise_data[] = $row;
    }
    $stmt->close();
    
    // Filter Data: Retain Only the Top Weight per Day
    function filterTopWeightsByDay($data) {
        $result = [];
        foreach ($data as $entry) {
            $date = substr($entry['log_date'], 0, 10);
            if (!isset($result[$date]) || $entry['weight'] > $result[$date]['weight']) {
                $result[$date] = $entry;
            }
        }
        $filtered = array_values($result);
        usort($filtered, function($a, $b) {
            return strtotime($a['log_date']) - strtotime($b['log_date']);
        });
        return $filtered;
    }
    $exercise_data = filterTopWeightsByDay($exercise_data);
    
    // Get the most recent log
    if (!empty($exercise_data)) {
        $most_recent_log    = end($exercise_data);
        $most_recent_weight = $most_recent_log['weight'];
    }
    
    //Helper Functions for Prediction
    function smoothDataWithSMA($data, $windowSize = 3) {
        if (count($data) < $windowSize) {
            return $data;
        }
        $smoothed = [];
        for ($i = 0; $i < count($data); $i++) {
            $start  = max(0, $i - $windowSize + 1);
            $subset = array_slice($data, $start, $i - $start + 1);
            $avgWeight = array_sum(array_column($subset, 'weight')) / count($subset);
            $avgReps   = array_sum(array_column($subset, 'reps'))   / count($subset);
            $avgSets   = array_sum(array_column($subset, 'sets'))   / count($subset);
            $smoothed[] = [
                'log_date' => $data[$i]['log_date'],
                'weight'   => $avgWeight,
                'reps'     => $avgReps,
                'sets'     => $avgSets
            ];
        }
        return $smoothed;
    }
    
    function linearRegression($x, $y) {
        $n = count($x);
        if ($n === 0) {
            return [0, 0];
        }
        $mean_x = array_sum($x) / $n;
        $mean_y = array_sum($y) / $n;
        $numerator = 0;
        $denom     = 0;
        for ($i = 0; $i < $n; $i++) {
            $numerator += ($x[$i] - $mean_x) * ($y[$i] - $mean_y);
            $denom     += pow(($x[$i] - $mean_x), 2);
        }
        $m = ($denom != 0) ? $numerator / $denom : 0;
        $b = $mean_y - $m * $mean_x;
        return [$m, $b]; //$m = Slope and $b = Intercept
    }
    
    /**
     * multiRegressionPrediction($data, $days_ahead, $target_weight, $target_reps, $target_sets)
     * - Modified to clamp the daily weight-increase slope to a realistic range.
     */
    function multiRegressionPrediction($data, $days_ahead, $target_weight = null, $target_reps = null, $target_sets = null) {
        // Step 1: Smooth the data.
        $data = smoothDataWithSMA($data, 3);
        $n = count($data);
        if ($n < 5) {
            return [
                'predicted_weight' => null,
                'predicted_reps'   => null,
                'predicted_sets'   => null,
                'day_to_target'    => null,
                'slope_weight'     => 0,
                'intercept_weight' => 0,
                'max_x'            => 0
            ];
        }
        
        // Step 2: Convert log dates to "days since first log".
        $first_date = strtotime($data[0]['log_date']);
        $x_time   = [];
        $y_weight = [];
        foreach ($data as $entry) {
            $days_since_first = (strtotime($entry['log_date']) - $first_date) / (60 * 60 * 24);
            $x_time[]   = $days_since_first;
            $y_weight[] = $entry['weight'];
        }
        
        // Step 3: Perform linear regression for weight over time.
        list($m_weight, $b_weight) = linearRegression($x_time, $y_weight);
        $max_time    = max($x_time);
        $future_time = $max_time + $days_ahead;
        
        
        // Enforce realistic daily gains (clamping)
        // Prevent negative slope as well
        $max_daily_gain = end($y_weight) * (0.10 / 7);
        if ($m_weight > $max_daily_gain) {
            $m_weight = $max_daily_gain;
        }
        if ($m_weight < 0) {
            $m_weight = 0; 
        }
        
        // Recalculate predicted weight with new slope:
        $predicted_weight = $m_weight * $future_time + $b_weight;
        
        // Step 4: Time to reach target weight (if any).
        $t_weight = null;
        if ($target_weight !== null && $m_weight > 0) {
            $t_weight = ($target_weight - $b_weight) / $m_weight;
        }
        
        // Step 5: Build regressions for reps and sets vs. weight.
        $x_forReps = [];
        $y_reps    = [];
        $x_forSets = [];
        $y_sets    = [];
        foreach ($data as $entry) {
            $x_forReps[] = $entry['weight'];
            $y_reps[]    = $entry['reps'];
            $x_forSets[] = $entry['weight'];
            $y_sets[]    = $entry['sets'];
        }
        list($m_reps, $b_reps) = linearRegression($x_forReps, $y_reps);
        list($m_sets, $b_sets) = linearRegression($x_forSets, $y_sets);
        
        // Step 6: Calculate target times for reps and sets if targets are provided.
        $t_reps = null;
        if ($target_reps !== null && $m_reps * $m_weight > 0) {
            $t_reps = ($target_reps - $m_reps * $b_weight - $b_reps) / ($m_reps * $m_weight);
        }
        $t_sets = null;
        if ($target_sets !== null && $m_sets * $m_weight > 0) {
            $t_sets = ($target_sets - $m_sets * $b_weight - $b_sets) / ($m_sets * $m_weight);
        }
        
        // Step 7: Overall target time from weight, reps, and sets.
        $t_target = max(
            $t_weight !== null ? $t_weight : 0,
            $t_reps   !== null ? $t_reps   : 0,
            $t_sets   !== null ? $t_sets   : 0
        );
        
        // Step 8: Compute additional days needed to reach the target.
        $day_to_target = ($t_target > $max_time) ? $t_target - $max_time : 0;
        
        // Step 9: Determine final predictions.
        $current_weight = end($y_weight);
        if ($t_target > $t_weight && $target_weight !== null && $target_reps !== null && $target_sets !== null) {
            // Freeze weight at current level if rep/set goals are the limiting factor.
            $predicted_weight_final = $current_weight;
            $predicted_reps_final   = (int) round($m_reps * $current_weight + $b_reps);
            $predicted_sets_final   = (int) round($m_sets * $current_weight + $b_sets);
        } else {
            $predicted_weight_final = $predicted_weight;
            $predicted_reps_short   = $m_reps * ($m_weight * $future_time + $b_weight) + $b_reps;
            $predicted_sets_short   = $m_sets * ($m_weight * $future_time + $b_weight) + $b_sets;
            
            if ($target_reps !== null) {
                $predicted_reps_final = min((int) round($predicted_reps_short), $target_reps);
            } else {
                $predicted_reps_final = (int) round($predicted_reps_short);
            }
            
            if ($target_sets !== null) {
                $predicted_sets_final = min((int) round($predicted_sets_short), $target_sets);
            } else {
                $predicted_sets_final = (int) round($predicted_sets_short);
            }
        }
        
        // Cap the predictions.
        $predicted_reps_final = min($predicted_reps_final, 12);
        $predicted_sets_final = min($predicted_sets_final, 4);
        
        return [
            'predicted_weight' => $predicted_weight_final,
            'predicted_reps'   => $predicted_reps_final,
            'predicted_sets'   => $predicted_sets_final,
            'day_to_target'    => $day_to_target,
            'slope_weight'     => $m_weight,
            'intercept_weight' => $b_weight,
            'max_x'            => $max_time
        ];
    }
    
    // Call the multi-step prediction function.
    $result = multiRegressionPrediction($exercise_data, $custom_time_frame, $target_weight, $target_reps, $target_sets);
    $predicted_weight = $result['predicted_weight'];
    $predicted_reps   = $result['predicted_reps'];
    $predicted_sets   = $result['predicted_sets'];
    $day_to_target    = $result['day_to_target'];
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
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
  <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* Simple fade/slide animation */
    @keyframes fade-slide {
      0% { opacity: 0; transform: translateY(20px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-slide { animation: fade-slide 0.6s ease-out; }
    /* Increase the chart container size for better readability */
    .chart-container {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      height: 500px;
    }
    /* Force the canvas to fill the container */
    canvas {
      background: transparent;
      width: 100% !important;
      height: 100% !important;
    }

    body { font-family: 'Poppins', sans-serif; }
  </style>
</head>
<body class="bg-gray-900 text-white">

  <nav class="bg-gray-800 bg-opacity-90 backdrop-blur-md shadow-lg fixed w-full top-0 z-50">
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
  
  <main class="max-w-6xl mx-auto px-4 mt-24 animate-fade-slide">
    <div class="bg-gray-800 rounded-lg p-6 shadow-lg">
      <div class="text-center mb-6">
        <h2 class="text-3xl font-bold text-center text-blue-500">Exercise Predictions</h2>
        <p class="text-gray-400">
          Enter your target performance for this exercise if desired. The model will calculate when you can expect to reach that feat.
          You may leave the target fields blank if you only want a timeline prediction.
        </p>
      </div>
      <!-- Input Form -->
      <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Choose an Exercise -->
        <div>
          <label for="exercise_name" class="block text-lg">Choose an Exercise:</label>
          <select id="exercise_name" name="exercise_name" class="mt-2 p-2 rounded bg-gray-700 text-white w-full">
            <?php foreach ($exercise_names as $exercise): ?>
              <option value="<?= htmlspecialchars($exercise) ?>" <?= ($chosen_exercise === $exercise) ? 'selected' : '' ?>>
                <?= htmlspecialchars($exercise) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- Custom Time Frame (Days) -->
        <div>
          <label for="custom_time_frame" class="block text-lg">Custom Time Frame (Days):</label>
          <input type="number" name="custom_time_frame" id="custom_time_frame" class="mt-2 p-2 rounded bg-gray-700 text-white w-full" min="1" value="<?= $custom_time_frame ?: 7 ?>" />
        </div>
        <!-- Target Weight (Optional) -->
        <div>
          <label for="target_weight" class="block text-lg">Target Weight (kg) <span class="text-sm text-gray-400">(Optional)</span>:</label>
          <input type="number" name="target_weight" id="target_weight" class="mt-2 p-2 rounded bg-gray-700 text-white w-full" min="0" <?= $target_weight ? 'value="' . $target_weight . '"' : '' ?> />
        </div>
        <!-- Target Reps (Optional) -->
        <div>
          <label for="target_reps" class="block text-lg">Target Reps <span class="text-sm text-gray-400">(Optional)</span>:</label>
          <input type="number" name="target_reps" id="target_reps" class="mt-2 p-2 rounded bg-gray-700 text-white w-full" min="1" value="<?= isset($target_reps) ? $target_reps : '' ?>" />
        </div>
        <!-- Target Sets (Optional) -->
        <div>
          <label for="target_sets" class="block text-lg">Target Sets <span class="text-sm text-gray-400">(Optional)</span>:</label>
          <input type="number" name="target_sets" id="target_sets" class="mt-2 p-2 rounded bg-gray-700 text-white w-full" min="1" value="<?= isset($target_sets) ? $target_sets : '' ?>" />
        </div>
        <!-- Submit Button -->
        <div class="flex items-end">
          <button type="submit" class="w-full bg-blue-600 text-white p-3 rounded hover:bg-blue-500">Get Prediction</button>
        </div>
      </form>
      
      <!-- Display Predictions -->
      <?php if ($chosen_exercise): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Predicted Weight -->
          <div class="p-4 bg-gray-700 rounded">
            <h3 class="text-xl font-semibold">Predicted Weight</h3>
            <p class="mt-1 text-blue-500">
              <?= $predicted_weight !== null ? round($predicted_weight, 2) . ' kg' : '<span class="text-red-500">Not enough data</span>' ?>
            </p>
          </div>
          <!-- Predicted Reps (Short-Term) -->
          <div class="p-4 bg-gray-700 rounded">
            <h3 class="text-xl font-semibold">Predicted Reps (at future time)</h3>
            <p class="mt-1 text-blue-500">
              <?= $predicted_reps !== null ? $predicted_reps : '<span class="text-red-500">Not enough data</span>' ?>
            </p>
          </div>
          <!-- Predicted Sets (Short-Term) -->
          <div class="p-4 bg-gray-700 rounded">
            <h3 class="text-xl font-semibold">Predicted Sets (at future time)</h3>
            <p class="mt-1 text-blue-500">
              <?= $predicted_sets !== null ? $predicted_sets : '<span class="text-red-500">Not enough data</span>' ?>
            </p>
          </div>
          <!-- Current Weight -->
          <div class="p-4 bg-gray-700 rounded">
            <h3 class="text-xl font-semibold">Current Weight</h3>
            <p class="mt-1 text-blue-500">
              <?= $most_recent_weight !== null ? round($most_recent_weight, 2) . ' kg' : '<span class="text-red-500">No data available</span>' ?>
            </p>
          </div>
        </div>
        
        <!-- Target Performance Prediction (if all targets provided) -->
        <?php if ($target_weight !== null && $target_reps !== null && $target_sets !== null): ?>
          <div class="mt-4 p-4 bg-gray-700 rounded text-center">
            <?php if ($day_to_target !== null && $day_to_target > 0): ?>
              <p class="text-xl">
                You will be able to perform <?= $target_sets ?> sets of <?= $target_reps ?> reps at <?= round($target_weight, 2) ?> kg in approximately <span class="text-blue-500"><?= round($day_to_target, 0) ?></span> days from now.
              </p>
            <?php else: ?>
              <p class="text-xl text-green-500">
                You have already reached or exceeded the target performance.
              </p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        
        <!-- Chart Section (only if enough data exists) -->
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
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
  <script>
    // Toggle mobile menu on click.
    document.getElementById('menu-toggle').addEventListener('click', function() {
      const menu = document.getElementById('mobile-menu');
      menu.classList.toggle('hidden');
    });
    // Close mobile menu if clicked outside.
    document.addEventListener('click', function(event) {
      const menu = document.getElementById('mobile-menu');
      const button = document.getElementById('menu-toggle');
      if (!menu.contains(event.target) && !button.contains(event.target)) {
        menu.classList.add('hidden');
      }
    });
    
    <?php if (!empty($exercise_data) && count($exercise_data) >= 5 && $predicted_weight !== null): ?>
      // Helper function to format dates (YYYY-MM-DD -> DD/MM/YYYY).
      function formatDate(dateString) {
          const parts = dateString.split('-');
          if (parts.length === 3) {
              return parts[2] + "/" + parts[1] + "/" + parts[0];
          }
          return dateString;
      }
      
      const exerciseData = <?= json_encode($exercise_data) ?>;
      const labels = exerciseData.map(data => data.log_date);
      const formattedLabels = labels.map(dateStr => formatDate(dateStr));
      const weightData = exerciseData.map(data => data.weight);
      
      // Compute a future date label by adding the custom time frame to the last logged date.
      const lastDate = new Date(labels[labels.length - 1]);
      lastDate.setDate(lastDate.getDate() + <?= $custom_time_frame ?>);
      const futureDateRaw = lastDate.toISOString().split('T')[0];
      const futureDate = formatDate(futureDateRaw);
      
      const predictedWeight = <?= $predicted_weight ?>;
      
      let chartInstance = null;
      
      function renderChart(chartType) {
          if (chartInstance !== null) {
              chartInstance.destroy();
          }
          const ctx = document.getElementById('exerciseChart').getContext('2d');
          
          const historicalDataset = {
              label: 'Historical Weight (kg)',
              data: [...weightData, null],
              borderColor: 'rgb(75, 192, 192)',
              backgroundColor: 'rgba(75, 192, 192, 0.2)',
              fill: true,
              tension: 0.3,
              pointRadius: 3,
          };
          
          const predictedDataset = {
              label: 'Predicted Weight (kg)',
              data: [...Array(weightData.length).fill(null), predictedWeight],
              borderColor: 'rgb(255, 99, 132)',
              backgroundColor: 'rgba(255, 99, 132, 0.8)',
              fill: false,
              pointRadius: 6,
              pointStyle: 'rectRot',
          };
          
          const data = {
              labels: [...formattedLabels, futureDate],
              datasets: [historicalDataset, predictedDataset]
          };
          
          const options = {
              responsive: true,
              maintainAspectRatio: false,
              layout: {
                  padding: {
                      top: 20,
                      right: 20,
                      bottom: 20,
                      left: 20
                  }
              },
              scales: {
                  y: { 
                      beginAtZero: false,
                      ticks: {
                          color: "#fff",
                          font: { size: 14 }
                      },
                      grid: {
                          color: 'rgba(255,255,255,0.1)'
                      }
                  },
                  x: { 
                      ticks: { 
                          autoSkip: true, 
                          maxTicksLimit: 10,
                          color: "#fff",
                          font: { size: 14 }
                      },
                      grid: {
                          color: 'rgba(255,255,255,0.1)'
                      }
                  }
              },
              plugins: {
                  legend: {
                      labels: {
                          color: "#fff",
                          font: { size: 14 }
                      }
                  },
                  tooltip: {
                      bodyFont: { size: 14, color: "#fff" }
                  }
              }
          };
          
          chartInstance = new Chart(ctx, {
              type: chartType,
              data: data,
              options: options
          });
      }
      
      // Initial render using the selected chart type.
      renderChart(document.getElementById('chartTypeSelector').value);
      
      // Re-render the chart when the chart type selection changes.
      document.getElementById('chartTypeSelector').addEventListener('change', function() {
          renderChart(this.value);
      });
    <?php endif; ?>
  </script>
</body>
</html>
