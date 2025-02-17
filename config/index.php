<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
  echo "<h1>Welcome, " . htmlspecialchars($_SESSION['username']) . "!</h1>";
  echo "<p>Registration successful!</p>";

  // Display user details from database
  try {
    $stmt = $conn->prepare("SELECT username, email, created_at FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
      echo "<h2>Your Account Details:</h2>";
      echo "<p>Username: " . htmlspecialchars($user['username']) . "</p>";
      echo "<p>Email: " . htmlspecialchars($user['email']) . "</p>";
      echo "<p>Joined: " . $user['created_at'] . "</p>";
    }
  } catch (PDOException $e) {
    echo "<p>Error retrieving user details.</p>";
  }
} else {
  echo "<h1>Welcome!</h1>";
  echo "<p>Please <a href='auth/login.php'>login</a> or <a href='auth/register.php'>register</a>.</p>";
}
