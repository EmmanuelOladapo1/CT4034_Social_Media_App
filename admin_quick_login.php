<?php
session_start();
require_once 'config/database.php';

// Check if admin account exists, create if not
$stmt = $conn->prepare("SELECT COUNT(*) FROM admins WHERE username = 'admin'");
$stmt->execute();
if ($stmt->fetchColumn() == 0) {
  // Create admin account with password "admin123"
  $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
  $stmt = $conn->prepare("INSERT INTO admins (username, email, password, full_name, created_at)
                         VALUES ('admin', 'admin@example.com', ?, 'Administrator', NOW())");
  $stmt->execute([$hashedPassword]);
  echo "Admin account created successfully.<br>";
}

// Log in as admin
$stmt = $conn->prepare("SELECT * FROM admins WHERE username = 'admin'");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin) {
  $_SESSION['admin_id'] = $admin['admin_id'];
  $_SESSION['admin_username'] = $admin['username'];
  $_SESSION['is_admin'] = true;

  // Update last login time
  $stmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?");
  $stmt->execute([$admin['admin_id']]);

  header("Location: admin_dashboard.php");
  exit();
} else {
  header("Location: admin_login.php?error=Admin account not found");
  exit();
}
