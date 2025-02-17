<?php
require_once 'database.php';
$email = 'test@example.com';
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();
print_r($user);
