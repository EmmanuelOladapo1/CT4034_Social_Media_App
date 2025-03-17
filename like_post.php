<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['post_id'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
  exit;
}

$post_id = $_POST['post_id'];
$user_id = $_SESSION['user_id'];

try {
  // Check if already liked
  $stmt = $conn->prepare("SELECT like_id FROM likes WHERE post_id = ? AND user_id = ?");
  $stmt->execute([$post_id, $user_id]);

  if ($stmt->rowCount() > 0) {
    // Unlike
    $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$post_id, $user_id]);
    $action = 'unliked';
  } else {
    // Like
    $stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
    $stmt->execute([$post_id, $user_id]);
    $action = 'liked';
  }

  // Get updated count
  $stmt = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
  $stmt->execute([$post_id]);
  $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

  echo json_encode(['success' => true, 'action' => $action, 'count' => $count]);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error']);
}
