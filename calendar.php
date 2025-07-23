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

$user_id = $_SESSION['user_id'];

// Fetch all exercise logs grouped by date
$exerciseLogs = [];
$sql = "SELECT log_date, exercise_name, weight, reps, sets 
        FROM exercise_logs 
        WHERE user_id = ? 
        ORDER BY log_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $date = $row['log_date'];
    if (!isset($exerciseLogs[$date])) {
        $exerciseLogs[$date] = [];
    }
    $exerciseLogs[$date][] = $row;
}

$stmt->close();
$conn->close();

// Convert logs to JSON for JavaScript
$exerciseLogsJson = json_encode($exerciseLogs);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Workout Calendar</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet" />
  <style>
    /* Fade-in effect for page */
    .fade-in {
      animation: fadeIn 0.8s ease-in-out;
    }
    @keyframes fadeIn { 
      from { opacity: 0; transform: translateY(-10px); } 
      to { opacity: 1; transform: translateY(0); }
    }

    /* glowing title */
    .title-glow {
      font-size: 2.5rem;
      font-weight: bold;
      color: #60A5FA;
      text-align: center;
      text-shadow: 0px 0px 10px rgba(96, 165, 250, 0.8);
      animation: pulseGlow 1.5s infinite alternate ease-in-out;
    }
    @keyframes pulseGlow {
      from { text-shadow: 0px 0px 10px rgba(96, 165, 250, 0.8); }
      to { text-shadow: 0px 0px 20px rgba(96, 165, 250, 1); }
    }

    /* Calendar container with smooth animation */
    #calendar {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      padding: 20px;
      border-radius: 12px;
      opacity: 0;
      transform: scale(0.95);
      animation: popIn 0.8s forwards ease-out;
    }
    @keyframes popIn {
      to { opacity: 1; transform: scale(1); }
    }

    /* Modal background fade */
    .modal-bg {
      background: rgba(0, 0, 0, 0.7);
    }

    /* Hover effect for calendar days */
    .fc-daygrid-day {
      transition: background 0.3s ease-in-out;
    }
    .fc-daygrid-day:hover {
      background: rgba(96, 165, 250, 0.3);
      cursor: pointer;
      transform: scale(1.05);
    }

    /* Workout day highlight */
    .workout-day {
      background: linear-gradient(45deg, #3B82F6, #60A5FA);
      color: white !important;
      border-radius: 8px;
      padding: 2px;
      text-align: center;
    }

    /* Mobile-specific styles */
    @media (max-width: 640px) {
      #calendar {
        padding: 10px !important;
        font-size: 0.9rem;
      }
      .fc-toolbar-title {
        font-size: 1.2rem !important;
      }
      .fc-daygrid-day-number {
        font-size: 0.75rem !important;
      }
    }

    /* Custom styles for FullCalendar header (day names) */
    .fc .fc-col-header-cell {
      background-color: #1F2937 !important;
      border: none !important;
    }
    .fc .fc-col-header-cell-cushion {
      color: #ffffff !important; 
      font-weight: bold;
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

    .welcome-heading {
      background: linear-gradient(90deg, #60a5fa, #3b82f6);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      animation: bounce 2s infinite;
    }

    body { font-family: 'Poppins', sans-serif; }
  </style>
</head>
<body class="bg-gray-900 text-white fade-in">
  <!-- Navigation Bar -->
  <nav class="bg-gray-800 bg-opacity-90 backdrop-blur-md shadow-lg fixed w-full top-0 z-50  animate-fade-slide">
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

  <!-- PAGE TITLE & CALENDAR -->
  <main class="container mx-auto py-12 px-4 sm:px-6 lg:px-8 mt-24">
  <h1 class="welcome-heading text-5xl font-bold text-center drop-shadow-lg">
        <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES) ?>'s Calendar
      </h1>
      <p class="text-lg text-gray-400 text-center mt-4">
        Check past workouts
      </p>
    <div id="calendar" class="mt-8"></div>
  </main>

  <div id="exerciseModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-bg">
    <div class="bg-gray-800 rounded-lg p-6 relative max-w-xl w-full mx-4 overflow-y-auto max-h-screen">
      <button class="absolute top-2 right-2 text-white text-xl" onclick="closeModal()">
        <i class="ri-close-line"></i>
      </button>
      <h3 id="modalDate" class="text-2xl font-bold mb-2"></h3>
      <div id="modalContent" class="space-y-2"></div>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      let exerciseLogs = <?= $exerciseLogsJson ?>;

      // Determine initial view based on screen width
      let initialView = window.innerWidth < 640 ? 'listWeek' : 'dayGridMonth';

      let calendarEl = document.getElementById("calendar");
      let calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: initialView,
        height: 'auto',
        headerToolbar: {
          left: "prev,next today",
          center: "title",
          right: "dayGridMonth,timeGridWeek,listWeek"
        },
        events: Object.keys(exerciseLogs).map(date => ({
          title: "Workouts",
          start: date,
          className: "workout-day"
        })),
        eventMouseEnter: function (info) {
          let date = info.event.startStr;
          info.el.innerHTML = exerciseLogs[date].map(e => e.exercise_name).join(", ");
        },
        eventMouseLeave: function (info) {
          info.el.innerHTML = "Workouts";
        },
        dateClick: function (info) {
          let date = info.dateStr;
          // Show modal even if there are no workouts logged for this day.
          showModal(date, exerciseLogs[date] || []);
        }
      });
  
      calendar.render();

      // Adjust calendar view on window resize
      window.addEventListener('resize', function() {
        if (window.innerWidth < 640 && calendar.view.type !== 'listWeek') {
          calendar.changeView('listWeek');
        } else if (window.innerWidth >= 640 && calendar.view.type === 'listWeek') {
          calendar.changeView('dayGridMonth');
        }
      });

    
    });

    function showModal(date, exercises) {
      document.getElementById("modalDate").textContent = `Workouts on ${new Date(date).toLocaleDateString("en-GB")}`;
      let modalContent = document.getElementById("modalContent");
      if (exercises.length > 0) {
        modalContent.innerHTML = exercises.map(e => `<p><strong>${e.exercise_name}:</strong> ${e.weight}kg, ${e.reps} reps, ${e.sets} sets</p>`).join("");
      } else {
        modalContent.innerHTML = `<p>No workouts logged for this day.</p>`;
      }
      document.getElementById("exerciseModal").classList.remove("hidden");
    }
  
    function closeModal() {
      document.getElementById("exerciseModal").classList.add("hidden");
    }
  </script>
  <script>
     // Mobile Menu Toggle
     document.getElementById('menu-toggle').addEventListener('click', function() {
      const menu = document.getElementById('mobile-menu');
      menu.classList.toggle('hidden');
    });
    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
      const menu = document.getElementById('mobile-menu');
      const button = document.getElementById('menu-toggle');
      if (!menu.contains(event.target) && !button.contains(event.target)) {
          menu.classList.add('hidden');
      }
    });
  </script>
</body>
</html>
