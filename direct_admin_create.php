<?php
// direct_admin_create.php - DELETE THIS FILE AFTER USE!
require_once 'config/database.php';

// Set admin credentials
$username = 'admin';
$password = 'Admin123!';
$email = 'admin@example.com';

// Clear error output
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Admin Account Creation Tool</h1>";
echo "<p>Attempting to create admin account...</p>";

try {
  // First, check if the admins table exists
  $tables = $conn->query("SHOW TABLES LIKE 'admins'")->fetchAll();
  if (count($tables) == 0) {
    echo "<p style='color:red'>Error: 'admins' table does not exist!</p>";
    die();
  }

  // Check table structure
  echo "<p>Checking admins table structure...</p>";
  $columns = $conn->query("SHOW COLUMNS FROM admins")->fetchAll(PDO::FETCH_COLUMN);
  echo "<pre>" . print_r($columns, true) . "</pre>";

  // Delete any existing admin users with this username (start fresh)
  $stmt = $conn->prepare("DELETE FROM admins WHERE username = ?");
  $stmt->execute([$username]);

  // Create a clear password hash
  $hashed_password = password_hash($password, PASSWORD_DEFAULT);

  // Display hash info
  echo "<p>Password: $password</p>";
  echo "<p>Generated hash: $hashed_password</p>";

  // Get the column names to build a proper INSERT statement
  $stmt = $conn->prepare("INSERT INTO admins (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
  $stmt->execute([$username, $email, $hashed_password]);

  // Verify the insertion
  $admin_id = $conn->lastInsertId();
  echo "<p style='color:green'>Success! Admin account created with ID: $admin_id</p>";

  // Test password verification
  $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
  $stmt->execute([$username]);
  $admin = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($admin && password_verify($password, $admin['password'])) {
    echo "<p style='color:green'>Password verification successful!</p>";
  } else {
    echo "<p style='color:red'>Password verification failed!</p>";
  }

  echo "<h2>Login Credentials:</h2>";
  echo "<p>Username: $username</p>";
  echo "<p>Password: $password</p>";
  echo "<p><a href='admin_login.php'>Go to Login Page</a></p>";
  echo "<p style='color:red;font-weight:bold'>IMPORTANT: Delete this file after use!</p>";
} catch (PDOException $e) {
  echo "<p style='color:red'>Database Error: " . $e->getMessage() . "</p>";
}
