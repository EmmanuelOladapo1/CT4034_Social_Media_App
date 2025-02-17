<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email']);
  $password = $_POST['password'];

  try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
      $_SESSION['user_id'] = $user['user_id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['is_admin'] = $user['is_admin'];

      header("Location: ../feed.php");
      exit();
    } else {
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
