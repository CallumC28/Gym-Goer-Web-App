<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Tracker | Weather</title>
    <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert/dist/sweetalert.min.js"></script>
    <style>
        body {
            background: linear-gradient(120deg, #1e293b, #0f172a);
            transition: background 0.5s ease-in-out;
            font-family: 'Poppins', sans-serif;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            transition: transform 0.3s ease-in-out;
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
    <!--Weather Dashboard -->
    <main class="max-w-6xl mx-auto px-4 mt-24 fade-in">
        <div class="glass-card shadow-lg p-8 text-center">
            <h1 class="text-3xl font-bold text-blue-400">Weather Information</h1>
            <div id="weather-app" class="space-y-6 mt-6">
                <div class="flex flex-col md:flex-row justify-center items-center gap-4">
                    <input type="text" id="input-box" class="w-full md:w-2/3 p-3 bg-gray-900 border border-gray-700 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 text-white" placeholder="Enter city name">
                    <button id="submit-btn" class="w-full md:w-auto px-6 py-3 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 focus:ring-2 focus:ring-blue-400">
                        Get Weather
                    </button>
                </div>
                <div id="weather-body" class="bg-gray-900 p-4 rounded-lg shadow-md text-gray-300 text-center hidden">
                </div>
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
        const weatherApi = { key: '8c89544469c978d8f05b1ff75ae9b36c', baseUrl: 'https://api.openweathermap.org/data/2.5/weather' };
        
        document.getElementById('submit-btn').addEventListener('click', () => {
            const city = document.getElementById('input-box').value.trim();
            if (city) getWeather(city);
            else swal("Error", "Please enter a city name", "error");
        });

        document.getElementById('input-box').addEventListener('keypress', (event) => {
            if (event.key === "Enter") getWeather(event.target.value);
        });

        function getWeather(city) {
            fetch(`${weatherApi.baseUrl}?q=${city}&appid=${weatherApi.key}&units=metric`)
                .then(response => response.json())
                .then(displayWeather)
                .catch(() => swal("Error", "City not found", "warning"));
        }

        function displayWeather(weather) {
            if (weather.cod !== 200) {
                swal("Error", "Invalid city name", "error");
                return;
            }

            const weatherBox = document.getElementById('weather-body');
            weatherBox.classList.remove('hidden');
            weatherBox.innerHTML = `
                <h2 class="text-2xl font-semibold">${weather.name}, ${weather.sys.country}</h2>
                <p class="text-lg">${getDate()}</p>
                <div class="mt-4 flex items-center justify-center space-x-4">
                    <i class="${getWeatherIcon(weather.weather[0].main)} text-5xl text-blue-400"></i>
                    <p class="text-4xl font-bold">${Math.round(weather.main.temp)}°C</p>
                </div>
                <p class="text-lg mt-2">${weather.weather[0].description.toUpperCase()}</p>
                <hr class="my-4 border-gray-600">
                <div class="grid grid-cols-2 gap-4">
                    <div><span class="font-semibold">Min:</span> ${Math.floor(weather.main.temp_min)}°C</div>
                    <div><span class="font-semibold">Max:</span> ${Math.ceil(weather.main.temp_max)}°C</div>
                    <div><span class="font-semibold">Humidity:</span> ${weather.main.humidity}%</div>
                    <div><span class="font-semibold">Wind Speed:</span> ${weather.wind.speed} km/h</div>
                </div>
            `;

            changeBackground(weather.weather[0].main);
        }

        function getDate() {
            const date = new Date();
            return `${date.getDate()} ${date.toLocaleString('default', { month: 'long' })}, ${date.getFullYear()}`;
        }

        function getWeatherIcon(condition) {
            const icons = {
                "Clear": "fas fa-sun",
                "Clouds": "fas fa-cloud",
                "Rain": "fas fa-cloud-showers-heavy",
                "Snow": "fas fa-snowflake",
                "Thunderstorm": "fas fa-bolt",
                "Drizzle": "fas fa-cloud-rain",
                "Fog": "fas fa-smog",
                "Mist": "fas fa-smog"
            };
            return icons[condition] || "fas fa-cloud-sun";
        }

        function changeBackground(condition) {
            const backgrounds = {
                "Clear": "linear-gradient(120deg, #ffcc70, #f5b461)",
                "Clouds": "linear-gradient(120deg, #6366f1, #374151)",
                "Rain": "linear-gradient(120deg, #4f46e5, #1e293b)",
                "Snow": "linear-gradient(120deg, #a1c4fd, #c2e9fb)",
                "Thunderstorm": "linear-gradient(120deg, #1a1a2e, #16213e)",
                "Drizzle": "linear-gradient(120deg, #6b7280, #374151)",
                "Mist": "linear-gradient(120deg, #3d4e57, #2c3e50)"
            };
            document.body.style.background = backgrounds[condition] || "linear-gradient(120deg, #1e293b, #0f172a)";
        }
    </script>
</body>
</html>
