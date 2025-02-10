<?php
// test.php
require_once __DIR__ . '/database.php';  // Use __DIR__ to ensure correct path

try {
  // If connection is successful
  if ($conn) {
    echo "<h1>Database Connection Test</h1>";
    echo "<p style='color: green;'>Connection successful!</p>";

    // Test query
    $stmt = $conn->query("SELECT NOW *");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

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
