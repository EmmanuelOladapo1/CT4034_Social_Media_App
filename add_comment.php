<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['post_id']) || !isset($_POST['content'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
  exit;
}

$post_id = $_POST['post_id'];
$user_id = $_SESSION['user_id'];
$content = trim($_POST['content']);

if (empty($content)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
  exit;
}

try {
  $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
  $stmt->execute([$post_id, $user_id, $content]);

  // Get the comment with username
  $comment_id = $conn->lastInsertId();
  $stmt = $conn->prepare("SELECT c.*, u.username FROM comments c JOIN users u ON c.user_id = u.user_id WHERE c.comment_id = ?");
  $stmt->execute([$comment_id]);
  $comment = $stmt->fetch(PDO::FETCH_ASSOC);

  echo json_encode(['success' => true, 'comment' => $comment]);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error']);
}
