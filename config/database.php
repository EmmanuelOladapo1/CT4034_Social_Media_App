<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 's4413678_db');
define('DB_USER', 's4413678_social');
define('DB_PASS', '7q0fe22B~');

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>