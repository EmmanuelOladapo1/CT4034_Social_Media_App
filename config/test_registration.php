<?php
session_start();
require_once 'database.php';

// Test registration with new user
$test_user = [
  'username' => 'testuser2',
  'email' => 'test2@example.com',
  'password' => 'Test12345'
];

try {
  // Check if user exists
  $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
  $stmt->execute([$test_user['email'], $test_user['username']]);
  echo "User exists check: " . $stmt->fetchColumn() . "\n";

  // Test password hashing
  $hashed = password_hash($test_user['password'], PASSWORD_DEFAULT);
  echo "Password hash working: " . ($hashed !== false ? "Yes" : "No") . "\n";

  // Test verification
  echo "Password verify working: " . (password_verify($test_user['password'], $hashed) ? "Yes" : "No") . "\n";
} catch (PDOException $e) {
  echo "Error: " . $e->getMessage();
}
