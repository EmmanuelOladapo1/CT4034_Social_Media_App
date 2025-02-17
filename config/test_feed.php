<?php
session_start();
require_once 'database.php';

// Remove login redirect for testing
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../auth/login.php");
//     exit();
// }

echo "<h2>Feed System Test</h2>";

try {
  // Test 1: Check posts table
  $stmt = $conn->query("SELECT COUNT(*) as total FROM posts");
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  echo "<p>Test 1 - Posts in database: " . $result['total'] . "</p>";

  // Test 2: Check if posts table exists
  $stmt = $conn->query("SHOW TABLES LIKE 'posts'");
  if ($stmt->rowCount() > 0) {
    echo "<p style='color:green'>Test 2 - Posts table exists</p>";
  } else {
    echo "<p style='color:red'>Test 2 - Posts table missing</p>";
  }

  // Test 3: Check session status
  if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p style='color:green'>Test 3 - Session is active</p>";
    echo "Session user_id: " . ($_SESSION['user_id'] ?? 'Not set');
  } else {
    echo "<p style='color:red'>Test 3 - Session not active</p>";
  }
} catch (PDOException $e) {
  echo "<p style='color:red'>Database Error: " . $e->getMessage() . "</p>";
}
