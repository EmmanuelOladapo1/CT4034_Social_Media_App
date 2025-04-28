<?php
require_once 'config/database.php';

// Use the same database connection type as your users.php file
$new_password = 'admin123';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Use the appropriate query style (PDO)
$stmt = $conn->prepare("UPDATE admins SET password_hash = ? WHERE username = 'admin'");
$result = $stmt->execute([$hashed_password]);

if ($result) {
  echo "Admin password reset to: $new_password";
} else {
  echo "Failed to reset password";
}
