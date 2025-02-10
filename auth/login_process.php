<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email']);
  $password = $_POST['password'];

  try {
    // Get user by email
    $stmt = $conn->prepare("SELECT user_id, username, password_hash, is_admin FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
      // Login successful
      $_SESSION['user_id'] = $user['user_id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['is_admin'] = $user['is_admin'];

      // Redirect based on user type
      if ($user['is_admin']) {
        header("Location: ../admin/dashboard.php");
      } else {
        header("Location: ../index.php");
      }
      exit();
    } else {
      // Login failed
      $_SESSION['login_error'] = "Invalid email or password";
      header("Location: login.php");
      exit();
    }
  } catch (PDOException $e) {
    $_SESSION['login_error'] = "Login failed: " . $e->getMessage();
    header("Location: login.php");
    exit();
  }
}
