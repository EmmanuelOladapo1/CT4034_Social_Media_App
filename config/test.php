<?php
// test.php
require_once 'config/database.php';

try {
  // If connection is successful
  if ($conn) {
    echo "<h1>Database Connection Test</h1>";
    echo "<p style='color: green;'>Connection successful!</p>";

    // Test query
    $stmt = $conn->query("SELECT NOW() as current_time");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Current server time: " . $result['current_time'] . "</p>";

    // Display database info
    echo "<h2>Connection Details:</h2>";
    echo "<p>Database Name: " . $db . "</p>";
    echo "<p>Database User: " . $user . "</p>";
    echo "<p>Host: " . $host . "</p>";
  }
} catch (PDOException $e) {
  echo "<h1>Database Connection Test</h1>";
  echo "<p style='color: red;'>Connection failed!</p>";
  echo "<p>Error: " . $e->getMessage() . "</p>";
}
