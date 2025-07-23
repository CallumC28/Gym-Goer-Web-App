<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Tracker | Log Weight</title>
    <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .glass {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .btn-animate:hover {
            transform: scale(1.05);
            transition: transform 0.2s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in {
            animation: fadeIn 0.8s ease-out;
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

    <main class="max-w-4xl mx-auto p-6 mt-24 fade-in">
        <div class="glass p-8 rounded-lg shadow-lg">
            <h1 class="text-3xl font-bold mb-6 text-center text-blue-400">Log Your Body Weight</h1>

            <!-- Success & Error Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div id="success-message" class="bg-green-600 text-white p-3 rounded-lg mb-4 text-center fade-in">
                    ✅ Weight logged successfully!
                </div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="bg-red-600 text-white p-3 rounded-lg mb-4 text-center fade-in">
                    ❌ Something went wrong. Try again.
                </div>
            <?php endif; ?>

            <form action="log_weight.php" method="POST" class="space-y-6">
                
                <!-- Weight Input -->
                <div>
                    <label for="weight" class="block font-medium text-gray-300">Weight (kg)</label>
                    <input 
                        type="number" step="0.01" id="weight" name="weight"
                        class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 text-white"
                        required oninput="updateWeightPreview()"/>
                    <p id="weight-preview" class="mt-2 text-blue-400 text-lg font-semibold"></p>
                </div>

                <!-- Date Input -->
                <div>
                    <label for="log_date" class="block font-medium text-gray-300">Log Date</label>
                    <input 
                        type="date" id="log_date" name="log_date"
                        class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 text-white"
                        required />
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full py-3 bg-blue-500 text-white rounded-lg font-semibold btn-animate">
                    Submit
                </button>
            </form>
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
        function updateWeightPreview() {
            const weightInput = document.getElementById("weight").value;
            const weightPreview = document.getElementById("weight-preview");
            weightPreview.textContent = weightInput ? `You're logging: ${weightInput} kg` : "";
        }

        // Set default date to today
        document.getElementById("log_date").valueAsDate = new Date();

        // Auto-hide success message after 3 seconds
        setTimeout(() => {
            const successMessage = document.getElementById('success-message');
            if (successMessage) {
                successMessage.style.display = 'none';
            }
        }, 3000);
    </script>

</body>
</html>
