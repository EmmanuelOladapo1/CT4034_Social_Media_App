<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['user_id'])) {
  echo json_encode(['error' => 'Missing parameters']);
  exit();
}

$user_id = $_GET['user_id'];
$current_user_id = $_SESSION['user_id'];

$response = [
  'is_friend' => false,
  'request_sent' => false,
  'request_received' => false,
  'is_blocked' => false
];

// Check if they're friends
$stmt = $conn->prepare("SELECT COUNT(*) FROM friends
                      WHERE (user_id1 = ? AND user_id2 = ?)
                      OR (user_id1 = ? AND user_id2 = ?)");
$stmt->execute([$current_user_id, $user_id, $user_id, $current_user_id]);
$response['is_friend'] = ($stmt->fetchColumn() > 0);

if (!$response['is_friend']) {
  // Check if request was sent
  $stmt = $conn->prepare("SELECT COUNT(*) FROM friend_requests WHERE sender_id = ? AND receiver_id = ?");
  $stmt->execute([$current_user_id, $user_id]);
  $response['request_sent'] = ($stmt->fetchColumn() > 0);

  // Check if request was received
  $stmt = $conn->prepare("SELECT COUNT(*) FROM friend_requests WHERE sender_id = ? AND receiver_id = ?");
  $stmt->execute([$user_id, $current_user_id]);
  $response['request_received'] = ($stmt->fetchColumn() > 0);
}

// Check if blocked
$stmt = $conn->prepare("SELECT COUNT(*) FROM blocks WHERE user_id = ? AND blocked_id = ?");
$stmt->execute([$current_user_id, $user_id]);
$response['is_blocked'] = ($stmt->fetchColumn() > 0);

echo json_encode($response);
