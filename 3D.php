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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fitness Tracker | 3D Muscle Anatomy</title>
  <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script type="module" src="https://unpkg.com/@google/model-viewer@latest"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    @keyframes fadeSlide {
      0% { opacity: 0; transform: translateY(-10px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-slide {
      animation: fadeSlide 0.6s ease-out forwards;
    }
    /* Navigation Styles */
    .nav-link {
      position: relative;
      color: white;
      font-size: 16px;
      font-weight: 500;
      transition: color 0.3s ease-in-out, transform 0.3s;
    }
    .nav-link:hover {
      color: #60a5fa;
      transform: scale(1.05);
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

    /* Model Container */
    .model-container {
      width: 100%;
      max-width: 900px;
      height: 650px;
      position: relative;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.7);
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(4px);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    @media (max-width: 768px) {
      .model-container { height: 500px; }
    }
    .model-container:hover {
      animation: pulseGlow 2s infinite;
    }
    @keyframes pulseGlow {
      0% { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.7); }
      50% { box-shadow: 0 4px 30px rgba(96, 165, 250, 0.8); }
      100% { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.7); }
    }
    /* Info Box */
    #info-box {
      background: rgba(31, 41, 55, 0.95);
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.8);
      padding: 1.5rem;
      max-width: 320px;
      width: 100%;
      transition: transform 0.3s ease, opacity 0.3s ease;
    }
    .transition-scale {
      transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
    }
    .scale-0 { transform: scale(0.8); opacity: 0; }
    .scale-100 { transform: scale(1); opacity: 1; }
    .animate-slide-in { animation: slideIn 0.3s forwards; }
    @keyframes slideIn {
      from { transform: translateX(50px); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    /* Hotspots */
    .hotspot {
      background: rgba(0, 0, 0, 0.7);
      color: white;
      padding: 6px 10px;
      border-radius: 6px;
      font-size: 12px;
      border: 1px solid white;
      cursor: pointer;
      transition: transform 0.2s ease, background 0.2s ease;
    }
    .hotspot:hover {
      transform: scale(1.1);
      background: rgba(0, 0, 0, 0.9);
    }
    .hidden-hotspot {
      opacity: 0;
      pointer-events: none;
    }
    /* Loading Spinner */
    .loading-spinner {
      border: 4px solid rgba(255, 255, 255, 0.2);
      border-top: 4px solid #60a5fa;
      border-radius: 50%;
      width: 32px;
      height: 32px;
      animation: spin 1s linear infinite;
      margin: 0 auto;
    }
    @keyframes spin {
      0% { transform: rotate(0); }
      100% { transform: rotate(360deg); }
    }

    body { font-family: 'Poppins', sans-serif; }
  </style>
</head>
<body class="bg-gray-900 text-white">
  <nav class="bg-gray-800 bg-opacity-90 backdrop-blur-md shadow-lg fixed w-full top-0 z-50 opacity-0 animate-fade-slide">
    <div class="max-w-6xl mx-auto px-4 flex justify-between items-center py-4 relative">
      <a href="Homepage.php" class="flex items-center space-x-2">
        <img src="assets/Logo_Fitness.png" alt="Fitness Tracker Logo" class="h-8" />
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
  <main class="max-w-6xl mx-auto px-6 mt-24">
    <div class="text-center opacity-0 animate-fade-slide">
      <h1 class="text-4xl font-bold text-blue-400 drop-shadow-lg">Interactive 3D Muscle Anatomy</h1>
      <p class="text-lg text-gray-400 mt-4">
      Click on any muscle to explore recommended exercises, functions, and tips.
      </p>
    </div>
    <div class="flex flex-col md:flex-row items-center justify-center mt-10 gap-8">
      <!-- 3D Model Viewer -->
      <div class="p-4 w-full model-container relative">
        <!-- Loading Spinner -->
        <div id="model-loading" class="absolute inset-0 flex justify-center items-center bg-black bg-opacity-50 z-10">
          <div class="loading-spinner"></div>
        </div>
        <model-viewer
          id="muscleModel"
          src="assets/muscles.glb"
          alt="3D Human Muscle Model"
          camera-controls
          disable-zoom
          disable-pan
          environment-image="neutral"
          exposure="1"
          class="w-full h-full rounded-lg"
        >
          <!-- FRONT Hotspots -->
          <button class="hotspot front" slot="hotspot-1" data-position="0 0.8 0.12" onclick="showMuscleInfo('Chest')">Chest</button>
          <button class="hotspot front" slot="hotspot-2" data-position="0.45 0.7 0.15" onclick="showMuscleInfo('Biceps')">Biceps</button>
          <button class="hotspot front" slot="hotspot-3" data-position="0 0.45 0.12" onclick="showMuscleInfo('Abs')">Abs</button>
          <button class="hotspot front" slot="hotspot-4" data-position="0.26 -0.1 0.18" onclick="showMuscleInfo('Quads')">Quads</button>
          <button class="hotspot front" slot="hotspot-9" data-position="-0.35 0.9 0.15" onclick="showMuscleInfo('Shoulders')">Shoulders</button>
          <button class="hotspot front" slot="hotspot-10" data-position="-0.7 0.5 0.2" onclick="showMuscleInfo('Forearms')">Forearms</button>
          <button class="hotspot front" slot="hotspot-11" data-position="-0.25 0.4 0.2" onclick="showMuscleInfo('Obliques')">Obliques</button>
          <!-- BACK Hotspots -->
          <button class="hotspot back" slot="hotspot-5" data-position="0 0.7 -0.12" onclick="showMuscleInfo('Back')">Back</button>
          <button class="hotspot back" slot="hotspot-6" data-position="-0.45 0.7 -0.15" onclick="showMuscleInfo('Triceps')">Triceps</button>
          <button class="hotspot back" slot="hotspot-7" data-position="-0.26 -0.25 -0.18" onclick="showMuscleInfo('Hamstrings')">Hamstrings</button>
          <button class="hotspot back" slot="hotspot-8" data-position="0.25 -0.75 -0.18" onclick="showMuscleInfo('Calves')">Calves</button>
          <button class="hotspot back" slot="hotspot-12" data-position="0 1 -0.1" onclick="showMuscleInfo('Traps')">Traps</button>
          <button class="hotspot back" slot="hotspot-13" data-position="0 0.1 -0.2" onclick="showMuscleInfo('Glutes')">Glutes</button>
        </model-viewer>
      </div>

      <!-- Info Box -->
      <div id="info-box" class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-sm w-full transition-scale scale-0 opacity-0">
        <div class="flex justify-end">
          <button class="text-gray-400 hover:text-white text-xl font-bold" onclick="closeMuscleInfo()">✕</button>
        </div>
        <h2 id="muscle-name" class="text-2xl font-bold text-blue-400 mt-2">Muscle Name</h2>
        <div class="mt-4">
          <h3 class="text-lg font-semibold text-blue-300">Recommended Exercises:</h3>
          <ul id="exercise-list" class="mt-2 text-gray-200 list-disc list-inside"></ul>
        </div>
        <div class="mt-4">
          <h3 class="text-lg font-semibold text-blue-300">Muscle Function:</h3>
          <p id="muscle-function" class="text-gray-200 mt-1"></p>
        </div>
        <div class="mt-4">
          <h3 class="text-lg font-semibold text-blue-300">Tips & Benefits:</h3>
          <p id="tips" class="text-gray-200 mt-1"></p>
        </div>
        <div class="mt-4">
          <h3 class="text-lg font-semibold text-blue-300">Common Injuries & Recovery:</h3>
          <p id="injuries" class="text-gray-200 mt-1"></p>
        </div>
      </div>
    </div>
  </main>

  <footer class="text-gray-500 text-xs text-center mt-10 pb-4">
    <p>
      3D Model
      <a href="https://skfb.ly/o8yXB" target="_blank" class="text-gray-400 hover:text-gray-300">
        "TECG_284_Dupont_william_Anatomy"
      </a>
      by
      <a href="https://sketchfab.com/HEPL3D" target="_blank" class="text-gray-400 hover:text-gray-300">
        HEPL3D
      </a>
      is licensed under
      <a href="http://creativecommons.org/licenses/by/4.0/" target="_blank" class="text-gray-400 hover:text-gray-300">
        CC Attribution 4.0
      </a>.
    </p>
  </footer>

  <script>
    // Mobile Menu Toggle
    document.getElementById("menu-toggle").addEventListener("click", function () {
      const menu = document.getElementById("mobile-menu");
      menu.classList.toggle("hidden");
    });
    document.addEventListener("click", function (event) {
      const menu = document.getElementById("mobile-menu");
      const button = document.getElementById("menu-toggle");
      if (!menu.contains(event.target) && !button.contains(event.target)) {
        menu.classList.add("hidden");
      }
    });

    // Model reference & loading spinner
    const model = document.getElementById("muscleModel");
    model.addEventListener("load", () => {
      document.getElementById("model-loading").style.display = "none";
    });

    // Hotspot visibility based on camera
    const frontHotspots = document.querySelectorAll(".hotspot.front");
    const backHotspots = document.querySelectorAll(".hotspot.back");
    function normalizeTheta(theta) {
      theta = theta % (2 * Math.PI);
      return theta < 0 ? theta + 2 * Math.PI : theta;
    }
    function updateHotspotVisibility() {
      const orbit = model.getCameraOrbit();
      const theta = normalizeTheta(orbit.theta);
      const isFront = theta <= Math.PI / 2 || theta >= (3 * Math.PI) / 2;
      frontHotspots.forEach(h => h.classList.toggle("hidden-hotspot", !isFront));
      backHotspots.forEach(h => h.classList.toggle("hidden-hotspot", isFront));
    }
    model.addEventListener("camera-change", updateHotspotVisibility);
    setInterval(updateHotspotVisibility, 100);

    // Muscle info
    const muscleInfo = {
      Chest: {
        exercises: ["Bench Press", "Incline Dumbbell Press", "Push-Ups"],
        tips: "Focus on full range of motion and controlled movements.",
        function: "Powers pushing movements and stabilizes the shoulder joints.",
        injuries: "Avoid strains by warming up and using proper form."
      },
      Biceps: {
        exercises: ["Bicep Curls", "Hammer Curls", "Chin-Ups"],
        tips: "Keep elbows steady to isolate the muscle.",
        function: "Bends the elbow and rotates the forearm.",
        injuries: "Prevent tendonitis by avoiding excessive weights."
      },
      Triceps: {
        exercises: ["Triceps Dips", "Overhead Extensions", "Close-Grip Bench Press"],
        tips: "Maintain elbow alignment and control the descent.",
        function: "Straightens the arm and supports pushing movements.",
        injuries: "Avoid overexertion to prevent muscle tears."
      },
      Shoulders: {
        exercises: ["Overhead Press", "Lateral Raises", "Front Raises"],
        tips: "Stabilize your core and avoid shrugging during lifts.",
        function: "Allows for a wide range of arm movements.",
        injuries: "Rotator cuff injuries can occur; warm up properly."
      },
      Back: {
        exercises: ["Pull-Ups", "Deadlifts", "Lat Pulldown"],
        tips: "Keep your back straight and engage your core.",
        function: "Supports posture and enables pulling movements.",
        injuries: "Improper form can cause strains; use proper technique."
      },
      Abs: {
        exercises: ["Crunches", "Planks", "Hanging Leg Raises"],
        tips: "Control your movements and breathe correctly.",
        function: "Stabilizes your core and improves balance.",
        injuries: "Avoid excessive strain to prevent back pain."
      },
      Quads: {
        exercises: ["Squats", "Lunges", "Leg Press"],
        tips: "Keep knees aligned and drive through your heels.",
        function: "Extends the knee and powers leg movements.",
        injuries: "Watch for knee pain by maintaining proper form."
      },
      Hamstrings: {
        exercises: ["Romanian Deadlifts", "Leg Curls", "Glute Ham Raises"],
        tips: "Hinge at the hips and keep a slight knee bend.",
        function: "Bends the knee and extends the hip.",
        injuries: "Focus on flexibility to avoid muscle strains."
      },
      Calves: {
        exercises: ["Standing Calf Raises", "Seated Calf Raises", "Jump Rope"],
        tips: "Pause at the top for maximum contraction.",
        function: "Facilitates ankle movements and supports explosive actions.",
        injuries: "Gradually increase load to avoid strains."
      },
      Traps: {
        exercises: ["Barbell Shrugs", "Dumbbell Shrugs", "Farmer’s Walk"],
        tips: "Keep your neck neutral and engage your shoulders.",
        function: "Supports neck and shoulder movements.",
        injuries: "Avoid overloading to prevent shoulder strains."
      },
      Glutes: {
        exercises: ["Hip Thrusts", "Glute Bridges", "Squats"],
        tips: "Squeeze at the top for full contraction.",
        function: "Enables hip extension and powers lower-body movements.",
        injuries: "Focus on form to prevent lower back strain."
      },
      Forearms: {
        exercises: ["Wrist Curls", "Reverse Wrist Curls", "Farmer’s Walk"],
        tips: "Maintain slow, controlled movements for best results.",
        function: "Aids in grip strength and wrist movement.",
        injuries: "Prevent repetitive strain by varying exercises."
      },
      Obliques: {
        exercises: ["Side Planks", "Russian Twists", "Cable Woodchoppers"],
        tips: "Keep your core engaged and twist with control.",
        function: "Supports rotational movements and core stability.",
        injuries: "Avoid heavy twists that could strain the lower back."
      }
    };

    // Show muscle info in info box
    function showMuscleInfo(muscle) {
      const nameEl = document.getElementById("muscle-name");
      const listEl = document.getElementById("exercise-list");
      const tipsEl = document.getElementById("tips");
      const funcEl = document.getElementById("muscle-function");
      const injuriesEl = document.getElementById("injuries");
      const infoBox = document.getElementById("info-box");

      if (!muscleInfo[muscle]) return;
      nameEl.innerText = muscle;
      listEl.innerHTML = muscleInfo[muscle].exercises.map(ex => `<li>${ex}</li>`).join("");
      tipsEl.innerText = muscleInfo[muscle].tips;
      funcEl.innerText = muscleInfo[muscle].function;
      injuriesEl.innerText = muscleInfo[muscle].injuries;

      infoBox.classList.remove("scale-0", "opacity-0");
      infoBox.classList.add("scale-100", "animate-slide-in");
    }

    // Close info box
    function closeMuscleInfo() {
      const infoBox = document.getElementById("info-box");
      infoBox.classList.remove("scale-100", "animate-slide-in");
      infoBox.classList.add("scale-0", "opacity-0");
    }
  </script>
</body>
</html>
