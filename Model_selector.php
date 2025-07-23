<?php
session_start();
if (!isset($_SESSION['loggedin'])) {
    header('Location: index.html');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fitness Tracker | Hub</title>
  <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
  <style>
    /* Page Animations */
    @keyframes fadeSlide {
      0% { opacity: 0; transform: translateY(-10px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-slide { animation: fadeSlide 0.6s ease-out forwards; }

    /* Glassmorphism Style */
    .glassmorphism {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(12px);
      border-radius: 15px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .glassmorphism:hover {
      transform: scale(1.05);
      box-shadow: 0 10px 20px rgba(96, 165, 250, 0.5);
    }

    /* Title Styling */
    .title-glow {
      font-size: 2.5rem;
      font-weight: bold;
      color: #60A5FA;
      text-align: center;
      text-shadow: 0px 0px 10px rgba(96, 165, 250, 0.8);
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

    .AI-heading {
      background: linear-gradient(90deg, #60a5fa, #3b82f6);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      animation: bounce 2s infinite;
    }

  </style>
</head>
<body class="bg-gray-900 text-white font-poppins">

 <nav class="bg-gray-800 bg-opacity-90 backdrop-blur-md shadow-lg fixed w-full top-0 z-50 opacity-0 animate-fade-slide">
    <div class="max-w-6xl mx-auto px-4 flex justify-between items-center py-4">
      <a href="Homepage.php" class="flex items-center space-x-2" aria-label="Fitness Tracker Home">
        <img src="assets/Logo_Fitness.png" alt="Fitness Tracker Logo" class="h-8">
        <span class="hidden sm:block text-white text-lg font-bold tracking-wide">Fitness Tracker</span>
      </a>
      <div class="hidden md:flex space-x-8">
        <a href="profile.php" class="nav-link" aria-label="View Profile"><i class="ri-user-line text-lg"></i> Profile</a>
        <a href="Homepage.php" class="nav-link" aria-label="Homepage"><i class="ri-home-line text-lg"></i> Home</a>
        <a href="logout.php" class="nav-link text-red-500 hover:text-red-600" aria-label="Logout"><i class="ri-logout-box-line text-lg"></i> Logout</a>
      </div>
      <button id="menu-toggle" class="md:hidden text-white focus:outline-none" aria-label="Toggle Navigation Menu">
        <i class="ri-menu-3-line text-2xl"></i>
      </button>
      <div id="mobile-menu" class="absolute top-16 right-4 bg-gray-800 bg-opacity-95 backdrop-blur-md rounded-lg w-48 shadow-xl hidden">
        <a href="profile.php" class="mobile-nav-link" aria-label="Profile"><i class="ri-user-line text-lg"></i> Profile</a>
        <a href="Homepage.php" class="mobile-nav-link" aria-label="Home"><i class="ri-home-line text-lg"></i> Home</a>
        <a href="logout.php" class="mobile-nav-link text-red-500 hover:text-red-600" aria-label="Logout"><i class="ri-logout-box-line text-lg"></i> Logout</a>
      </div>
    </div>
  </nav>
  
    <!-- Welcome Section -->
    <main class="max-w-6xl mx-auto px-6 mt-24 opacity-0 animate-fade-slide flex flex-col items-center justify-center text-center">
    <div class="text-center">
      <h1 class="AI-heading text-5xl font-bold drop-shadow-lg">
        <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES) ?>
      </h1>
      <p class="text-lg text-gray-400 mt-4">
        Choose a model for your Prediction to be based off
      </p>
    </div>
  
  <div class="grid grid-cols-1 md:grid-cols-2 gap-10 w-full mt-10">
    
    <!-- Predictions Tile -->
    <a href="predictions.php" class="glassmorphism p-10 flex flex-col items-center">
      <i class="ri-line-chart-line text-6xl text-blue-400 mb-4"></i>
      <h2 class="text-2xl font-semibold">Linear Regression</h2>
      <p class="text-gray-300 mt-2">Smooths your last few workouts with a 3-day moving average, then fits simple linear trends to your weight, reps, and sets (with realistic daily-gain caps and more logs for a exercise the more accurate it becomes)</p>
    </a>

    <a href="test.php" class="glassmorphism p-10 flex flex-col items-center">
      <i class="ri-flask-line text-6xl text-green-400 mb-4"></i>
      <h2 class="text-2xl font-semibold">Machine Learning Model</h2>
      <p class="text-gray-300 mt-2">Full-featured predictor retrains a nine-factor model on the fly—looking at when you trained, how much you lifted (weight, reps & sets), total workout volume, frequency, consistency and recent volume trends—then blends both short- and long-term trends under realistic caps.</p>
    </a>

    <a href="short_term_prediction.php" class="glassmorphism p-10 flex flex-col items-center">
      <i class="ri-flask-line text-6xl text-green-400 mb-4"></i>
      <h2 class="text-2xl font-semibold">Machine Learning Model - Short Term Predictions (up to 2 months)</h2>
      <p class="text-gray-300 mt-2">Trains a two-feature SVR (days since first log & workout volume = weight×reps×sets) on your history each time you click Predict.</p>
    </a>

    <a href="long_term_prediction.php" class="glassmorphism p-10 flex flex-col items-center">
      <i class="ri-flask-line text-6xl text-green-400 mb-4"></i>
      <h2 class="text-2xl font-semibold">Machine Learning Model -Long Term Predictions (6 months to 1 year)</h2>
      <h3 class=" font-semibold text-red-400">Better used when you have logged upwards of 6 months worth of data for a exercise</h3>
      <p class="text-gray-300 mt-2">The long-term predictor re-trains on your entire history—fitting a gently tapered trend line over recent lifts and a data-driven model on date & total workout volume (weight × reps × sets)—then blends them for a reliable 6–12 month forecast.</p>
    </a>

  </div>

</main>

<script>
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
</script>

</body>
</html>
