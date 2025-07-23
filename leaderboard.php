<?php
session_start();
if (!isset($_SESSION['loggedin'])) {
    header('Location: index.html');
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tracker";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$query_leaderboard = "
    SELECT 
        exercise_name,
        max_weight,
        sets,
        reps,
        username,
        formatted_date
    FROM (
      SELECT 
        exercise_name,
        weight AS max_weight,
        sets,
        reps,
        username,
        log_date,
        DATE_FORMAT(log_date, '%d-%m-%Y') AS formatted_date,
        ROW_NUMBER() OVER (
            PARTITION BY exercise_name 
            ORDER BY weight DESC, sets DESC, reps DESC
        ) AS rn
      FROM exercise_logs
      INNER JOIN users ON exercise_logs.user_id = users.user_id
    ) AS sub
    WHERE rn = 1
    ORDER BY exercise_name ASC";
    
$result_leaderboard = $conn->query($query_leaderboard);

$leaderboard_data = [];
if ($result_leaderboard && $result_leaderboard->num_rows > 0) {
    while ($row = $result_leaderboard->fetch_assoc()) {
        // Convert max_weight to int
        $row['max_weight'] = (float)$row['max_weight'] == (int)$row['max_weight'] ? (int)$row['max_weight'] : (float)$row['max_weight'];
        $leaderboard_data[] = $row;
    }
}

$conn->close();

// Get the logged in user's username for highlighting their entry
$logged_in_username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Tracker | Leaderboard</title>
    <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .glassmorphism {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
        }
        .fade-in {
            animation: fadeIn 0.8s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Hover Effect for Table Rows */
        .hover-effect:hover {
            background: rgba(156, 163, 175, 0.2);
            transition: background 0.2s ease-in-out;
        }
        /* Enlarge each cell within a row on hover */
        .hover-effect:hover td { 
            transform: scale(1.06); 
            transition: transform 0.2s ease-in-out; 
        }
        /* Highlight the logged-in user's row */
        .highlight-entry {
            background: rgba(96, 165, 250, 0.2);
        }
        .highlight-entry:hover {
            background: rgba(96, 165, 250, 0.4);
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
        /* Leaderboard Table */
        table {
            min-width: 600px;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid rgba(55, 65, 81, 0.5);
            padding: 12px;
        }
        thead {
            background: linear-gradient(90deg, #60a5fa, #3b82f6);
            color: white;
        }
        th {
            cursor: pointer;
            transition: color 0.3s ease;
        }
        th:hover { color: #dbeafe; }

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

    <!-- Loading Spinner -->
    <div id="loading" class="flex justify-center items-center h-screen">
        <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-blue-400"></div>
    </div>

    <main id="leaderboard-content" class="max-w-6xl mx-auto px-4 mt-24 fade-in hidden">
        <div class="text-center">
            <h2 class="text-4xl font-bold text-blue-400">🏆 Leaderboard</h2>
            <p class="mt-2 text-gray-400">See who’s at the top for each exercise!</p>
        </div>

        <!--Search Bar -->
        <div class="mt-6 flex justify-center">
            <input 
                type="text" 
                id="search-bar"
                class="w-2/3 sm:w-1/2 p-3 bg-gray-800 border border-gray-700 rounded-md text-white text-center"
                placeholder="🔍 Search exercises..."
                onkeyup="filterTable()"
            />
        </div>

        <!--Leaderboard Table -->
        <div class="mt-8 bg-gray-800 p-6 rounded-lg shadow-lg overflow-x-auto">
            <table class="w-full text-center text-sm">
                <thead>
                    <tr>
                        <th class="cursor-pointer hover:text-blue-400" onclick="sortTable(0)">Exercise ⬍</th>
                        <th class="cursor-pointer hover:text-blue-400" onclick="sortTable(1)">User ⬍</th>
                        <th class="cursor-pointer hover:text-blue-400" onclick="sortTable(2)">Max Weight (kg) ⬍</th>
                        <th class="cursor-pointer hover:text-blue-400" onclick="sortTable(3)">Reps ⬍</th>
                        <th class="cursor-pointer hover:text-blue-400" onclick="sortTable(4)">Date ⬍</th>
                    </tr>
                </thead>
                <tbody id="leaderboard-body">
                    <?php if (!empty($leaderboard_data)): ?>
                        <?php foreach ($leaderboard_data as $entry): 
                            // Highlight row if this entry belongs to the logged in user.
                            $highlight = ($entry['username'] === $logged_in_username) ? "highlight-entry" : "";
                        ?>
                            <tr class="hover-effect <?= $highlight; ?>">
                                <td><?= htmlspecialchars($entry['exercise_name']); ?></td>
                                <td><?= htmlspecialchars($entry['username']); ?></td>
                                <td><?= htmlspecialchars($entry['max_weight']); ?></td>
                                <td><?= htmlspecialchars($entry['reps']); ?></td>
                                <td><?= htmlspecialchars($entry['formatted_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-gray-400">No data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Mobile Menu Toggle
        document.addEventListener("DOMContentLoaded", function () {
            const menuToggle = document.getElementById("menu-toggle");
            const mobileMenu = document.getElementById("mobile-menu");

            if (menuToggle && mobileMenu) {
                menuToggle.addEventListener("click", function () {
                    mobileMenu.classList.toggle("hidden");
                });

                // Close menu when clicking outside
                document.addEventListener("click", function (event) {
                    if (!mobileMenu.contains(event.target) && !menuToggle.contains(event.target)) {
                        mobileMenu.classList.add("hidden");
                    }
                });
            }

            // Hide loading spinner and show content after a brief delay
            setTimeout(() => {
                document.getElementById("loading").classList.add("hidden");
                const leaderboardContent = document.getElementById("leaderboard-content");
                leaderboardContent.classList.remove("hidden");

                // Trigger confetti for every row with the highlight-entry class
                const highlightedRows = document.querySelectorAll(".highlight-entry");
                highlightedRows.forEach(row => {
                    const rect = row.getBoundingClientRect();
                    // Calculate the vertical center (as a fraction of the window height)
                    const centerY = (rect.top + rect.bottom) / 2 / window.innerHeight;
                    // Launch confetti from the left edge of the row
                    confetti({
                        particleCount: 50,
                        spread: 100,
                        startVelocity: 30,
                        origin: { x: rect.left / window.innerWidth, y: centerY }
                    });
                    // Launch confetti from the right edge of the row
                    confetti({
                        particleCount: 50,
                        spread: 100,
                        startVelocity: 30,
                        origin: { x: rect.right / window.innerWidth, y: centerY }
                    });
                });
            }, 1000);
        });

        // Sorting Functionality
        function sortTable(columnIndex) {
            const tableBody = document.getElementById("leaderboard-body");
            const rows = Array.from(tableBody.rows);

            rows.sort((a, b) => {
                const valA = a.cells[columnIndex].innerText.toLowerCase();
                const valB = b.cells[columnIndex].innerText.toLowerCase();
                if (!isNaN(parseFloat(valA)) && !isNaN(parseFloat(valB))) {
                    return parseFloat(valB) - parseFloat(valA);
                }
                return valA.localeCompare(valB);
            });

            rows.forEach(row => tableBody.appendChild(row));
        }

        // Filtering Functionality
        function filterTable() {
            const searchInput = document.getElementById("search-bar").value.toLowerCase();
            const rows = document.querySelectorAll("#leaderboard-body tr");

            rows.forEach(row => {
                const exerciseName = row.cells[0].innerText.toLowerCase();
                row.style.display = exerciseName.includes(searchInput) ? "" : "none";
            });
        }
    </script>
</body>
</html>
