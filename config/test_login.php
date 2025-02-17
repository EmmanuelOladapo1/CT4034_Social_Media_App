<?php
// config/test_login.php
session_start(); // Add this at the top
require_once 'database.php';

echo "<h2>Login System Test</h2>";

try {
  // Test 1: Check if users table has records
  $stmt = $conn->query("SELECT COUNT(*) as total FROM users");
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  echo "<p>Test 1 - Users in database: " . $result['total'] . "</p>";

  // Test 2: Check if test user exists
  $email = "test@example.com";
  $stmt = $conn->prepare("SELECT username, email, is_admin FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($user) {
    echo "<p style='color:green'>Test 2 - Test user found: " . htmlspecialchars($user['username']) . "</p>";
  } else {
    echo "<p style='color:red'>Test 2 - Test user not found</p>";
  }

  // Test 3: Check session configuration
  if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p style='color:green'>Test 3 - Sessions are working</p>";

    // Additional session info
    echo "<p>Current session details:</p>";
    if (isset($_SESSION['user_id'])) {
      echo "<p>Logged in user ID: " . $_SESSION['user_id'] . "</p>";
      echo "<p>Username: " . $_SESSION['username'] . "</p>";
    } else {
      echo "<p>No user currently logged in</p>";
    }
  } else {
    echo "<p style='color:red'>Test 3 - Sessions not working</p>";
  }
} catch (PDOException $e) {
  echo "<p style='color:red'>Database Error: " . $e->getMessage() . "</p>";
}

// Display test login form
echo "
<h3>Test Login Form</h3>
<form method='post' action='../auth/login_process.php'>
    <input type='email' name='email' placeholder='Email' value='test@example.com'><br>
    <input type='password' name='password' placeholder='Password' value='Test12345'><br>
    <button type='submit'>Test Login</button>
</form>
";
