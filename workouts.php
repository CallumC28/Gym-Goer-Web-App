<!DOCTYPE html>
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

    // Fetch exercises from the database
    $exerciseOptions = [];
    $query = "SELECT exercise_name FROM list";
    $result = mysqli_query($conn, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $exerciseOptions[] = $row['exercise_name'];
        }
    } else {
        echo "Error fetching exercises: " . mysqli_error($conn);
    }
?>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Fitness Tracker | Log Exercises</title>
    <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet"/>
    <style>
   
        .glass {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            padding: 20px;
            transition: transform 0.3s ease-in-out;
        }

        .message-box {
            position: fixed;
            top: 15%;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 20px;
            font-size: 1.1rem;
            font-weight: bold;
            border-radius: 8px;
            box-shadow: 0px 5px 10px rgba(0, 0, 0, 0.2);
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
            z-index: 9999; /* Make sure it's on top */
        }
        /* Input Validation */
        input:focus, select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 8px rgba(59, 130, 246, 0.5);
        }
        /* Fade-in Animation */
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
        <h1 class="text-3xl font-bold mb-6 text-center text-blue-400">Log Your Exercise</h1>
        <form id="exercise-form" class="space-y-6">
            <!-- Exercise Name -->
            <div>
                <label for="exercise_name" class="block font-medium text-gray-300">Exercise Name</label>
                <select id="exercise_name" name="exercise_name" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 text-white" required>
                    <option value="" disabled selected>Select an exercise</option>
                    <?php foreach ($exerciseOptions as $exercise): ?>
                        <option value="<?= htmlspecialchars($exercise) ?>"><?= htmlspecialchars($exercise) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sets & Reps -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="sets" class="block font-medium text-gray-300">Sets</label>
                    <input type="number" id="sets" name="sets" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white" required />
                </div>
                <div>
                    <label for="reps" class="block font-medium text-gray-300">Reps</label>
                    <input type="number" id="reps" name="reps" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white" required />
                </div>
            </div>

            <!-- Weight -->
            <div>
                <label for="weight" class="block font-medium text-gray-300">Weight (kg)</label>
                <input type="number" step="0.01" id="weight" name="weight" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white" />
            </div>

            <!-- Date -->
            <div>
                <label for="log_date" class="block font-medium text-gray-300">Log Date</label>
                <input type="date" id="log_date" name="log_date" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white" required />
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full py-3 bg-blue-500 text-white rounded-lg font-semibold hover:bg-blue-600 transition duration-300">
                Submit
            </button>
        </form>
    </div>
</main>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Mobile menu toggle
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

    // Set log_date default to today's date
    document.getElementById("log_date").valueAsDate = new Date();

    // Form submission
    const exerciseForm = document.getElementById("exercise-form");
    exerciseForm.addEventListener("submit", function (e) {
        e.preventDefault();
        const formData = new FormData(exerciseForm);

        // Make POST request
        fetch("log_exercise.php", { method: "POST", body: formData })
            .then(response => {
                if (!response.ok) {
                    // If we didn't get a 200 response, throw an error
                    throw new Error(`Network response was not ok (status: ${response.status})`);
                }
                return response.json();
            })
            .then(data => {
                console.log("Server response:", data);  // <--- Log to console for debugging

                // Create the standard message box
                const messageBox = document.createElement("div");
                messageBox.className = `message-box ${data.status === "success" ? "bg-green-500" : "bg-red-500"} text-white`;
                messageBox.textContent = data.message || "No message returned.";
                document.body.appendChild(messageBox);

                // Fade in/out effect
                setTimeout(() => {
                    messageBox.style.opacity = "1";
                }, 100);
                setTimeout(() => {
                    messageBox.style.opacity = "0";
                    setTimeout(() => messageBox.remove(), 500);
                }, 3000);

                // Check if achievements_earned is non-empty
                if (data.achievements_earned && data.achievements_earned.length > 0) {
                    const achievementBox = document.createElement("div");
                    achievementBox.className = "message-box bg-blue-600 text-white";
                    achievementBox.style.zIndex = 9999; 
                    const earnedTitles = data.achievements_earned.join(', ');

                    achievementBox.textContent = `Congratulations! You earned: ${earnedTitles}`;
                    document.body.appendChild(achievementBox);

                    setTimeout(() => { achievementBox.style.opacity = "1"; }, 100);
                    setTimeout(() => {
                        achievementBox.style.opacity = "0";
                        setTimeout(() => achievementBox.remove(), 500);
                    }, 5000);
                }

                // If success, you could also reset the form
                if (data.status === 'success') {
                    exerciseForm.reset();
                }
            })
            .catch(error => {
                console.error("Error in fetch:", error);
                // Show an error box if the request fails
                const errorBox = document.createElement("div");
                errorBox.className = "message-box bg-red-600 text-white";
                errorBox.textContent = "Error logging exercise. Check console for details.";
                document.body.appendChild(errorBox);
                setTimeout(() => { errorBox.style.opacity = "1"; }, 100);
                setTimeout(() => {
                    errorBox.style.opacity = "0";
                    setTimeout(() => errorBox.remove(), 500);
                }, 4000);
            });
    });
});
</script>
</body>
</html>
