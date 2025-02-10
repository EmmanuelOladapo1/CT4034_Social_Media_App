<?php
$config = include_once 'config.php';

$host = $config['DB_HOST'];
$db = $config['DB_NAME'];
$user = $config['DB_USER'];
$pass = $config['DB_PASS'];

try {
  $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  // echo "Connected successfully";
} catch (PDOException $e) {
  die("Connection failed: " . $e->getMessage());
}

// Make connection available globally
global $conn;
