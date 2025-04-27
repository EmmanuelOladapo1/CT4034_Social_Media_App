<?php
session_start();
require_once 'config/database.php';

// Check if admin account exists, create if not
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin' AND role = 'admin'");
$stmt->execute();
if ($stmt->fetchColumn() == 0) {
  // Create admin account with password "password"
  $hashedPassword = password_hash('password', PASSWORD_DEFAULT);
  $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, created_at)
                         VALUES ('admin', 'admin@example.com', ?, 'admin', NOW())");
  $stmt->execute([$hashedPassword]);
}

// Log in as admin
$stmt = $conn->prepare("SELECT * FROM users WHERE username = 'admin' AND role = 'admin'");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin) {
  $_SESSION['user_id'] = $admin['user_id'];
  $_SESSION['username'] = $admin['username'];
  $_SESSION['role'] = $admin['role'];

  header("Location: admin_dashboard.php");
  exit();
} else {
  header("Location: login.php?error=Admin account not found");
  exit();
}
