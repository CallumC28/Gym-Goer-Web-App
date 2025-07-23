<?php
session_start();

if (!isset($_SESSION['loggedin'])) {
    header('Location: index.html');
    exit;
}

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "tracker";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

$exerciseOptions = [];
$query = "SELECT exercise_name FROM list";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $exerciseOptions[] = $row['exercise_name'];
    }
} else {
    echo "Error fetching exercises: " . $conn->error;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['template_name'])) {
    $template_name = $_POST['template_name'];
    $template_desc = $_POST['template_description'];

    // Insert into workout_templates
    $sql = "INSERT INTO workout_templates (user_id, template_name, template_description)
            VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $template_name, $template_desc);
    $stmt->execute();
    $new_template_id = $stmt->insert_id;
    $stmt->close();

    // Insert exercises for this template
    if (!empty($_POST['exercise_names'])) {
        foreach ($_POST['exercise_names'] as $index => $exercise_name) {
            $exercise_name = trim($exercise_name);
            if ($exercise_name === '') {
                continue;
            }
            
            $sets_val = intval($_POST['sets'][$index]);
            $reps_val = intval($_POST['reps'][$index]);
            $rest_val = intval($_POST['rest_seconds'][$index]);

            $sql2 = "INSERT INTO workout_template_exercises 
                     (template_id, exercise_name, sets, reps, rest_seconds)
                     VALUES (?, ?, ?, ?, ?)";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("isiii", $new_template_id, $exercise_name, $sets_val, $reps_val, $rest_val);
            $stmt2->execute();
            $stmt2->close();
        }
    }

    // Redirect or just set a success indicator
    header("Location: workout_templates.php?success=1");
    exit;
}

// Fetch existing templates (and related exercises) for this user
$templates = [];
$sqlTemplates = "SELECT * FROM workout_templates WHERE user_id=? ORDER BY created_at DESC";
$stmtT = $conn->prepare($sqlTemplates);
$stmtT->bind_param("i", $user_id);
$stmtT->execute();
$resultT = $stmtT->get_result();
while ($row = $resultT->fetch_assoc()) {
    $templates[] = $row;
}
$stmtT->close();

// Build an array that groups the exercises under each template
$exercises_by_template = [];
if (!empty($templates)) {
    $template_ids = array_column($templates, 'template_id');
    $in_list = implode(',', array_map('intval', $template_ids));

    // Only run this if we actually have template IDs
    if (!empty($in_list)) {
        $sqlEx = "SELECT * FROM workout_template_exercises 
                  WHERE template_id IN ($in_list)
                  ORDER BY template_exercise_id ASC";
        $resEx = $conn->query($sqlEx);
        while ($exRow = $resEx->fetch_assoc()) {
            $tid = $exRow['template_id'];
            if (!isset($exercises_by_template[$tid])) {
                $exercises_by_template[$tid] = [];
            }
            $exercises_by_template[$tid][] = $exRow;
        }
    }
}

$conn->close();

// Prepare a structure in JSON to be used by the modal
$templateDataForJS = [];
foreach ($templates as $t) {
    $tid  = $t['template_id'];
    $exs  = isset($exercises_by_template[$tid]) ? $exercises_by_template[$tid] : [];

    // Build a sub-array of exercises
    $exerciseList = [];
    foreach ($exs as $ex) {
        $exerciseList[] = [
            'exercise_name' => $ex['exercise_name'],
            'sets' => $ex['sets'],
            'reps' => $ex['reps'],
            'rest_seconds' => $ex['rest_seconds']
        ];
    }

    $templateDataForJS[] = [
        'template_id' => $tid,
        'template_name' => $t['template_name'],
        'template_description' => $t['template_description'],
        'created_at' => $t['created_at'],
        'exercises' => $exerciseList
    ];
}
$templateDataForJS = json_encode($templateDataForJS);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Workout Templates</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
  <style>
  @keyframes fadeSlide {
        0% { opacity: 0; transform: translateY(-10px); }
        100% { opacity: 1; transform: translateY(0); }
      }
      .animate-fade-slide { animation: fadeSlide 0.6s ease-out forwards; }
      .glassmorphism {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

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

      /*  Mobile Menu Links */
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
    /* Modal backdrop */
    .modal-bg {
      background: rgba(0, 0, 0, 0.7);
    }

    body { font-family: 'Poppins', sans-serif; }
  </style>
</head>
<body class="bg-gray-900 text-white fade-in">

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

<main class="max-w-6xl mx-auto px-6 mt-24 opacity-0 animate-fade-slide">
      <div class="text-center">
        <h1 class="text-5xl font-bold text-blue-400">
          <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES) ?>'s Workout Plans
        </h1>
        <p class="text-lg text-gray-400 mt-4">Let’s crush your fitness goals today! 💪</p>
      </div>

  <?php if (isset($_GET['success'])): ?>
    <div id="successMessage" class="bg-green-700 text-white p-3 rounded mb-4">
      New workout template created successfully!
    </div>
    <script>
      // Hide the success message after 3 seconds (3000 ms)
      setTimeout(() => {
        const msg = document.getElementById('successMessage');
        if (msg) {
          msg.style.display = 'none';
        }
      }, 3000);
    </script>
  <?php endif; ?>

  <!-- Template Creation Form -->
  <div class="mb-8 p-6 rounded bg-gray-800 shadow-lg glassmorphism p-6">
    <h2 class="text-xl font-semibold mb-4 text-blue-400">Create a New Template</h2>
    <form method="POST" class="space-y-4">
      <div>
        <label class="block mb-1 ">Template Name</label>
        <input type="text" name="template_name" 
               class="w-full p-2 rounded bg-gray-700 text-white" required>
      </div>
      <div>
        <label class="block mb-1">Template Description (optional)</label>
        <textarea name="template_description" rows="2"
                  class="w-full p-2 rounded bg-gray-700 text-white"></textarea>
      </div>

      <!-- Dynamic Exercises Section -->
      <div id="exercises-container" class="space-y-4">
        <div class="exercise-group border border-gray-600 p-4 rounded">
          <label class="block mb-1">Exercise Name</label>
          <select name="exercise_names[]" class="w-full p-2 rounded bg-gray-700 text-white" required>
            <option value="" disabled selected>-- Choose an exercise --</option>
            <?php foreach ($exerciseOptions as $exName): ?>
              <option value="<?= htmlspecialchars($exName) ?>"><?= htmlspecialchars($exName) ?></option>
            <?php endforeach; ?>
          </select>

          <div class="flex space-x-2 mt-4">
            <div>
              <label>Sets</label>
              <input type="number" name="sets[]" value="3" min="1" 
                     class="w-full p-2 rounded bg-gray-700 text-white mt-1">
            </div>
            <div>
              <label>Reps</label>
              <input type="number" name="reps[]" value="8" min="1"
                     class="w-full p-2 rounded bg-gray-700 text-white mt-1">
            </div>
            <div>
              <label>Rest (secs)</label>
              <input type="number" name="rest_seconds[]" value="60" min="0"
                     class="w-full p-2 rounded bg-gray-700 text-white mt-1">
            </div>
          </div>
        </div>
      </div>

      <button type="button" onclick="addExerciseFields()" 
              class="bg-blue-600 px-4 py-2 rounded hover:bg-blue-500 mt-2">
        + Add Another Exercise
      </button>
      
      <div>
        <button type="submit" 
                class="bg-green-600 px-4 py-2 rounded hover:bg-green-500 mt-4">
          Save Template
        </button>
      </div>
    </form>
  </div>

  <!-- Existing Templates List -->
  <div>
    <h2 class="text-xl text-blue-400 font-semibold mb-4 text-center">Your Templates</h2>
    <?php 
      // If there are no templates
      if (empty($templates)): 
    ?>
      <p class="text-gray-300 text-center">No templates created yet.</p>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($templates as $tmpl): 
          $tid       = $tmpl['template_id'];
          $exercises = isset($exercises_by_template[$tid]) ? $exercises_by_template[$tid] : [];
        ?>
          <div class="bg-gray-800 p-4 rounded shadow relative">
            <h3 class="text-lg font-bold mb-2">
              <?= htmlspecialchars($tmpl['template_name']) ?>
            </h3>
            <?php if (!empty($tmpl['template_description'])): ?>
              <p class="text-gray-400 mb-2"><?= htmlspecialchars($tmpl['template_description']) ?></p>
            <?php endif; ?>

            <p class="text-sm text-gray-500 mb-4">Created: <?= htmlspecialchars($tmpl['created_at']) ?></p>
            
            <?php if (empty($exercises)): ?>
              <p class="text-sm text-gray-400 italic">No exercises in this template.</p>
            <?php else: ?>
              <ul class="text-sm mb-4">
                <?php 
                // Show just a preview of up to 2 exercises
                $preview = array_slice($exercises, 0, 2);
                foreach ($preview as $exRow): ?>
                  <li class="mb-1">
                    <strong><?= htmlspecialchars($exRow['exercise_name']) ?></strong>
                    (<?= $exRow['sets'] ?> sets x <?= $exRow['reps'] ?> reps)
                  </li>
                <?php endforeach; ?>
                <?php if (count($exercises) > 2): ?>
                  <li class="text-gray-400 italic">...and more</li>
                <?php endif; ?>
              </ul>
            <?php endif; ?>

            <!-- Button to open modal -->
            <button type="button"
                    class="bg-blue-600 px-3 py-2 rounded text-white hover:bg-blue-500 text-sm"
                    onclick="showTemplateModal(<?= $tid ?>)">
              View Details
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- MODAL (Hidden by default) -->
<div id="templateModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-bg glassmorphism p-6">
  <div class="bg-gray-800 rounded-lg p-6 relative max-w-xl w-full mx-4">
    <button class="absolute top-2 right-2 text-white text-xl" onclick="closeTemplateModal()">
      <i class="ri-close-line"></i>
    </button>
    <h3 id="modalTemplateName" class="text-2xl font-bold mb-2"></h3>
    <p id="modalTemplateDescription" class="text-gray-300 mb-4"></p>
    <p id="modalCreatedAt" class="text-sm text-gray-500 mb-4"></p>
    <div id="modalExercises" class="space-y-2">
    </div>
  </div>
</div>
<script>
// Toggle mobile menu on click
const menuToggle = document.getElementById('menu-toggle');
const mobileMenu = document.getElementById('mobile-menu');
if (menuToggle && mobileMenu) {
  menuToggle.addEventListener('click', () => {
    mobileMenu.classList.toggle('hidden');
  });
  // Close mobile menu when clicking outside
  document.addEventListener('click', (event) => {
    if (!mobileMenu.contains(event.target) && !menuToggle.contains(event.target)) {
      mobileMenu.classList.add('hidden');
    }
  });
}

// Data from PHP for the modal (templates + exercises)
const templateData = JSON.parse('<?php echo $templateDataForJS; ?>');

function addExerciseFields() {
  const container = document.getElementById('exercises-container');
  const templateHTML = `
    <div class="exercise-group border border-gray-600 p-4 rounded mt-4">
      <label class="block mb-1">Exercise Name</label>
      <select name="exercise_names[]" class="w-full p-2 rounded bg-gray-700 text-white" required>
        <option value="" disabled selected>-- Choose an exercise --</option>
        <?php foreach ($exerciseOptions as $exName): ?>
          <option value="<?= htmlspecialchars($exName) ?>"><?= htmlspecialchars($exName) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="flex space-x-2 mt-4">
        <div>
          <label>Sets</label>
          <input type="number" name="sets[]" value="3" min="1"
                 class="w-full p-2 rounded bg-gray-700 text-white mt-1">
        </div>
        <div>
          <label>Reps</label>
          <input type="number" name="reps[]" value="8" min="1"
                 class="w-full p-2 rounded bg-gray-700 text-white mt-1">
        </div>
        <div>
          <label>Rest (secs)</label>
          <input type="number" name="rest_seconds[]" value="60" min="0"
                 class="w-full p-2 rounded bg-gray-700 text-white mt-1">
        </div>
      </div>
    </div>
  `;
  container.insertAdjacentHTML('beforeend', templateHTML);
}

// Show modal with template details
function showTemplateModal(templateId) {
  const modal = document.getElementById('templateModal');
  const templateNameEl = document.getElementById('modalTemplateName');
  const templateDescEl = document.getElementById('modalTemplateDescription');
  const templateCreatedEl = document.getElementById('modalCreatedAt');
  const modalExercisesEl = document.getElementById('modalExercises');

  // Find the template object by ID
  const tmpl = templateData.find(item => item.template_id == templateId);
  if (!tmpl) return;

  // Populate modal fields
  templateNameEl.textContent = tmpl.template_name || 'No Name';
  templateDescEl.textContent = tmpl.template_description || 'No Description';
  templateCreatedEl.textContent = 'Created at: ' + (tmpl.created_at || 'Unknown date');

  // Exercises
  modalExercisesEl.innerHTML = '';
  if (tmpl.exercises && tmpl.exercises.length > 0) {
    tmpl.exercises.forEach(ex => {
      const exDiv = document.createElement('div');
      exDiv.className = 'bg-gray-700 p-3 rounded';
      exDiv.innerHTML = `
        <p><strong>Exercise:</strong> ${ex.exercise_name}</p>
        <p><strong>Sets:</strong> ${ex.sets} | <strong>Reps:</strong> ${ex.reps} | <strong>Rest:</strong> ${ex.rest_seconds}s</p>
      `;
      modalExercisesEl.appendChild(exDiv);
    });
  } else {
    modalExercisesEl.innerHTML = '<p class="text-gray-400">No exercises found.</p>';
  }

  // Display the modal
  modal.classList.remove('hidden');
}

// Close the modal
function closeTemplateModal() {
  const modal = document.getElementById('templateModal');
  modal.classList.add('hidden');
}
</script>
</body>
</html>
