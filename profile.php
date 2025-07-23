<?php

$servername = "localhost";
$username = "root"; 
$password = "";     
$dbname = "tracker";

session_start();
$user_id = $_SESSION['user_id']; 

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user details
$sql_user = "SELECT username, date_joined FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();

// Fetch most recent weight
$sql_weight = "SELECT weight, log_date FROM body_weight_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT 1";
$stmt_weight = $conn->prepare($sql_weight);
$stmt_weight->bind_param("i", $user_id);
$stmt_weight->execute();
$result_weight = $stmt_weight->get_result();
$weight = $result_weight->fetch_assoc();

// Fetch start weight (oldest weight)
$sql_start_weight = "SELECT weight, log_date FROM body_weight_logs WHERE user_id = ? ORDER BY log_date ASC LIMIT 1";
$stmt_start_weight = $conn->prepare($sql_start_weight);
$stmt_start_weight->bind_param("i", $user_id);
$stmt_start_weight->execute();
$result_start_weight = $stmt_start_weight->get_result();
$start_weight = $result_start_weight->fetch_assoc();

// Fetch the user's before/after photos (if any) without a LIMIT
$sql_photos = "SELECT before_photo, after_photo, upload_date
               FROM progress_photos
               WHERE user_id = ?";

$stmt_photos = $conn->prepare($sql_photos);
$stmt_photos->bind_param("i", $user_id);
$stmt_photos->execute();
$result_photos = $stmt_photos->get_result();

$latestPhotos = $result_photos->fetch_assoc();
$stmt_photos->close();

$stmt_user->close();
$stmt_weight->close();
$stmt_start_weight->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Fitness Tracker | Profile</title>
    <link rel="shortcut icon" href="assets/Logo_Fitness.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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

      .glassmorphism {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        padding: 20px;
      }
      /* Smooth Hover Effect */
      .nav-link {
        position: relative;
        color: white;
        font-size: 16px;
        font-weight: 500;
        transition: color 0.3s ease-in-out;
      }
      .nav-link:hover {
        color: #60a5fa;
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

      /* Mobile Menu Links */
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

      .gallery {
        --g: 8px; 
        display: grid;
        clip-path: inset(1px); 
        justify-content: center; 
        margin-top: 1rem; 
      }

      .gallery > img {
        --_p: calc(-1 * var(--g));
        grid-area: 1 / 1;
        width: 350px; 
        aspect-ratio: 1; 
        cursor: pointer;
        transition: 0.4s 0.1s;
        object-fit: cover;
        border-radius: 0.5rem;
      }

      /* Clip paths for the first (before) and second (after) images */
      .gallery > img:first-child {
        clip-path: polygon(0 0, calc(100% + var(--_p)) 0, 0 calc(100% + var(--_p)));
      }
      .gallery > img:last-child {
        clip-path: polygon(100% 100%, 100% calc(0% - var(--_p)), calc(0% - var(--_p)) 100%);
      }

      /* Hover states */
      .gallery:hover > img:last-child,
      .gallery:hover > img:first-child:hover {
        --_p: calc(50% - var(--g));
      }
      .gallery:hover > img:first-child,
      .gallery:hover > img:first-child:hover + img {
        --_p: calc(-50% - var(--g));
      }

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

<div class="max-w-6xl mx-auto pt-20 px-4 flex flex-col lg:flex-row gap-8">
  <!-- Left Column (Profile Info) -->
  <div class="bg-gray-800 rounded-lg shadow-lg p-8 w-full lg:w-2/3 fade-in">
    <h1 class="text-3xl font-bold text-center mb-8">
      <span class="text-blue-400"><?= htmlspecialchars($user['username']); ?>'s</span> Profile
    </h1>

    <div class="space-y-6">
      <!-- Username -->
      <div class="glassmorphism">
        <label class="block text-blue-400 text-sm font-medium">Username:</label>
        <p class="text-lg font-semibold mt-1"><?= htmlspecialchars($user['username']); ?></p>
      </div>

      <!-- Weight Information -->
      <?php if ($weight) : ?>
        <div class="glassmorphism">
          <label class="block text-blue-400 text-sm font-medium">Most Recent Weight:</label>
          <p class="text-lg font-semibold mt-1">
            <?= htmlspecialchars($weight['weight']); ?> kg (logged on <?= htmlspecialchars($weight['log_date']); ?>)
          </p>
        </div>
      <?php else : ?>
        <div class="glassmorphism">
          <p class="text-lg font-semibold text-red-500">No weight data logged yet.</p>
        </div>
      <?php endif; ?>

      <!-- Date Joined -->
      <div class="glassmorphism">
        <label class="block text-blue-400 text-sm font-medium">Date Joined:</label>
        <p class="text-lg font-semibold mt-1">
          <?= htmlspecialchars(date("F j, Y", strtotime($user['date_joined']))); ?>
        </p>
      </div>

      <!-- Update Username / Password Section -->
      <div class="glassmorphism">
        <h2 class="text-xl font-medium text-blue-400">Update Profile</h2>
        <form action="update_profile.php" method="POST" class="mt-4 space-y-4">
          <input 
            type="text" 
            name="username" 
            placeholder="New Username" 
            class="w-full bg-gray-700 text-white p-3 rounded-md transition-all duration-300 scale-hover" 
            required
          >
          <input 
            type="password" 
            name="password" 
            placeholder="New Password" 
            class="w-full bg-gray-700 text-white p-3 rounded-md transition-all duration-300 scale-hover"
          >
          <button 
            type="submit" 
            class="w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-md transition-all scale-hover"
          >
            Update Profile
          </button>
        </form>
      </div>

      <!-- Delete Account -->
      <form action="delete_profile.php" method="POST" onsubmit="return confirmDelete()">
        <button 
          type="submit" 
          class="w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-md transition-all mt-4 scale-hover"
        >
          Delete Account
        </button>
      </form>
      <script>
        function confirmDelete() {
          return confirm("Are you sure you want to delete your account? All your data will be permanently deleted.");
        }
      </script>
    </div>
  </div>

  <!-- Right Column (Before & After) -->
  <div class="flex flex-col w-full lg:w-1/3 gap-8">

<!-- Upload Form Section -->
<div class="glassmorphism p-4 fade-in">
  <h2 class="text-xl font-medium text-blue-400">Before & After Photos</h2>
  <form 
    action="upload_photo.php" 
    method="POST" 
    enctype="multipart/form-data" 
    class="mt-4 space-y-4"
  >
    <div>
      <label class="block text-sm text-blue-300 mb-1" for="beforePhoto">Before Photo:</label>
      <input 
        type="file" 
        name="beforePhoto" 
        accept="image/*"
        class="bg-gray-700 text-white p-2 rounded-md"
      />
    </div>
    <div>
      <label class="block text-sm text-blue-300 mb-1" for="afterPhoto">After Photo:</label>
      <input 
        type="file" 
        name="afterPhoto" 
        accept="image/*"
        class="bg-gray-700 text-white p-2 rounded-md"
      />
    </div>

    <button 
      type="submit"
      class="w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-md transition-all scale-hover"
    >
      Upload Photos
    </button>
  </form>
</div>
    <!-- Display Latest Photos Section -->
    <div class="glassmorphism p-4 fade-in">
      <h2 class="text-xl font-medium text-blue-400">My Latest Before & After</h2>

      <?php if ($latestPhotos) : ?>
        <!-- The gallery container -->
        <div class="gallery">
          <img 
            src="uploads/<?= htmlspecialchars($latestPhotos['before_photo']); ?>" 
            alt="Before Photo" 
          />
          <img 
            src="uploads/<?= htmlspecialchars($latestPhotos['after_photo']); ?>" 
            alt="After Photo" 
          />
        </div>
        <p class="text-sm text-gray-400 mt-2">
          Uploaded on <?= date("F j, Y", strtotime($latestPhotos['upload_date'])); ?>
        </p>
      <?php else : ?>
        <p class="text-gray-400 mt-4">You haven't uploaded any before & after photos yet.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

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
