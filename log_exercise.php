<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "tracker";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id       = $_SESSION['user_id'];
    $exercise_name = $_POST['exercise_name'] ?? '';
    $sets          = $_POST['sets']          ?? 0;
    $reps          = $_POST['reps']          ?? 0;
    $weight        = $_POST['weight']        ?? null;
    $log_date      = $_POST['log_date']      ?? '';

    // Validate input
    if (empty($exercise_name) || empty($sets) || empty($reps) || empty($log_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO exercise_logs (user_id, exercise_name, sets, reps, weight, log_date) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isiiis", $user_id, $exercise_name, $sets, $reps, $weight, $log_date);

    if (!$stmt->execute()) {
        // If insertion fails
        echo json_encode(['status' => 'error', 'message' => 'Error logging exercise.']);
        $stmt->close();
        $conn->close();
        exit;
    }

    $stmt->close();

    $newlyEarned = []; //track which achievements the user just earned

    $sqlAch = "SELECT * FROM achievements";
    $resultAch = $conn->query($sqlAch);

    while ($ach = $resultAch->fetch_assoc()) {
        $achievement_id = $ach['achievement_id'];
        $title          = $ach['title'];          
        $criteria_type  = $ach['criteria_type'];
        $criteria_value = $ach['criteria_value'];

        // Check if user already earned it
        $checkSQL = "SELECT COUNT(*) AS count_earned 
                     FROM user_achievements 
                     WHERE user_id=? AND achievement_id=?";
        $stmtCheck = $conn->prepare($checkSQL);
        $stmtCheck->bind_param("ii", $user_id, $achievement_id);
        $stmtCheck->execute();
        $rowCheck = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();

        if ($rowCheck['count_earned'] > 0) {
            continue;
        }

        $earned = false;

        
        if ($criteria_type === 'streak') {
            $streakSQL = "
              SELECT DISTINCT DATE(log_date) AS log_day
              FROM exercise_logs
              WHERE user_id = ?
              ORDER BY log_day DESC
              LIMIT 30
            ";
            $stmtStreak = $conn->prepare($streakSQL);
            $stmtStreak->bind_param("i", $user_id);
            $stmtStreak->execute();
            $resStreak = $stmtStreak->get_result();

            $loggedDays = [];
            while ($d = $resStreak->fetch_assoc()) {
                $loggedDays[] = $d['log_day'];
            }
            $stmtStreak->close();

            if (count($loggedDays) >= $criteria_value) {
                $recentDays = array_slice($loggedDays, 0, $criteria_value);
                $timestamps = array_map(fn($day) => strtotime($day), $recentDays);

                $isConsecutive = true;
                for ($i = 0; $i < count($timestamps) - 1; $i++) {
                    if ($timestamps[$i] - $timestamps[$i+1] != 86400) {
                        $isConsecutive = false;
                        break;
                    }
                }
                if ($isConsecutive) {
                    $earned = true;
                }
            }
        }
        
        elseif ($criteria_type === 'weight') {
            if ($weight !== null && floatval($weight) >= floatval($criteria_value)) {
                $earned = true;
            }
        }
        
        elseif ($criteria_type === 'workout_count') {
            $countSQL = "
                SELECT COUNT(*) AS total_logs
                FROM exercise_logs
                WHERE user_id = ?
            ";
            $stmtCount = $conn->prepare($countSQL);
            $stmtCount->bind_param("i", $user_id);
            $stmtCount->execute();
            $countRes = $stmtCount->get_result()->fetch_assoc();
            $stmtCount->close();

            if ($countRes['total_logs'] >= $criteria_value) {
                $earned = true;
            }
        }

        // If newly earned, insert & track
        if ($earned) {
            $insertAchSQL = "
                INSERT INTO user_achievements (user_id, achievement_id) 
                VALUES (?, ?)
            ";
            $stmtInsert = $conn->prepare($insertAchSQL);
            $stmtInsert->bind_param("ii", $user_id, $achievement_id);
            $stmtInsert->execute();
            $stmtInsert->close();

            $newlyEarned[] = $title;
        }
    }

    $conn->close();

    
    //Return JSON with newly earned achievements (if any)
    if (!empty($newlyEarned)) {
        //One or more achievements just earned
        echo json_encode([
            "status" => "success",
            "message" => "Exercise logged successfully!",
            "achievements_earned" => $newlyEarned
        ]);
    } else {
        //No new achievements
        echo json_encode([
            "status" => "success",
            "message" => "Exercise logged successfully!",
            "achievements_earned" => []
        ]);
    }
    exit;
}
?>
