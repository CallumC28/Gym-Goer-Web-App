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
    <title>Fitness Tracker | Workout Videos</title>
    <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
    <style>
        body { background: linear-gradient(120deg, #1e293b, #0f172a); transition: background 0.5s ease-in-out; }
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            transition: transform 0.3s ease-in-out;
        }
        .glass-card:hover { transform: scale(1.05); }
        .fade-in { animation: fadeIn 0.6s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        /* 🔹 Navbar Styling */
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
    <main class="max-w-6xl mx-auto px-4 mt-24 fade-in">
        <div class="text-center">
            <h2 class="text-3xl font-bold text-blue-400">Helpful Gym & Exercise Videos</h2>
            <p class="mt-2 text-gray-400">Find inspiration and guidance to improve your workouts!</p>
        </div>
        <div class="mt-6 flex justify-center">
            <input type="text" id="video-search" class="w-full md:w-1/2 p-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:ring-2 focus:ring-blue-500" placeholder="Search for an exercise...">
        </div>
        <!-- Video Grid -->
        <div id="video-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-8">
            <div class="glass-card transition transform hover:scale-105">
                <a href="https://www.youtube.com/watch?v=34LJX-arUo8" target="_blank">
                    <img src="https://img.youtube.com/vi/34LJX-arUo8/0.jpg" alt="Best Exercises for Strength" class="w-full rounded-t-lg">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-blue-400">Best Exercises for Building Strength</h3>
                        <p class="text-gray-400 mt-2">Learn the fundamentals of strength training.</p>
                    </div>
                </a>
            </div>
            <div class="glass-card transition transform hover:scale-105">
                <a href="https://www.youtube.com/shorts/ZaTM37cfiDs" target="_blank">
                    <img src="https://img.youtube.com/vi/ZaTM37cfiDs/0.jpg" alt="Deadlift Tips" class="w-full rounded-t-lg">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-blue-400">How to Deadlift Safely</h3>
                        <p class="text-gray-400 mt-2">Master your form for one of the best compound lifts.</p>
                    </div>
                </a>
            </div>
            <div class="glass-card transition transform hover:scale-105">
                <a href="https://www.youtube.com/watch?v=7ExaC_KjYOw" target="_blank">
                    <img src="https://img.youtube.com/vi/7ExaC_KjYOw/0.jpg" alt="Top 5 Mobility Drills" class="w-full rounded-t-lg">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-blue-400">Top 5 Mobility Drills</h3>
                        <p class="text-gray-400 mt-2">Improve your flexibility and prevent injuries.</p>
                    </div>
                </a>
            </div>
            <div class="glass-card transition transform hover:scale-105">
                <a href="https://www.youtube.com/watch?v=eO0U9U7uXGY" target="_blank">
                    <img src="https://img.youtube.com/vi/eO0U9U7uXGY/0.jpg" alt="Best Chest Workouts" class="w-full rounded-t-lg">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-blue-400">Best Chest Workouts for Strength</h3>
                        <p class="text-gray-400 mt-2">Sculpt your chest with these expert techniques.</p>
                    </div>
                </a>
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
        document.getElementById('video-search').addEventListener('input', function() {
            let filter = this.value.toLowerCase();
            let videos = document.querySelectorAll('.glass-card');

            videos.forEach(video => {
                let title = video.querySelector('h3').innerText.toLowerCase();
                video.style.display = title.includes(filter) ? "block" : "none";
            });
        });
    </script>

</body>
</html>
