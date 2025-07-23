CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    date_joined TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE body_weight_logs (
    user_id INT,
    weight DECIMAL(5, 2) NOT NULL,
    log_date DATE NOT NULL,
    PRIMARY KEY (user_id, log_date),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE exercise_logs (
    user_id INT,
    exercise_name VARCHAR(100) NOT NULL,
    sets INT NOT NULL,
    reps INT NOT NULL,
    weight DECIMAL(5, 2),
    log_date DATE NOT NULL,
    PRIMARY KEY (user_id, exercise_name, log_date),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE workout_templates (
  template_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  template_name VARCHAR(100) NOT NULL,
  template_description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `workout_template_exercises` (
  `template_exercise_id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `exercise_name` VARCHAR(100) NOT NULL,
  `sets` INT NOT NULL DEFAULT 3,
  `reps` INT NOT NULL DEFAULT 8,
  `rest_seconds` INT NOT NULL DEFAULT 60
) ENGINE=InnoDB;


CREATE TABLE workout_template_exercises (
  template_exercise_id INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT NOT NULL,
  exercise_name VARCHAR(100) NOT NULL,
  sets INT NOT NULL DEFAULT 3,
  reps INT NOT NULL DEFAULT 8,
  rest_seconds INT NOT NULL DEFAULT 60
);

CREATE TABLE `achievements` (
  `achievement_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `criteria_type` VARCHAR(50) NOT NULL,  
  `criteria_value` INT NOT NULL,   
  `badge_icon` VARCHAR(100) DEFAULT 'default.png'
);

CREATE TABLE `user_achievements` (
  `user_achievement_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `achievement_id` INT NOT NULL,
  `date_earned` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE progress_photos (
    user_id INT PRIMARY KEY,
    before_photo VARCHAR(255) DEFAULT NULL,
    after_photo VARCHAR(255) DEFAULT NULL,
    upload_date DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_goals (
    goal_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    goal_text VARCHAR(255) NOT NULL,
    reminder DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);
