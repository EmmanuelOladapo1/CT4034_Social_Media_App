<?php
session_start();
require_once 'config/database.php';

// Check if user is admin
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_role = $stmt->fetchColumn();

if (!isset($_SESSION['user_id']) || $user_role !== 'admin') {
  header("Location: auth/login.php");
  exit();
}

if (isset($_GET['user_id']) && isset($_GET['days'])) {
  $user_id = $_GET['user_id'];
  $days = (int)$_GET['days'];

  // Add block record
  $stmt = $conn->prepare("INSERT INTO blocks (user_id, blocked_id, created_at) VALUES (?, ?, NOW())");
  $stmt->execute([$_SESSION['user_id'], $user_id]);

  // Redirect back to admin dashboard
  header("Location: admin_dashboard.php?blocked=1");
  exit();
}
