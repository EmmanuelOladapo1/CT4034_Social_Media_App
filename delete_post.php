<?php
// delete_post.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
  header("Location: feed.php");
  exit();
}

$post_id = $_GET['id'];

// Check post ownership
$stmt = $conn->prepare("SELECT user_id FROM posts WHERE post_id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

// Only allow delete if post belongs to user or user is admin
if (!$post || ($post['user_id'] != $_SESSION['user_id'] && !$_SESSION['is_admin'])) {
  header("Location: feed.php");
  exit();
}

try {
  // Delete related likes and comments first (maintaining referential integrity)
  $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ?");
  $stmt->execute([$post_id]);

  $stmt = $conn->prepare("DELETE FROM comments WHERE post_id = ?");
  $stmt->execute([$post_id]);

  // Delete the post
  $stmt = $conn->prepare("DELETE FROM posts WHERE post_id = ?");
  $stmt->execute([$post_id]);

  header("Location: feed.php");
  exit();
} catch (PDOException $e) {
  die("Error deleting post: " . $e->getMessage());
}
