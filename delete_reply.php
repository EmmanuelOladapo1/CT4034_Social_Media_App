<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id']) || !isset($_GET['csrf_token'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
  exit;
}

// Verify CSRF token
if ($_GET['csrf_token'] !== $_SESSION['csrf_token']) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
  exit;
}

$reply_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
  // Check if reply exists and belongs to user
  $stmt = $conn->prepare("SELECT user_id FROM comment_replies WHERE reply_id = ?");
  $stmt->execute([$reply_id]);
  $reply = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$reply) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Reply not found']);
    exit;
  }

  // Only allow reply owner or admin to delete
  if ($reply['user_id'] != $user_id && !isset($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You cannot delete this reply']);
    exit;
  }

  // Delete the reply
  $stmt = $conn->prepare("DELETE FROM comment_replies WHERE reply_id = ?");
  $stmt->execute([$reply_id]);

  echo json_encode(['success' => true, 'message' => 'Reply deleted successfully']);
} catch (PDOException $e) {
  error_log("Database error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error']);
}
