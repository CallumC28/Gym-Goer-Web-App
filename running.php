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

$user_routes = [];
$stmt = $conn->prepare("SELECT * FROM user_routes WHERE user_id=? ORDER BY created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_routes[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Fitness Tracker | Route Planner</title>
  <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet" />
  <style>
    /* Map & Search Bar */
    #map {
      height: 500px;
      width: 100%;
      border-radius: 10px;
    }
    #search-box {
      position: absolute;
      top: 10px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 5;
      width: 320px;
      background-color: rgba(255, 255, 255, 0.95);
      border-radius: 6px;
      padding: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
      font-size: 1rem;
    }
    .pac-container { z-index: 10000 !important; }

    .glass-card {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      padding: 20px;
      transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .glass-card:hover {
      transform: scale(1.03);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    }

    .glass-route {
      background: rgba(96, 165, 250, 0.2); 
      border: 1px solid rgba(96, 165, 250, 0.4);
      border-radius: 12px;
      padding: 16px 20px;
      transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
      text-align: left;
    }
    .glass-route:hover {
      transform: scale(1.03);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    }

    /* Fade-in Effect */
    .fade-in {
      animation: fadeIn 0.8s ease-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
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

    /* Body background gradient */
    body {
      background: linear-gradient(135deg, #1F2937, #111827);
      font-family: 'Poppins', sans-serif;
    }

    /* Modal styling for route summary */
    .modal-bg {
      background: rgba(0, 0, 0, 0.7);
    }
    .modal-content {
      background: #1F2937;
      border-radius: 12px;
      padding: 20px;
      max-width: 500px;
      width: 90%;
    }
  </style>
</head>
<body class="bg-gray-900 text-white">
  <nav class="bg-gray-800 bg-opacity-90 backdrop-blur-md shadow-lg fixed w-full top-0 z-50">
    <div class="max-w-6xl mx-auto px-4 flex justify-between items-center py-4">
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
    <div class="text-center">
      <h2 class="text-3xl sm:text-4xl font-bold text-blue-400">Plan Your Running Route</h2>
      <p class="mt-2 text-gray-400 text-lg">Click on the map to place pins, drag them, and get an optimized route.</p>
    </div>

    <!-- Map Container -->
    <div class="relative mt-8">
      <input id="search-box" class="text-gray-700 text-center" type="text" placeholder="🔍 Search location..." />
      <div id="map"></div>
    </div>

    <!-- Route Actions -->
    <div class="mt-6 flex flex-col sm:flex-row sm:justify-between items-center space-y-4 sm:space-y-0">
      <div class="flex space-x-4">
        <button id="clear-route-btn" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-6 rounded-md transition-colors">
          Clear Route
        </button>
        <button id="route-summary-btn" class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-6 rounded-md transition-colors">
          Route Summary
        </button>
      </div>
      <div class="flex flex-col sm:flex-row items-center space-x-0 sm:space-x-4 text-center">
        <p id="route-details" class="text-gray-400 mb-2 sm:mb-0">Click the map to start plotting your route.</p>
        <button id="toggle-units-btn" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-md transition-colors">
          Switch to Miles
        </button>
      </div>
    </div>

    <!-- Live Route Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mt-8">
      <div class="glass-card text-center">
        <h3 class="text-lg">Total Distance</h3>
        <p id="distance" class="text-2xl font-bold text-blue-400">0 km</p>
      </div>
      <div class="glass-card text-center">
        <h3 class="text-lg">Estimated Time</h3>
        <p id="time" class="text-2xl font-bold text-blue-400">0 mins</p>
      </div>
      <div class="glass-card text-center">
        <h3 class="text-lg">Calories Burned</h3>
        <p id="calories" class="text-2xl font-bold text-blue-400">0 kcal</p>
      </div>
    </div>

    <!-- Weather Button -->
    <div class="flex justify-center mt-8">
      <a href="weather.php" class="glass-card bg-gray-800 p-6 rounded-lg text-center transition transform hover:scale-105">
        <i class="ri-sun-cloudy-line text-4xl text-orange-500"></i>
        <h3 class="text-xl font-semibold mt-4">Weather</h3>
        <p class="text-gray-400 mt-2">Plan outdoor runs/workouts with live updates.</p>
      </a>
    </div>

    <!-- Saved Routes Section -->
    <section class="mt-12">
      <h3 class="text-2xl font-bold text-blue-400 text-center mb-6">Your Saved Routes</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($user_routes)): ?>
          <p class="text-gray-300 col-span-full text-center">No routes saved yet.</p>
        <?php else: ?>
          <?php foreach ($user_routes as $route): ?>
            <?php
              // Format the creation date and escape the route name.
              $dateLabel = date("M d, Y H:i", strtotime($route['created_at']));
              $routeName = htmlspecialchars($route['route_name']);
            ?>
            <button class="load-route-btn glass-route cursor-pointer shadow-md"
              data-route='<?php echo htmlspecialchars($route['route_data']); ?>'>
              <div class="font-bold text-lg"><?php echo $routeName; ?></div>
              <div class="text-sm text-gray-200"><?php echo $dateLabel; ?></div>
            </button>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <!-- Route Summary Modal -->
  <div id="routeSummaryModal" class="fixed inset-0 flex items-center justify-center modal-bg hidden">
    <div class="modal-content relative">
      <button id="close-summary-modal" class="absolute top-2 right-2 text-white text-2xl focus:outline-none">&times;</button>
      <h3 class="text-2xl font-bold mb-4">Route Summary</h3>
      <div id="summary-details" class="text-gray-300 space-y-2">
        <!-- Route details will be shown here -->
      </div>
      <!-- Input field for Route Name -->
      <div class="mt-4">
        <label for="route-name" class="block mb-1">Route Name:</label>
        <input id="route-name" type="text" class="w-full p-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter a name for this route">
      </div>
      <div class="mt-4 flex flex-wrap gap-2">
        <button id="download-route-btn" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-md transition-colors">
          Download Route JSON
        </button>
        <button id="save-route-btn" class="bg-purple-500 hover:bg-purple-600 text-white font-semibold py-2 px-4 rounded-md transition-colors">
          Save Route
        </button>
      </div>
    </div>
  </div>
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const menuToggle = document.getElementById('menu-toggle');
      const mobileMenu = document.getElementById('mobile-menu');

      if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', function () {
          mobileMenu.classList.toggle('hidden');
        });
        // Close mobile menu when clicking outside
        document.addEventListener('click', function (event) {
          if (!mobileMenu.contains(event.target) && !menuToggle.contains(event.target)) {
            mobileMenu.classList.add('hidden');
          }
        });
      }

      // Route Summary modal events
      const summaryModal = document.getElementById('routeSummaryModal');
      document.getElementById('route-summary-btn').addEventListener('click', showRouteSummary);
      document.getElementById('close-summary-modal').addEventListener('click', function () {
        summaryModal.classList.add('hidden');
      });
      document.getElementById('download-route-btn').addEventListener('click', downloadRouteJSON);
      document.getElementById('save-route-btn').addEventListener('click', saveRoute);

      // Add click event to each saved route button
      document.querySelectorAll('.load-route-btn').forEach(function(button) {
        button.addEventListener('click', function() {
          const routeJSON = this.getAttribute('data-route');
          loadSavedRoute(routeJSON);
        });
      });
    });

    let map, directionsService, directionsRenderer, markers = [], waypoints = [], isKm = true, totalDistanceInKm = 0;

    function initMap() {
      map = new google.maps.Map(document.getElementById("map"), {
        center: { lat: 51.5074, lng: -0.1278 },
        zoom: 13,
        mapTypeControl: false,
      });

      directionsService = new google.maps.DirectionsService();
      directionsRenderer = new google.maps.DirectionsRenderer({ map: map, suppressMarkers: true });

      const input = document.getElementById("search-box");
      const searchBox = new google.maps.places.SearchBox(input);

      searchBox.addListener("places_changed", () => {
        const places = searchBox.getPlaces();
        if (!places.length) return;
        map.setCenter(places[0].geometry.location);
        map.setZoom(13);
      });

      map.addListener("click", (event) => {
        addWaypoint(event.latLng);
        calculateRoute();
      });

      document.getElementById("clear-route-btn").addEventListener("click", clearRoute);
      document.getElementById("toggle-units-btn").addEventListener("click", toggleUnits);
    }

    function addWaypoint(location) {
      const marker = new google.maps.Marker({ position: location, map, draggable: true });
      markers.push(marker);
      waypoints.push({ location, stopover: true });

      marker.addListener("dragend", () => {
        updateWaypoints();
        calculateRoute();
      });
    }

    function updateWaypoints() {
      waypoints = markers.map(marker => ({ location: marker.getPosition(), stopover: true }));
    }

    function calculateRoute() {
      if (waypoints.length < 2) {
        document.getElementById("route-details").textContent = "Add at least two points.";
        return;
      }

      const request = {
        origin: waypoints[0].location,
        destination: waypoints[waypoints.length - 1].location,
        waypoints: waypoints.slice(1, -1),
        travelMode: "WALKING",
      };

      directionsService.route(request, (result, status) => {
        if (status !== "OK") {
          document.getElementById("route-details").textContent = "Couldn't calculate route.";
          return;
        }
        directionsRenderer.setDirections(result);
        totalDistanceInKm = result.routes[0].legs.reduce((sum, leg) => sum + leg.distance.value, 0) / 1000;
        updateStats();
      });
    }

    function updateStats() {
      const distance = isKm ? totalDistanceInKm : totalDistanceInKm * 0.621371;
      const time = (totalDistanceInKm / 6) * 60;
      const calories = isKm ? totalDistanceInKm * 65 : (totalDistanceInKm * 0.621371) * 105;

      document.getElementById("distance").textContent = `${distance.toFixed(2)} ${isKm ? "km" : "miles"}`;
      document.getElementById("time").textContent = `${Math.round(time)} mins`;
      document.getElementById("calories").textContent = `${Math.round(calories)} kcal`;
    }

    function toggleUnits() {
      isKm = !isKm;
      updateStats();
    }

    function clearRoute() {
      markers.forEach(marker => marker.setMap(null));
      markers = [];
      waypoints = [];
      directionsRenderer.setDirections({ routes: [] });
      document.getElementById("route-details").textContent = "Click the map to start plotting your route.";
    }

    // Load a saved route from its JSON string
    function loadSavedRoute(routeJSON) {
      clearRoute();
      try {
        const routeData = JSON.parse(routeJSON);
        if (routeData.waypoints && Array.isArray(routeData.waypoints)) {
          routeData.waypoints.forEach(function(wp) {
            const latLng = new google.maps.LatLng(wp.lat, wp.lng);
            addWaypoint(latLng);
          });
          calculateRoute();
        }
      } catch (error) {
        console.error("Failed to parse route JSON:", error);
        alert("Could not load the selected route.");
      }
    }

    // Build and show the route summary modal
    function showRouteSummary() {
      const distance = isKm ? totalDistanceInKm : totalDistanceInKm * 0.621371;
      const time = (totalDistanceInKm / 6) * 60;
      const calories = isKm ? totalDistanceInKm * 65 : (totalDistanceInKm * 0.621371) * 105;
      const markerPositions = markers.map(marker => {
        const pos = marker.getPosition();
        return { lat: pos.lat(), lng: pos.lng() };
      });

      const routeData = {
        distance: `${distance.toFixed(2)} ${isKm ? "km" : "miles"}`,
        estimatedTime: `${Math.round(time)} mins`,
        caloriesBurned: `${Math.round(calories)} kcal`,
        waypoints: markerPositions
      };

      const summaryEl = document.getElementById("summary-details");
      summaryEl.innerHTML = `
        <p><strong>Total Distance:</strong> ${routeData.distance}</p>
        <p><strong>Estimated Time:</strong> ${routeData.estimatedTime}</p>
        <p><strong>Calories Burned:</strong> ${routeData.caloriesBurned}</p>
        <p><strong>Waypoints:</strong></p>
        <ul class="list-disc list-inside text-gray-200">
          ${routeData.waypoints.map((wp, idx) => `<li>Point ${idx+1}: (${wp.lat.toFixed(4)}, ${wp.lng.toFixed(4)})</li>`).join('')}
        </ul>
      `;

      document.getElementById("routeSummaryModal").dataset.route = JSON.stringify(routeData, null, 2);
      document.getElementById("routeSummaryModal").classList.remove("hidden");
    }

    // Download the route JSON file
    function downloadRouteJSON() {
      const routeJSON = document.getElementById("routeSummaryModal").dataset.route;
      if (!routeJSON) return;
      const blob = new Blob([routeJSON], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "route-summary.json";
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }

    // Save the route to the server via AJAX, including the route name
    function saveRoute() {
      const routeJSON = document.getElementById("routeSummaryModal").dataset.route;
      const routeName = document.getElementById("route-name").value.trim();
      if (!routeJSON) return;
      if (!routeName) {
        alert("Please enter a route name.");
        return;
      }
      const routeToSave = {
        route_name: routeName,
        route_data: JSON.parse(routeJSON)
      };

      fetch("save_route.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(routeToSave)
      })
      .then(response => response.json())
      .then(data => {
        alert(data.success ? "Route saved successfully!" : "Failed to save route.");
      })
      .catch(error => {
        console.error("Error saving route:", error);
        alert("Error saving route.");
      });
    }
  </script>
  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB93GeADB1gCobTiozzWty_rNu8iD2RrA4&libraries=places&callback=initMap" async defer></script>
</body>
</html>
