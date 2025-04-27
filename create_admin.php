<?php
// create_admin.php
// Purpose: Creates an admin account with known credentials
// SECURITY NOTE: Delete this file after creating the admin account!

require_once 'config/database.php';

try {
  // Check if admin user already exists
  $stmt = $conn->prepare("SELECT COUNT(*) FROM admins WHERE username = 'admin'");
  $stmt->execute();
  $count = $stmt->fetchColumn();

  if ($count > 0) {
    echo "<p>Admin account already exists!</p>";
    echo "<p>You can login with:</p>";
    echo "<p>Username: admin</p>";
    echo "<p>Password: Admin123!</p>";
  } else {
    // Create admin user with known password
    $username = 'admin';
    $password = 'Admin123!';
    $email = 'admin@example.com';
    $full_name = 'Administrator';

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert the admin
    $stmt = $conn->prepare("INSERT INTO admins (username, email, password, full_name, created_at)
                             VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$username, $email, $hashed_password, $full_name]);

    echo "<p>Admin account created successfully!</p>";
    echo "<p>You can now login with:</p>";
    echo "<p>Username: admin</p>";
    echo "<p>Password: Admin123!</p>";
    echo "<p style='color: red; font-weight: bold;'>IMPORTANT: Delete this file from your server for security!</p>";
  }
} catch (PDOException $e) {
  echo "Error: " . $e->getMessage();
}
