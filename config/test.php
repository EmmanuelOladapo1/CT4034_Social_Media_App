<?php
require_once __DIR__ . '/database.php';

echo "<h1>Database Connection Test</h1>";

try {
  // Test 1: Basic Connection
  if ($conn) {
    echo "<p style='color: green;'>Step 1: Basic connection successful!</p>";
  }

  // Test 2: Simple Query
  $query = "SELECT 1";
  $stmt = $conn->query($query);
  echo "<p style='color: green;'>Step 2: Simple query successful!</p>";

  // Test 3: Database Selection
  $query = "SELECT DATABASE()";
  $stmt = $conn->query($query);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  echo "<p>Current database: " . ($result['DATABASE()'] ?? 'none') . "</p>";

  // Display connection info
  echo "<h2>Connection Details:</h2>";
  echo "<p>Database Name: " . $db . "</p>";
  echo "<p>Database User: " . $user . "</p>";
  echo "<p>Host: " . $host . "</p>";
} catch (PDOException $e) {
  echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
  echo "<p>Error Code: " . $e->getCode() . "</p>";
}
