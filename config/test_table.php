<?php
// test_table.php
require_once __DIR__ . '/config/database.php';

try {
  // Check if users table exists
  $query = "DESCRIBE users";
  $stmt = $conn->query($query);

  echo "<h2>Users Table Structure:</h2>";
  echo "<pre>";
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
  }
  echo "</pre>";
} catch (PDOException $e) {
  echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
