<?php
session_start();
require 'vendor/autoload.php';

use Phpml\Regression\SVR;
use Phpml\SupportVectorMachine\Kernel;
use Phpml\Preprocessing\Imputer;
use Phpml\Preprocessing\Imputer\Strategy\MeanStrategy;

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
    $custom_time_frame = filter_var($_POST['custom_time_frame'] ?? 7, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?: 7;

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

// Main prediction function
function predictWeight($data, $days_ahead, $common_sets = 4, $common_reps = 12) {
  if (count($data) < 5) {
      return ['error' => 'Need at least 5 data points for prediction'];
  }

  // Extract arrays from logs
  $dates = array_map(function($entry) { return strtotime($entry['log_date']); }, $data);
  $weights = array_column($data, 'weight');
  $reps = array_column($data, 'reps');
  $sets = array_column($data, 'sets');

  $last_weight = end($weights);
  $last_reps = end($reps) ?: $common_reps;
  $last_sets = end($sets) ?: $common_sets;
  $first_date = min($dates);

  // Compute elapsed days (raw feature)
  $x = array_map(function($date) use ($first_date) { 
      return ($date - $first_date) / (60 * 60 * 24);
  }, $dates);

  // Normalise the time feature to 0–1
  $max_x = max($x);
  $normalised_x = array_map(function($val) use ($max_x) {
      return $max_x ? $val / $max_x : 0;
  }, $x);

  // Calculate user-specific progression rate
  $daily_rates = [];
  $volume_rates = [];
  for ($i = 1; $i < count($weights); $i++) {
      $days_diff = ($x[$i] - $x[$i-1]);
      if ($days_diff > 0) {
          $weight_diff = $weights[$i] - $weights[$i-1];
          $volume = $weights[$i] * $reps[$i] * $sets[$i];
          $prev_volume = $weights[$i-1] * $reps[$i-1] * $sets[$i-1];
          $daily_rates[] = $weight_diff / $days_diff;
          $volume_rates[] = ($volume - $prev_volume) / $days_diff;
      }
  }

  // Calculate user consistency (standard deviation of workout intervals)
  $intervals = [];
  for ($i = 1; $i < count($x); $i++) {
      $intervals[] = $x[$i] - $x[$i-1];
  }
  $avg_interval = array_sum($intervals) / count($intervals);
  $interval_variance = array_sum(array_map(function($interval) use ($avg_interval) {
      return pow($interval - $avg_interval, 2);
  }, $intervals)) / count($intervals);
  // Adjusted consistency factor to be more forgiving and range between 0-100%
  $consistency_factor = max(0, min(1, 1 - (sqrt($interval_variance) / ($avg_interval + 1)))) * 100;

  // Filter rates for stability
  $filtered_rates = array_values(array_filter($daily_rates, function($r) { 
      return $r > -0.05 && $r < 0.05; // Reasonable bounds for daily change
  }));
  $filtered_volume_rates = array_values(array_filter($volume_rates, function($r) {
      return abs($r) < 1000; // Reasonable bounds for volume change
  }));

  // Compute user-specific progression rate with weighted recent performance
  if (!empty($filtered_rates)) {
      $mean_rate = array_sum($filtered_rates) / count($filtered_rates);
      $std_dev = sqrt(array_sum(array_map(function($r) use ($mean_rate) { 
          return pow($r - $mean_rate, 2); 
      }, $filtered_rates)) / count($filtered_rates));

      // Apply exponential weighting to recent data
      $weights_for_avg = array_map(function($i) { 
          return exp(0.3 * $i);
      }, range(0, count($filtered_rates) - 1));
      $weighted_sum = 0;
      $weight_sum = array_sum($weights_for_avg);
      foreach (array_keys($filtered_rates) as $i) {
          $weighted_sum += $filtered_rates[$i] * $weights_for_avg[$i];
      }
      $avg_daily_rate = $weight_sum ? $weighted_sum / $weight_sum : $mean_rate;
  } else {
      $total_days = end($x) - $x[0];
      $total_progress = end($weights) - $weights[0];
      $avg_daily_rate = ($total_days > 0) ? $total_progress / $total_days : 0;
  }

  // Adjust rate based on volume progression
  $volume_adjustment = 1;
  if (!empty($filtered_volume_rates)) {
      $mean_volume_rate = array_sum($filtered_volume_rates) / count($filtered_volume_rates);
      $volume_adjustment = max(0.5, min(2, 1 + ($mean_volume_rate / max($last_weight * $last_reps * $last_sets, 1))));
  }

  // Compute Exponential Moving Average (EMA) with adaptive alpha
  $dynamic_rate = $avg_daily_rate;
  if (!empty($filtered_rates)) {
      $rates_array = array_values($filtered_rates);
      $ema_rate = $rates_array[0];
      $alpha_ema = 0.3 + (0.4 * ($consistency_factor / 100));
      foreach (array_slice($rates_array, 1) as $r) {
          $ema_rate = $alpha_ema * $r + (1 - $alpha_ema) * $ema_rate;
      }
      $dynamic_rate = (0.3 * $avg_daily_rate) + (0.7 * $ema_rate);
  }

  // Adjust rate based on consistency
  $dynamic_rate *= ($consistency_factor / 100) * $volume_adjustment;

  // Apply physiological constraints based on experience
  $num_points = count($data);
  $experience_factor = min(1, $num_points / 15);
  $max_daily_improvement = $last_weight * (0.002 * (1 + $experience_factor));
  $min_daily_improvement = -$last_weight * 0.0005;
  $dynamic_rate = min(max($dynamic_rate, $min_daily_improvement), $max_daily_improvement);

  // Experience-based adjustment
  $dynamic_rate *= (0.5 + (0.5 * $experience_factor));

  // Compute linear regression trend
  $n = count($x);
  $sum_x = array_sum($x);
  $sum_y = array_sum($weights);
  $sum_xy = $sum_xx = 0;
  for ($i = 0; $i < $n; $i++) {
      $sum_xy += $x[$i] * $weights[$i];
      $sum_xx += $x[$i] * $x[$i];
  }
  $denom = ($n * $sum_xx - $sum_x * $sum_x);
  $trend_slope = ($denom != 0) ? ($n * $sum_xy - $sum_x * $sum_y) / $denom : 0;
  $trend_slope = min(max($trend_slope, $min_daily_improvement), $max_daily_improvement);
  $mean_x = $sum_x / $n;
  $mean_y = $sum_y / $n;
  $intercept = $mean_y - $trend_slope * $mean_x;

  // Enhanced Feature Engineering for SVR
  $workout_frequency = count($x) / (max($x) - min($x) + 1);
  $intensity = array_map(function($r, $s) { return ($r ?: 1) * ($s ?: 1); }, $reps, $sets);
  $avg_intensity = array_sum($intensity) / count($intensity);
  $consistency = sqrt($interval_variance);
  $volumes = array_map(function($w, $r, $s) { return $w * ($r ?: 1) * ($s ?: 1); }, $weights, $reps, $sets);
  $volume_trend = array_slice($volumes, -5);
  $volume_slope = count($volume_trend) > 1 ? (end($volume_trend) - $volume_trend[0]) / count($volume_trend) : 0;

  // Normalise features
  $max_weight = max($weights);
  $min_weight = min($weights);
  $normalised_weights = array_map(function($w) use ($min_weight, $max_weight) {
      return ($max_weight - $min_weight) ? ($w - $min_weight) / ($max_weight - $min_weight) : 0;
  }, $weights);

  $max_volume = max($volumes);
  $min_volume = min($volumes);
  $normalised_volumes = array_map(function($v) use ($min_volume, $max_volume) {
      return ($max_volume - $min_volume) ? ($v - $min_volume) / ($max_volume - $min_volume) : 0;
  }, $volumes);

  // Prepare samples for SVR
  $samples = array_map(function($x_val, $weight, $rep, $set, $vol, $freq, $int, $cons, $vol_slope) {
      return [$x_val, $weight, $rep ?: 0, $set ?: 0, $vol, $freq, $int, $cons, $vol_slope];
  }, $normalised_x, $normalised_weights, $reps, $sets, $normalised_volumes, 
  array_fill(0, count($x), $workout_frequency), $intensity, array_fill(0, count($x), $consistency), 
  array_fill(0, count($x), $volume_slope));
  $targets = $normalised_weights;

  // Train SVR Model with Grid Search
  $best_svr = null;
  $best_error = PHP_FLOAT_MAX;
  $epsilons = [0.01, 0.05, 0.1];
  $costs = [3.0, 5.0, 10.0];
  foreach ($epsilons as $epsilon) {
      foreach ($costs as $cost) {
          $regression = new SVR(Kernel::RBF, 3, $epsilon, $cost);
          $imputer = new Imputer(null, new MeanStrategy());
          $imputer->fit($samples);
          $imputer->transform($samples);
          try {
              $regression->train($samples, $targets);
              $preds = [];
              foreach ($samples as $sample) {
                  $preds[] = $regression->predict($sample);
              }
              $errors = array_map(function($pred, $actual) { return pow($pred - $actual, 2); }, $preds, $targets);
              $mse = array_sum($errors) / count($errors);
              if ($mse < $best_error) {
                  $best_error = $mse;
                  $best_svr = $regression;
              }
          } catch (Exception $e) {
              continue;
          }
      }
  }
  if (!$best_svr) {
      $best_svr = new SVR(Kernel::RBF, 3, 0.05, 5.0);
      $imputer = new Imputer(null, new MeanStrategy());
      $imputer->fit($samples);
      $imputer->transform($samples);
      $best_svr->train($samples, $targets);
  }

  try {
      // Extrapolate using the raw time feature
      $last_raw_x = end($x);
      $future_raw_x = $last_raw_x + $days_ahead;

      // Short-term prediction using dynamic rate
      $short_term_prediction = $last_weight + ($dynamic_rate * $days_ahead);
      
      // Long-term prediction using regression
      $long_term_prediction = $intercept + $trend_slope * $future_raw_x;
      
      // SVR-based prediction
      $future_sample = [
          $normalised_x[count($normalised_x)-1],
          end($normalised_weights),
          $last_reps,
          $last_sets,
          end($normalised_volumes),
          $workout_frequency,
          $intensity[count($intensity)-1],
          $consistency,
          $volume_slope
      ];
      $samples_for_transform = [$future_sample];
      $imputer->fit($samples_for_transform);
      $transformed_samples = $samples_for_transform;
      $imputer->transform($transformed_samples);
      $svr_normalised_prediction = $best_svr->predict($transformed_samples[0]);
      $svr_prediction = $min_weight + ($svr_normalised_prediction * ($max_weight - $min_weight));
      
      // Blend predictions
      $sigmoid_alpha = 1 / (1 + exp(-($days_ahead - 60) / 10));
      $linear_blend = (1 - $sigmoid_alpha) * $short_term_prediction + $sigmoid_alpha * $long_term_prediction;
      $svr_weight = min(0.3, 0.01 * $num_points);
      $prediction = (1 - $svr_weight) * $linear_blend + $svr_weight * $svr_prediction;
      
      // Apply physiological constraints
      $max_possible_gain = $last_weight * (1 + (0.002 * $days_ahead));
      $min_possible_weight = $last_weight * (1 - (0.0005 * $days_ahead));
      $constrained_prediction = min(max($prediction, $min_possible_weight), $max_possible_gain);

      // Compute confidence interval with increased uncertainty for long-term
      $preds = [];
      foreach ($samples as $sample) {
          $preds[] = $best_svr->predict($sample);
      }
      $errors = array_map(function($pred, $actual) { return pow($pred - $actual, 2); }, $preds, $targets);
      $mse = array_sum($errors) / count($errors);
      $multiplier = 3.5; // Increase from 2.576 to 3.5
      $extra_uncertainty = 1.3; // Extra uncertainty factor
      $base_confidence = $multiplier * sqrt($mse) * ($max_weight - $min_weight);
      $confidence = $base_confidence * (1 + sqrt($days_ahead / 30)) * $extra_uncertainty;
      
      // Predict sets and reps progression with more dynamic adjustment
      $predicted_sets = $last_sets;
      $predicted_reps = $last_reps;
      if ($constrained_prediction > $last_weight) {
          $progress_days = $days_ahead / 30;
          $set_progression = min(ceil($progress_days * 0.5), 3); // Allow up to 3 additional sets
          $rep_progression = min(ceil($progress_days * 1.5), 5); // Allow up to 5 additional reps
          $predicted_sets = min($common_sets + 2, $last_sets + $set_progression);
          $predicted_reps = min($common_reps + 5, $last_reps + $rep_progression);
      }

      // Recalculate avg_daily_rate based on final prediction
      $final_daily_rate = $days_ahead ? ($constrained_prediction - $last_weight) / $days_ahead : 0;

      return [
          'weight' => round($constrained_prediction, 2),
          'confidence' => round($confidence, 2),
          'days_ahead' => $days_ahead,
          'avg_daily_rate' => round($final_daily_rate, 4),
          'predicted_sets' => $predicted_sets,
          'predicted_reps' => $predicted_reps,
          'trend_slope' => $trend_slope,
          'intercept' => $intercept,
          'first_date' => $first_date,
          'consistency_factor' => round($consistency_factor),
          'volume_adjustment' => round($volume_adjustment, 2)
      ];
  } catch (Exception $e) {
      return ['error' => 'Prediction failed: ' . $e->getMessage()];
  }
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
    .timeline-container {
      position: relative;
      padding: 20px 0;
      margin: 20px auto;
      max-width: 800px;
    }
    .timeline-line {
      position: absolute;
      top: 50%;
      left: 5%;
      right: 5%;
      height: 2px;
      background: #60a5fa;
      transform: translateY(-50%);
    }
    .timeline-item {
      position: relative;
      text-align: center;
      margin-bottom: 20px;
      flex: 1;
    }
    .timeline-item .dot {
      width: 12px;
      height: 12px;
      background: #60a5fa;
      border-radius: 50%;
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
    }
    .timeline-item .label {
      background: #374151;
      padding: 5px 10px;
      border-radius: 5px;
      margin-top: 30px;
      display: inline-block;
      white-space: nowrap;
    }
    .milestone-card {
      background: #374151;
      padding: 15px;
      border-radius: 8px;
      margin: 10px 0;
      text-align: center;
    }
    .gauge-container {
      width: 200px;
      height: 200px;
      margin: 20px auto;
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

  <!-- Main Content -->
  <main class="max-w-6xl mx-auto px-4 mt-24 animate-fade-slide">
    <div class="bg-gray-800 rounded-lg p-6 shadow-lg">
      <div class="text-center mb-6">
        <h2 class="text-3xl font-bold text-blue-500">Exercise Weight Prediction</h2>
        <p class="text-gray-400">Predict your future performance based on your personal progression rate</p>
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
          <label for="custom_time_frame" class="block text-lg">Prediction Time Frame (Days):</label>
          <input type="number" name="custom_time_frame" id="custom_time_frame" class="mt-2 p-2 rounded bg-gray-700 text-white w-full" min="1" value="<?= $custom_time_frame ?: 7 ?>" required />
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
            <p class="mt-1 text-blue-500 text-lg">
                <?= $prediction_result['weight'] ?> kg
                <br>
                <small class="text-sm text-gray-400">(99% CI: ±<?= $prediction_result['confidence'] ?> kg)</small>
            </p>
            <p class="mt-2 text-gray-400">Based on your last weight: <?= round($most_recent_weight, 2) ?> kg</p>
            <p class="text-sm text-gray-500">Avg. daily improvement rate: <?= $prediction_result['avg_daily_rate'] ?> kg/day</p>
            <p class="text-sm text-gray-400">Predicted Sets/Reps: <?= $prediction_result['predicted_sets'] ?> sets of <?= $prediction_result['predicted_reps'] ?> reps</p>
            <p class="text-sm text-gray-400">Workout Consistency: <?= $prediction_result['consistency_factor'] ?>%</p>
            <p class="text-sm text-gray-400">Volume Adjustment Factor: x<?= $prediction_result['volume_adjustment'] ?></p>
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

            <!-- Progress Timeline -->
            <div class="mt-8">
              <h3 class="text-xl font-semibold text-center mb-4">Progress Timeline</h3>
              <div class="timeline-container">
                <div class="timeline-line"></div>
                <div class="flex justify-between">
                  <div class="timeline-item">
                    <div class="dot"></div>
                    <div class="label">Start: <?= round($exercise_data[0]['weight'], 2) ?> kg</div>
                  </div>
                  <div class="timeline-item">
                    <div class="dot"></div>
                    <div class="label">Recent: <?= round($most_recent_weight, 2) ?> kg</div>
                  </div>
                  <div class="timeline-item">
                    <div class="dot"></div>
                    <div class="label">Predicted: <?= $prediction_result['weight'] ?> kg</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Milestone Breakdown -->
            <div class="mt-8">
              <h3 class="text-xl font-semibold text-center mb-4">Milestone Breakdown</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php
                  $milestone_intervals = [30, 60, 90];
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

            <!-- Comparison Gauge -->
            <div class="mt-8">
              <h3 class="text-xl font-semibold text-center mb-4">Progress Gauge</h3>
              <div class="gauge-container">
                <canvas id="progressGauge"></canvas>
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

    // Utility: Parse a date string "dd/mm/yyyy" into a Date object
    function parseDateFromLabel(label) {
      const parts = label.split('/');
      return new Date(parts[2], parts[1] - 1, parts[0]);
    }

    let chartInstance = null;
    let gaugeInstance = null;

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
      
      // Calculate regression trend line for each label using parameters from prediction
      const firstDate = new Date(parseInt(prediction.first_date) * 1000);
      const trendLine = labels.map(label => {
        const currentDate = parseDateFromLabel(label);
        const diffDays = (currentDate - firstDate) / (1000 * 60 * 60 * 24);
        return prediction.intercept + prediction.trend_slope * diffDays;
      });
      
      // Future label for prediction point
      const lastDate = new Date(exerciseData[exerciseData.length - 1].log_date);
      lastDate.setDate(lastDate.getDate() + prediction.days_ahead);
      const futureLabel = (() => {
        const day = ("0" + lastDate.getDate()).slice(-2);
        const month = ("0" + (lastDate.getMonth() + 1)).slice(-2);
        const year = lastDate.getFullYear();
        return `${day}/${month}/${year}`;
      })();

      // Add annotations only for line charts
      let annotationOptions = {};
      if (chartType === 'line') {
        annotationOptions = {
          annotations: {
            predictionLabel: {
              type: 'label',
              xValue: labels.length,
              yValue: prediction.weight,
              content: `Predicted: ${prediction.weight} kg`,
              color: '#fff',
              backgroundColor: 'rgba(255, 99, 132, 0.8)',
              position: 'center',
              font: { size: 12 },
              padding: 6
            },
            recentLabel: {
              type: 'label',
              xValue: labels.length - 1,
              yValue: weights[weights.length - 1],
              content: `Recent: ${weights[weights.length - 1]} kg`,
              color: '#fff',
              backgroundColor: 'rgba(75, 192, 192, 0.8)',
              position: 'center',
              font: { size: 12 },
              padding: 6
            }
          }
        };
      }

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
            label: 'Trend Line (Regression)',
            data: trendLine,
            borderColor: 'rgba(255, 206, 86, 1)',
            borderDash: [5, 5],
            fill: false,
            pointRadius: 0,
            spanGaps: true
          },
          {
            label: `Predicted Weight (in ${prediction.days_ahead} days)`,
            data: [...Array(weights.length).fill(null), prediction.weight],
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.5)',
            fill: false,
            pointRadius: 5,
            pointHoverRadius: 7
          },
          {
            label: 'Confidence Interval',
            data: [...Array(weights.length).fill(null), 
              prediction.weight + prediction.confidence,
              prediction.weight - prediction.confidence],
            borderColor: 'rgba(255, 99, 132, 0.2)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            fill: '-1'
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
          },
          annotation: annotationOptions
        },
        scales: {
          y: {
            suggestedMin: Math.min(...weights, prediction.weight - prediction.confidence) * 0.9,
            suggestedMax: Math.max(...weights, prediction.weight + prediction.confidence) * 1.1,
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

    function renderGauge() {
      if (gaugeInstance) gaugeInstance.destroy();
      const ctx = document.getElementById('progressGauge').getContext('2d');
      const historicalMax = Math.max(...exerciseData.map(d => d.weight));
      const gaugeMax = Math.max(historicalMax, prediction.weight) * 1.2;

      const data = {
        datasets: [{
          data: [prediction.weight, gaugeMax - prediction.weight],
          backgroundColor: ['#60a5fa', '#374151'],
          borderWidth: 0,
          circumference: 180,
          rotation: 270
        }]
      };

      const options = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: { display: false },
          tooltip: { enabled: false },
          title: {
            display: true,
            text: `Predicted: ${prediction.weight} kg`,
            color: '#fff',
            font: { size: 14 },
            position: 'bottom'
          }
        }
      };

      gaugeInstance = new Chart(ctx, { type: 'doughnut', data, options });
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
      renderGauge();
    <?php endif; ?>
  </script>
</body>
</html>
