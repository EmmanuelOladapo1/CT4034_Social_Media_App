<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

if (isset($_POST['report_user'])) {
  $reported_id = $_POST['user_id'];
  $reason = $_POST['reason'];

  $stmt = $conn->prepare("INSERT INTO reports (reporter_id, reported_id, reason, created_at) VALUES (?, ?, ?, NOW())");
  $stmt->execute([$_SESSION['user_id'], $reported_id, $reason]);

  // Redirect back to the profile page
  header("Location: profile.php?id=" . $reported_id . "&reported=1");
  exit();
}

if (isset($_POST['block_user'])) {
  $blocked_id = $_POST['user_id'];

  $stmt = $conn->prepare("INSERT INTO blocks (user_id, blocked_id, created_at) VALUES (?, ?, NOW())");
  $stmt->execute([$_SESSION['user_id'], $blocked_id]);

  // Redirect back to the feed
  header("Location: feed.php?blocked=1");
  exit();
}
