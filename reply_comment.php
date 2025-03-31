<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['comment_id']) || !isset($_POST['content']) || !isset($_POST['csrf_token'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
  exit;
}

// Verify CSRF token
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
  exit;
}

$comment_id = $_POST['comment_id'];
$user_id = $_SESSION['user_id'];
$content = trim($_POST['content']);

if (empty($content)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Reply cannot be empty']);
  exit;
}

try {
  // Check if comment exists
  $stmt = $conn->prepare("SELECT comment_id FROM comments WHERE comment_id = ?");
  $stmt->execute([$comment_id]);
  if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Comment not found']);
    exit;
  }

  // Add reply
  $stmt = $conn->prepare("INSERT INTO comment_replies (comment_id, user_id, content) VALUES (?, ?, ?)");
  $stmt->execute([$comment_id, $user_id, $content]);
  $reply_id = $conn->lastInsertId();

  // Get reply with user data
  $stmt = $conn->prepare("SELECT r.*, u.username, u.profile_image FROM comment_replies r
                           JOIN users u ON r.user_id = u.user_id
                           WHERE r.reply_id = ?");
  $stmt->execute([$reply_id]);
  $reply = $stmt->fetch(PDO::FETCH_ASSOC);

  echo json_encode(['success' => true, 'reply' => $reply]);
} catch (PDOException $e) {
  error_log("Database error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error']);
}
