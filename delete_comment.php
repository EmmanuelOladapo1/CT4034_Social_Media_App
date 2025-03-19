<?php
// delete_comment.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
  http_response_code(400); // Bad request
  echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
  exit;
}

$comment_id = $_GET['id'];

try {
  // Check if comment exists and belongs to the user
  $stmt = $conn->prepare("SELECT user_id FROM comments WHERE comment_id = ?");
  $stmt->execute([$comment_id]);
  $comment = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$comment) {
    http_response_code(404); // Not found
    echo json_encode(['success' => false, 'message' => 'Comment not found']);
    exit;
  }

  if ($comment['user_id'] != $_SESSION['user_id'] && !$_SESSION['is_admin']) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'You cannot delete this comment']);
    exit;
  }

  // Delete the comment
  $stmt = $conn->prepare("DELETE FROM comments WHERE comment_id = ?");
  $stmt->execute([$comment_id]);

  // Check if the request is AJAX or direct
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // AJAX request
    echo json_encode(['success' => true, 'message' => 'Comment deleted successfully']);
  } else {
    // Direct request - redirect back to referring page
    header("Location: " . $_SERVER['HTTP_REFERER'] ?? 'feed.php');
  }
} catch (PDOException $e) {
  http_response_code(500); // Server error
  echo json_encode(['success' => false, 'message' => 'Error deleting comment: ' . $e->getMessage()]);
}
