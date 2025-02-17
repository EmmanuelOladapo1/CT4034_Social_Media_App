<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $email = trim($_POST['email']);
  $password = $_POST['password'];

  // Server-side validation
  $errors = [];

  if (empty($username)) {
    $errors[] = "Username is required";
  }

  if (empty($email)) {
    $errors[] = "Email is required";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format";
  }

  if (strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters long";
  }

  // Check if username or email already exists
  try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
      $errors[] = "Username or email already exists";
    }
  } catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
  }

  // If no errors, proceed with registration
  if (empty($errors)) {
    try {
      // Hash password
      $password_hash = password_hash($password, PASSWORD_DEFAULT);
      var_dump($password_hash);

      // Insert new user
      $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
      $stmt->execute([$username, $email, $password_hash]);
      $user = $stmt->fetch();
      var_dump($user); // Temporary debug
      var_dump(password_verify($password, $user['password_hash']));

      // Start session and redirect
      session_start();
      $_SESSION['user_id'] = $conn->lastInsertId();
      $_SESSION['username'] = $username;

      header("Location: ../index.php");
      exit();
    } catch (PDOException $e) {
      $errors[] = "Registration failed: " . $e->getMessage();
    }
  }

  // If there were errors, redirect back with error messages
  if (!empty($errors)) {
    $_SESSION['register_errors'] = $errors;
    header("Location: register.php");
    exit();
  }
}
