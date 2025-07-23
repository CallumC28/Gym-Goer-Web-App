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
$goalError = '';

// Handle form submission for new goal
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['goal_text'])) {
    $goal_text = trim($_POST['goal_text']);
    $reminder = !empty($_POST['reminder']) ? trim($_POST['reminder']) : null;
    
    if (!empty($goal_text)) {
        // Insert new goal with status "pending"
        $stmt = $conn->prepare("INSERT INTO user_goals (user_id, goal_text, reminder, status) VALUES (?, ?, ?, 'pending')");
        $stmt->bind_param("iss", $user_id, $goal_text, $reminder);
        if ($stmt->execute()) {
            header("Location: goal-setting.php");
            exit;
        } else {
            $goalError = "Error saving goal: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $goalError = "Please enter a goal.";
    }
}

// Retrieve user's current goals (including status)
$goals = [];
$stmt = $conn->prepare("SELECT goal_id, goal_text, reminder, created_at, status FROM user_goals WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $goals[] = $row;
}
$stmt->close();
$conn->close();

// Group goals by status
$activeGoals = $completedGoals = $failedGoals = [];
foreach ($goals as $goal) {
    if ($goal['status'] === 'pending') {
        $activeGoals[] = $goal;
    } elseif ($goal['status'] === 'completed') {
        $completedGoals[] = $goal;
    } elseif ($goal['status'] === 'failed') {
        $failedGoals[] = $goal;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Fitness Tracker | Goal Setting & Personalization</title>
  <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
  <style>
    /* Fade/Slide Animation */
    @keyframes fadeSlide {
      0% { opacity: 0; transform: translateY(-10px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-slide { animation: fadeSlide 0.6s ease-out forwards; }
    
    /* Glassmorphism & Card Styling */
    .glassmorphism {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .hover-scale:hover {
      transform: scale(1.05);
      transition: 0.3s;
    }
    .goal-card {
      padding: 1rem;
      border: 2px solid;
      border-radius: 0.5rem;
      margin-bottom: 1rem;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .goal-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }
    /* Color indicators */
    .border-pending { border-color: #4B5563; }
    .border-completed { border-color: #10B981; } 
    .border-failed { border-color: #EF4444; } 
    
    /* Navigation Styling */
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
    
    /* Modal Styling */
    .modal-bg { background: rgba(0, 0, 0, 0.6); }
    .modal-container {
      background: #1F2937;
      border-radius: 1rem;
      padding: 2rem;
      max-width: 400px;
      width: 90%;
    }
    .modal-button {
      transition: background 0.3s ease;
    }
    .modal-button:hover { opacity: 0.9; }
  </style>
</head>
<body class="bg-gray-900 text-white">
  <!-- Navigation Bar -->
  <nav class="bg-gray-800 bg-opacity-90 backdrop-blur-md shadow-lg fixed w-full top-0 z-50 animate-fade-slide">
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
  
  <main class="max-w-6xl mx-auto px-6 mt-24 animate-fade-slide">
    <h1 class="text-4xl font-bold text-blue-400 mb-6 text-center">Goal Setting</h1>
    <!-- Goal Submission Form -->
    <div class="glassmorphism p-6 rounded-lg hover-scale mb-6">
      <h2 class="text-2xl font-semibold mb-4">Set a New Goal</h2>
      <?php if($goalError): ?>
        <p class="text-red-500 mb-4"><?= htmlspecialchars($goalError) ?></p>
      <?php endif; ?>
      <form method="POST" action="goal-setting.php">
        <div class="mb-4">
          <label for="goal_text" class="block text-gray-300 mb-2">Your Goal:</label>
          <input type="text" id="goal_text" name="goal_text" class="w-full p-2 rounded bg-gray-800 border border-gray-700" placeholder="e.g., Run 5km in under 30 minutes" required>
        </div>
        <div class="mb-4">
          <label for="reminder" class="block text-gray-300 mb-2">Reminder (optional):</label>
          <input type="datetime-local" id="reminder" name="reminder" class="w-full p-2 rounded bg-gray-800 border border-gray-700">
        </div>
        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">Save Goal</button>
      </form>
    </div>
    
    <!-- Grouped Goals Display -->
    <section class="mb-8">
      <h2 class="text-3xl font-bold text-white mb-4">Your Goals</h2>
      
      <!-- Active Goals -->
      <div class="mb-6">
        <h3 class="text-2xl font-semibold text-blue-300 mb-2">Currently Active</h3>
        <?php if(empty($activeGoals)): ?>
          <p class="text-gray-400">No active goals.</p>
        <?php else: ?>
          <?php foreach($activeGoals as $goal): ?>
            <div class="goal-card border-pending goal-item" 
                 data-goal-id="<?= $goal['goal_id'] ?>" 
                 data-reminder="<?= $goal['reminder'] ?>" 
                 data-status="<?= $goal['status'] ?>" 
                 data-processed="false">
              <p class="text-lg"><?= htmlspecialchars($goal['goal_text']) ?></p>
              <?php if ($goal['reminder']): ?>
                <p class="text-sm text-gray-400">Reminder set for: <?= date('d-m-Y H:i', strtotime($goal['reminder'])) ?></p>
              <?php endif; ?>
              <p class="text-xs text-gray-500">Created on: <?= date('d-m-Y', strtotime($goal['created_at'])) ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      
      <!-- Completed Goals -->
      <div class="mb-6">
        <h3 class="text-2xl font-semibold text-green-400 mb-2">Completed</h3>
        <?php if(empty($completedGoals)): ?>
          <p class="text-gray-400">No completed goals.</p>
        <?php else: ?>
          <?php foreach($completedGoals as $goal): ?>
            <div class="goal-card border-completed" data-goal-id="<?= $goal['goal_id'] ?>">
              <p class="text-lg"><?= htmlspecialchars($goal['goal_text']) ?></p>
              <?php if ($goal['reminder']): ?>
                <p class="text-sm text-gray-400">Reminder was set for: <?= date('d-m-Y H:i', strtotime($goal['reminder'])) ?></p>
              <?php endif; ?>
              <p class="text-xs text-gray-500">Created on: <?= date('d-m-Y', strtotime($goal['created_at'])) ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      
      <!-- Failed Goals -->
      <div class="mb-6">
        <h3 class="text-2xl font-semibold text-red-400 mb-2">Failed</h3>
        <?php if(empty($failedGoals)): ?>
          <p class="text-gray-400">No failed goals.</p>
        <?php else: ?>
          <?php foreach($failedGoals as $goal): ?>
            <div class="goal-card border-failed" data-goal-id="<?= $goal['goal_id'] ?>">
              <p class="text-lg"><?= htmlspecialchars($goal['goal_text']) ?></p>
              <?php if ($goal['reminder']): ?>
                <p class="text-sm text-gray-400">Reminder was set for: <?= date('d-m-Y H:i', strtotime($goal['reminder'])) ?></p>
              <?php endif; ?>
              <p class="text-xs text-gray-500">Created on: <?= date('d-m-Y', strtotime($goal['created_at'])) ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      
    </section>
    
    <!-- AI-Powered Recommendations Section -->
    <div class="glassmorphism p-6 rounded-lg hover-scale mt-6">
      <h2 class="text-2xl font-semibold mb-4">AI-Powered Recommendations</h2>
      <p class="text-gray-300 mb-4">
        Based on your recent performance, we recommend adjusting your workouts and nutrition. Check back later for personalized tips!
      </p>
      <a href="recommendations.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
        View Recommendations
      </a>
    </div>
  </main>
  
  <!-- Modal Popup for Expired Goals -->
  <div id="goalModal" class="fixed inset-0 flex items-center justify-center hidden z-50">
    <div class="modal-bg absolute inset-0"></div>
    <div class="modal-container relative text-center">
      <h2 class="text-2xl font-bold mb-4" id="modalTitle">Time's Up!</h2>
      <p class="mb-6" id="modalText">Did you complete this goal?</p>
      <div class="flex justify-center space-x-4">
        <button id="modalYes" class="modal-button bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">Yes</button>
        <button id="modalNo" class="modal-button bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">No</button>
      </div>
    </div>
  </div>
  <script>
    // Mobile Menu Toggle
    document.getElementById('menu-toggle').addEventListener('click', function() {
      const menu = document.getElementById('mobile-menu');
      menu.classList.toggle('hidden');
    });
    document.addEventListener('click', function(event) {
      const menu = document.getElementById('mobile-menu');
      const button = document.getElementById('menu-toggle');
      if (!menu.contains(event.target) && !button.contains(event.target)) {
          menu.classList.add('hidden');
      }
    });
    
    // Modal and Goal Completion Logic
    let currentGoalElement = null;
    
    // Function to show modal for a specific goal
    function showModal(goalElement) {
      currentGoalElement = goalElement;
      const goalText = goalElement.querySelector('p.text-lg').innerText;
      document.getElementById('modalTitle').innerText = "Time's Up for:";
      document.getElementById('modalText').innerText = goalText + "\nDid you complete this goal?";
      document.getElementById('goalModal').classList.remove('hidden');
    }
    
    // Function to update the goal UI based on status
    function updateGoalUI(goalElement, status) {
      if(status === 'completed') {
        goalElement.classList.remove('border-pending');
        goalElement.classList.add('border-completed');
        // Trigger confetti celebration
        confetti({
          particleCount: 100,
          spread: 70,
          origin: { y: 0.6 }
        });
      } else if(status === 'failed') {
        goalElement.classList.remove('border-pending');
        goalElement.classList.add('border-failed');
      }
      // Update the data-status attribute
      goalElement.setAttribute('data-status', status);
    }
    
    // Function to send an AJAX request to update goal status
    function updateGoalStatus(goalId, status) {
      fetch('update_goal_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ goal_id: goalId, status: status })
      })
      .then(response => response.json())
      .then(data => {
        if(data.success) {
          updateGoalUI(currentGoalElement, status);
        } else {
          alert("Error updating goal status: " + data.error);
        }
      })
      .catch(error => {
        console.error('Error:', error);
      });
    }
    
    // Modal button event listeners
    document.getElementById('modalYes').addEventListener('click', function() {
      if(currentGoalElement) {
        const goalId = currentGoalElement.getAttribute('data-goal-id');
        updateGoalStatus(goalId, 'completed');
      }
      document.getElementById('goalModal').classList.add('hidden');
    });
    
    document.getElementById('modalNo').addEventListener('click', function() {
      if(currentGoalElement) {
        const goalId = currentGoalElement.getAttribute('data-goal-id');
        updateGoalStatus(goalId, 'failed');
      }
      document.getElementById('goalModal').classList.add('hidden');
    });
    
    // Real-time check for expired goals (every 10 seconds)
    setInterval(function() {
      const goalItems = document.querySelectorAll('.goal-item');
      goalItems.forEach(item => {
        const status = item.getAttribute('data-status');
        // Only check active (pending) goals that haven't been processed yet.
        if(status === 'pending' && item.getAttribute('data-processed') === "false") {
          const reminder = item.getAttribute('data-reminder');
          if(reminder) {
            const reminderTime = new Date(reminder);
            const now = new Date();
            if(now >= reminderTime) {
              // Mark as processed so we don't trigger repeatedly.
              item.setAttribute('data-processed', "true");
              showModal(item);
            }
          }
        }
      });
    }, 10000);
  </script>
</body>
</html>
