<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $content = trim($_POST['content']);
  $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
  $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
  $image_url = null;

  // Handle image upload
  if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
      mkdir($target_dir, 0777, true);
    }

    $target_file = $target_dir . time() . '_' . basename($_FILES["image"]["name"]);
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
      $image_url = $target_file;
    }
  }

  try {
    $stmt = $conn->prepare("INSERT INTO posts (user_id, content, image_url, latitude, longitude) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $content, $image_url, $latitude, $longitude]);
    header("Location: feed.php");
    exit(); // Add exit after redirect
  } catch (PDOException $e) {
    die("Error creating post: " . $e->getMessage());
  }
}
