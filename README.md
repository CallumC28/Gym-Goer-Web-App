# GymGoer Fitness Tracker Web App

A full-stack web application built to help gym-goers log workouts, track progress, and receive AI-powered exercise predictions. Designed to be simple, responsive, and effective for users aiming to monitor their fitness journey.

## Key Features

- 🏋️ Log exercises (sets, reps, weight) and body weight
- 📊 Visualise progress with interactive charts
- 🧠 AI predictions using Support Vector Regression (SVR) with Phpml
- 🏆 Leaderboard for top lifts across users
- ☁️ Weather API intergration for planning outdoor activities
- 🔐 Secure user registration and login system
- 🌐 Clean, responsive UI suitable for all devices

## 🛠️ Tech Stack

- **Frontend:** HTML, CSS/TailWind, JavaScript
- **Backend:** PHP (vanilla PHP)
- **Machine Learning:** Phpml library (SVR, Linear Regression)
- **Database:** MySQL via XAMPP
- **Package Manager:** Composer

## 📦 Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/CallumC28/Gym-Goer-Web-App.git
   cd Gym-Goer-Web-App

2. ## Install Dependencies with Composer ##
   Make sure you have Composer installed on your system.
   ```bash
   composer install

3. ## Set Up the MySQL Database ##
   Launch XAMPP and start Apache and MySQL.
   
   Open phpMyAdmin and create a new database (e.g. tracker).
   
   Import the SQL schema file located in the /database/ directory.
   
   Update database credentials in config.php to match your XAMPP setup.
   
   4. Run the App
   Place the project folder inside your htdocs/ directory (XAMPP).
   
   In your browser, go to:
   http://localhost/Gym-Goer-Web-App **(Will be different for you)**

## 🖼️ Screenshots

### Dashboard
![Dashboard](screenshots/dashboard.png) 

### BMR Calculator
![BMR Calculator](screenshots/Screenshot(9).png)

### Weather API intergration
![Progress Chart](screenshots/Screenshot(8).png)

### AI-Powered Prediction
![AI Prediction](screenshots/Screenshot(10).png)

### AI-Powered Prediction - choose which model you prefer
![AI](screenshots/AI_Modal.png)

### Progress Page
![Progress](screenshots/progress.png)

### LeaderBoard
![LeaderBoard](screenshots/leaderboard.png)

### Profile Page
![LeaderBoard](screenshots/profile.png)
   
## 📚 Dependencies
 -  **php-ai/php-ml** – Machine learning library for PHP
 -  **Composer** – PHP dependency management

## 🔧 Future Improvements that could be made
 -  **Advanced Analytics:** Introduce weekly/monthly performance summaries or progress trends.
 -  **Smarter AI:** Train models on larger datasets or use more advanced algorithms (e.g. Random Forest, Neural Networks).
 -  **User Notes:** Allow users to add comments or notes to each workout (e.g. "Felt strong today" or "Tried new form").
 -  **Nutrition Tools:** Add a macro tracker to support dieting goals (e.g. Could be done with an API or hard coded dependant on if you want to pull food data from a data base like Fitness Pal does)

