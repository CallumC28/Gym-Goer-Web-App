<?php
session_start();
// Check if the user is logged in
if (!isset($_SESSION['loggedin'])) {
    header('Location: index.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Fitness Tracker | BMR Calculator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet"/>
    <style>
        .glassmorphism {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 20px;
        }

        .fade-in {
            animation: fadeIn 0.8s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .scale-hover:hover {
            transform: scale(1.05);
            transition: transform 0.3s ease;
        }

        /* Navbar Styling */
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

    <div class="max-w-3xl mx-auto mt-20 fade-in">
        <h2 class="text-3xl font-bold text-center text-blue-500">BMR Calculator</h2>
        <p class="text-center mt-2">Welcome, <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES) ?>! Calculate how many calories you burn daily at rest.</p>

        <!-- BMR Calculator -->
        <div class="glassmorphism mt-6 p-6">
            <form id="calc-form" class="space-y-6">
                <!-- Formula Selection -->
                <div>
                    <label class="block text-lg font-bold">Choose BMR Formula:</label>
                    <select id="formula" class="w-full p-2 bg-gray-700 text-blue-400 border border-blue-500 rounded-md">
                        <option value="Mifflin-St Jeor">Mifflin-St Jeor</option>
                        <option value="Revised Harris-Benedict">Revised Harris-Benedict</option>
                        <option value="Katch-McArdle">Katch-McArdle</option>
                    </select>
                </div>

                <!-- Age & Gender -->
                <div class="flex space-x-4">
                    <div class="w-full">
                        <label class="block text-lg font-bold">Age:</label>
                        <input type="number" id="age" class="w-full p-2 bg-gray-700 text-blue-400 border border-blue-500 rounded-md" required>
                    </div>
                    <div class="w-full">
                        <label class="block text-lg font-bold">Gender:</label>
                        <select id="gender" class="w-full p-2 bg-gray-700 text-blue-400 border border-blue-500 rounded-md">
                            <option value="Female">Female</option>
                            <option value="Male">Male</option>
                        </select>
                    </div>
                </div>

                <!-- Height -->
                <div>
                    <label class="block text-lg font-bold">Height:</label>
                    <div class="flex space-x-4">
                        <input type="number" id="height-ft" placeholder="Feet" class="w-1/3 p-2 bg-gray-700 text-blue-400 border border-blue-500 rounded-md">
                        <input type="number" id="height-in" placeholder="Inches" class="w-1/3 p-2 bg-gray-700 text-blue-400 border border-blue-500 rounded-md">
                        <input type="number" id="height-cm" placeholder="Centimeters" class="w-1/3 p-2 bg-gray-700 text-blue-400 border border-blue-500 rounded-md">
                    </div>
                </div>

                <!-- Weight -->
                <div>
                    <label class="block text-lg font-bold">Weight:</label>
                    <div class="flex space-x-4">
                        <input type="number" id="weight" class="w-full p-2 bg-gray-700 text-blue-400 border border-blue-500 rounded-md">
                        <select id="weight-unit" class="p-2 bg-gray-700 text-blue-400 border border-blue-500 rounded-md">
                            <option value="lb">lb</option>
                            <option value="kg">kg</option>
                        </select>
                    </div>
                </div>

                <!-- Body Fat (for Katch-McArdle) -->
                <div id="body-fat-field" class="hidden">
                    <label class="block text-lg font-bold">Body Fat Percentage:</label>
                    <input type="number" id="body-fat" class="w-full p-2 bg-gray-700 text-blue-400 border border-blue-500 rounded-md">
                </div>

                <!-- Calculate Button -->
                <button type="submit" class="w-full bg-blue-500 text-white p-3 rounded-md hover:bg-blue-400 transition duration-300">Calculate</button>
            </form>

            <!-- Results Section -->
            <div id="results" class="mt-6 p-4 bg-gray-800 text-white rounded-lg hidden">
                <p>Your estimated BMR is <span id="bmr-calories" class="text-blue-500"></span> calories/day.</p>
            </div>
        </div>
    </div>

    <script>

        document.getElementById('formula').addEventListener('change', function() {
            document.getElementById('body-fat-field').classList.toggle('hidden', this.value !== 'Katch-McArdle');
        });

        document.getElementById('calc-form').addEventListener('submit', function(event) {
            event.preventDefault();

            let age = +document.getElementById('age').value;
            let gender = document.getElementById('gender').value;
            let height = (+document.getElementById('height-ft').value * 30.48) + (+document.getElementById('height-in').value * 2.54) || +document.getElementById('height-cm').value;
            let weight = document.getElementById('weight-unit').value === 'lb' ? +document.getElementById('weight').value * 0.453592 : +document.getElementById('weight').value;
            let formula = document.getElementById('formula').value;
            let BMR;

            BMR = formula === 'Mifflin-St Jeor' ? (10 * weight) + (6.25 * height) - (5 * age) + (gender === 'Male' ? 5 : -161) :
                  formula === 'Revised Harris-Benedict' ? (13.397 * weight) + (4.799 * height) - (5.677 * age) + (gender === 'Male' ? 88.362 : 447.593) :
                  370 + (21.6 * weight * (1 - (+document.getElementById('body-fat').value / 100)));

            document.getElementById('bmr-calories').textContent = BMR.toFixed(2);
            document.getElementById('results').classList.remove('hidden');
        });
    </script>

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
