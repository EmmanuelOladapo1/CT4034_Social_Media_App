<?php

/**
 * Core Functions for Social Media Website
 *
 * This file contains the main functionality for the social media platform
 * including user management, post handling, messaging, and friend management.
 *
 * @author Student
 * @version 1.0
 */

// Start session
session_start();

// Database connection
/**
 * Establishes a connection to the MySQL database
 *
 * @return mysqli Database connection object
 */
function dbConnect()
{
  $host = "localhost";
  $username = "db_username";
  $password = "db_password";
  $database = "social_media_db";

  // Create connection
  $conn = new mysqli($host, $username, $password, $database);

  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  return $conn;
}

/**
 * User Authentication Functions
 */

/**
 * Register a new user in the system
 *
 * @param string $username Username for the new account
 * @param string $email User's email address
 * @param string $password User's password (will be hashed)
 * @param string $security_question User's security question
 * @param string $security_answer User's answer to security question
 * @param string $pin Memorable PIN for password reset
 * @return array Status of registration and message
 */
function registerUser($username, $email, $password, $security_question, $security_answer, $pin)
{
  $conn = dbConnect();

  // Check if username or email already exists
  $check_query = "SELECT * FROM users WHERE username = ? OR email = ?";
  $check_stmt = $conn->prepare($check_query);
  $check_stmt->bind_param("ss", $username, $email);
  $check_stmt->execute();
  $result = $check_stmt->get_result();

  if ($result->num_rows > 0) {
    return ["success" => false, "message" => "Username or email already exists"];
  }

  // Hash password for security
  $hashed_password = password_hash($password, PASSWORD_DEFAULT);
  $hashed_answer = password_hash(strtolower($security_answer), PASSWORD_DEFAULT);
  $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);

  // Default role is regular user (0)
  $is_admin = 0;

  // Prepare and execute the SQL query
  $query = "INSERT INTO users (username, email, password, security_question, security_answer, pin, is_admin, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("ssssssi", $username, $email, $hashed_password, $security_question, $hashed_answer, $hashed_pin, $is_admin);

  if ($stmt->execute()) {
    return ["success" => true, "message" => "Registration successful"];
  } else {
    return ["success" => false, "message" => "Registration failed: " . $conn->error];
  }
}

/**
 * Authenticate a user login attempt
 *
 * @param string $username Username or email
 * @param string $password Password attempt
 * @param bool $as_admin Whether to login as admin
 * @return array Authentication result and user data if successful
 */
function loginUser($username, $password, $as_admin = false)
{
  $conn = dbConnect();

  // Check if login is with username or email
  $query = "SELECT * FROM users WHERE (username = ? OR email = ?)";

  if ($as_admin) {
    // If admin login, add admin condition
    $query .= " AND is_admin = 1";
  }

  $stmt = $conn->prepare($query);
  $stmt->bind_param("ss", $username, $username);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Verify password
    if (password_verify($password, $user['password'])) {
      // Create session
      $_SESSION['user_id'] = $user['user_id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['is_admin'] = $user['is_admin'];

      return ["success" => true, "user" => $user];
    }
  }

  return ["success" => false, "message" => "Invalid username or password"];
}

/**
 * Log out the current user
 */
function logoutUser()
{
  // Unset all session variables
  $_SESSION = array();

  // Destroy the session
  session_destroy();
}

/**
 * Check if user is logged in
 *
 * @return bool True if user is logged in
 */
function isLoggedIn()
{
  return isset($_SESSION['user_id']);
}

/**
 * Check if logged in user is an admin
 *
 * @return bool True if user is an admin
 */
function isAdmin()
{
  return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

/**
 * Post Management Functions
 */

/**
 * Create a new wall post
 *
 * @param int $user_id ID of the user creating the post
 * @param string $content Text content of the post
 * @param string $image_path Path to the uploaded image (or null)
 * @param float $latitude Location latitude (or null)
 * @param float $longitude Location longitude (or null)
 * @return array Status of post creation and message
 */
function createPost($user_id, $content, $image_path = null, $latitude = null, $longitude = null)
{
  $conn = dbConnect();

  // Prepare and execute the SQL query
  $query = "INSERT INTO posts (user_id, content, image_path, latitude, longitude, created_at)
              VALUES (?, ?, ?, ?, ?, NOW())";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("issdd", $user_id, $content, $image_path, $latitude, $longitude);

  if ($stmt->execute()) {
    $post_id = $conn->insert_id;
    return ["success" => true, "post_id" => $post_id];
  } else {
    return ["success" => false, "message" => "Post creation failed: " . $conn->error];
  }
}

/**
 * Get posts for the wall feed
 *
 * @param int $user_id Current user ID
 * @param int $limit Number of posts to retrieve
 * @param int $offset Offset for pagination
 * @return array List of posts with user information
 */
function getPosts($user_id, $limit = 10, $offset = 0)
{
  $conn = dbConnect();

  // Get posts from the user and their friends
  // Also exclude posts from blocked users
  $query = "SELECT p.*, u.username, u.profile_picture,
              (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count,
              (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
              (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id AND user_id = ?) as user_liked
              FROM posts p
              JOIN users u ON p.user_id = u.user_id
              WHERE p.user_id = ?
              OR (p.user_id IN (SELECT friend_id FROM friends WHERE user_id = ? AND status = 'accepted'))
              AND p.user_id NOT IN (SELECT blocked_id FROM blocks WHERE user_id = ?)
              ORDER BY p.created_at DESC
              LIMIT ? OFFSET ?";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("iiiiiii", $user_id, $user_id, $user_id, $user_id, $limit, $offset);
  $stmt->execute();
  $result = $stmt->get_result();

  $posts = [];
  while ($row = $result->fetch_assoc()) {
    $posts[] = $row;
  }

  return $posts;
}

/**
 * Like or unlike a post
 *
 * @param int $user_id ID of the user liking/unliking
 * @param int $post_id ID of the post
 * @return array Status of like operation
 */
function toggleLike($user_id, $post_id)
{
  $conn = dbConnect();

  // Check if already liked
  $check_query = "SELECT * FROM likes WHERE user_id = ? AND post_id = ?";
  $check_stmt = $conn->prepare($check_query);
  $check_stmt->bind_param("ii", $user_id, $post_id);
  $check_stmt->execute();
  $result = $check_stmt->get_result();

  if ($result->num_rows > 0) {
    // Unlike
    $query = "DELETE FROM likes WHERE user_id = ? AND post_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $post_id);
    $stmt->execute();
    return ["success" => true, "action" => "unliked"];
  } else {
    // Like
    $query = "INSERT INTO likes (user_id, post_id, created_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $post_id);
    $stmt->execute();
    return ["success" => true, "action" => "liked"];
  }
}

/**
 * Add a comment to a post
 *
 * @param int $user_id ID of the commenter
 * @param int $post_id ID of the post
 * @param string $content Comment text
 * @return array Status of comment creation
 */
function addComment($user_id, $post_id, $content)
{
  $conn = dbConnect();

  $query = "INSERT INTO comments (user_id, post_id, content, created_at) VALUES (?, ?, ?, NOW())";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("iis", $user_id, $post_id, $content);

  if ($stmt->execute()) {
    $comment_id = $conn->insert_id;
    return ["success" => true, "comment_id" => $comment_id];
  } else {
    return ["success" => false, "message" => "Comment failed: " . $conn->error];
  }
}

/**
 * Get comments for a post
 *
 * @param int $post_id ID of the post
 * @return array List of comments with user information
 */
function getComments($post_id)
{
  $conn = dbConnect();

  $query = "SELECT c.*, u.username, u.profile_picture
              FROM comments c
              JOIN users u ON c.user_id = u.user_id
              WHERE c.post_id = ?
              ORDER BY c.created_at ASC";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $post_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $comments = [];
  while ($row = $result->fetch_assoc()) {
    $comments[] = $row;
  }

  return $comments;
}

/**
 * Messaging Functions
 */

/**
 * Send a message to another user
 *
 * @param int $sender_id ID of the sender
 * @param int $receiver_id ID of the receiver
 * @param string $content Message content
 * @return array Status of message sending
 */
function sendMessage($sender_id, $receiver_id, $content)
{
  $conn = dbConnect();

  // Check if sender is blocked by receiver
  $check_query = "SELECT * FROM blocks WHERE user_id = ? AND blocked_id = ?";
  $check_stmt = $conn->prepare($check_query);
  $check_stmt->bind_param("ii", $receiver_id, $sender_id);
  $check_stmt->execute();
  $result = $check_stmt->get_result();

  if ($result->num_rows > 0) {
    return ["success" => false, "message" => "You cannot send message to this user"];
  }

  $query = "INSERT INTO messages (sender_id, receiver_id, content, created_at) VALUES (?, ?, ?, NOW())";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("iis", $sender_id, $receiver_id, $content);

  if ($stmt->execute()) {
    $message_id = $conn->insert_id;
    return ["success" => true, "message_id" => $message_id];
  } else {
    return ["success" => false, "message" => "Message sending failed: " . $conn->error];
  }
}

/**
 * Get conversation between two users
 *
 * @param int $user1_id First user ID
 * @param int $user2_id Second user ID
 * @return array Messages between the two users
 */
function getConversation($user1_id, $user2_id)
{
  $conn = dbConnect();

  $query = "SELECT m.*, u.username, u.profile_picture
              FROM messages m
              JOIN users u ON m.sender_id = u.user_id
              WHERE (m.sender_id = ? AND m.receiver_id = ?)
              OR (m.sender_id = ? AND m.receiver_id = ?)
              ORDER BY m.created_at ASC";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("iiii", $user1_id, $user2_id, $user2_id, $user1_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $messages = [];
  while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
  }

  return $messages;
}

/**
 * Get all conversations for a user
 *
 * @param int $user_id User ID
 * @return array List of conversations with last message
 */
function getUserConversations($user_id)
{
  $conn = dbConnect();

  // Get all users who have exchanged messages with this user
  $query = "SELECT
                DISTINCT IF(m.sender_id = ?, m.receiver_id, m.sender_id) as other_user_id,
                u.username,
                u.profile_picture,
                (SELECT content FROM messages
                 WHERE (sender_id = ? AND receiver_id = other_user_id)
                 OR (sender_id = other_user_id AND receiver_id = ?)
                 ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM messages
                 WHERE (sender_id = ? AND receiver_id = other_user_id)
                 OR (sender_id = other_user_id AND receiver_id = ?)
                 ORDER BY created_at DESC LIMIT 1) as last_message_time,
                (SELECT COUNT(*) FROM messages
                 WHERE sender_id = other_user_id AND receiver_id = ? AND is_read = 0) as unread_count
              FROM messages m
              JOIN users u ON IF(m.sender_id = ?, m.receiver_id, m.sender_id) = u.user_id
              WHERE m.sender_id = ? OR m.receiver_id = ?
              ORDER BY last_message_time DESC";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("iiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $conversations = [];
  while ($row = $result->fetch_assoc()) {
    $conversations[] = $row;
  }

  return $conversations;
}

/**
 * Friend Management Functions
 */

/**
 * Send friend request to a user
 *
 * @param int $user_id ID of the requester
 * @param int $friend_id ID of the potential friend
 * @return array Status of friend request
 */
function sendFriendRequest($user_id, $friend_id)
{
  $conn = dbConnect();

  // Check if already friends or request pending
  $check_query = "SELECT * FROM friends
                    WHERE (user_id = ? AND friend_id = ?)
                    OR (user_id = ? AND friend_id = ?)";
  $check_stmt = $conn->prepare($check_query);
  $check_stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
  $check_stmt->execute();
  $result = $check_stmt->get_result();

  if ($result->num_rows > 0) {
    $friendship = $result->fetch_assoc();
    if ($friendship['status'] == 'accepted') {
      return ["success" => false, "message" => "Already friends"];
    } else if ($friendship['status'] == 'pending' && $friendship['user_id'] == $user_id) {
      return ["success" => false, "message" => "Friend request already sent"];
    } else if ($friendship['status'] == 'pending' && $friendship['user_id'] == $friend_id) {
      return ["success" => false, "message" => "This user has already sent you a friend request"];
    }
  }

  // Check if user is blocked
  $block_query = "SELECT * FROM blocks WHERE (user_id = ? AND blocked_id = ?) OR (user_id = ? AND blocked_id = ?)";
  $block_stmt = $conn->prepare($block_query);
  $block_stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
  $block_stmt->execute();
  $block_result = $block_stmt->get_result();

  if ($block_result->num_rows > 0) {
    return ["success" => false, "message" => "Cannot send friend request due to block"];
  }

  // Create friend request
  $query = "INSERT INTO friends (user_id, friend_id, status, created_at) VALUES (?, ?, 'pending', NOW())";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("ii", $user_id, $friend_id);

  if ($stmt->execute()) {
    return ["success" => true, "message" => "Friend request sent"];
  } else {
    return ["success" => false, "message" => "Friend request failed: " . $conn->error];
  }
}

/**
 * Accept or reject a friend request
 *
 * @param int $user_id ID of the request receiver
 * @param int $friend_id ID of the requester
 * @param string $action 'accept' or 'reject'
 * @return array Status of the action
 */
function respondToFriendRequest($user_id, $friend_id, $action)
{
  $conn = dbConnect();

  // Check if request exists
  $check_query = "SELECT * FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'";
  $check_stmt = $conn->prepare($check_query);
  $check_stmt->bind_param("ii", $friend_id, $user_id);
  $check_stmt->execute();
  $result = $check_stmt->get_result();

  if ($result->num_rows == 0) {
    return ["success" => false, "message" => "No pending friend request found"];
  }

  if ($action == 'accept') {
    // Update status to accepted
    $query = "UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $friend_id, $user_id);

    if ($stmt->execute()) {
      return ["success" => true, "message" => "Friend request accepted"];
    } else {
      return ["success" => false, "message" => "Failed to accept request: " . $conn->error];
    }
  } else {
    // Delete the request
    $query = "DELETE FROM friends WHERE user_id = ? AND friend_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $friend_id, $user_id);

    if ($stmt->execute()) {
      return ["success" => true, "message" => "Friend request rejected"];
    } else {
      return ["success" => false, "message" => "Failed to reject request: " . $conn->error];
    }
  }
}

/**
 * Get a user's friends list
 *
 * @param int $user_id User ID
 * @return array List of friends with user information
 */
function getFriends($user_id)
{
  $conn = dbConnect();

  $query = "SELECT u.user_id, u.username, u.profile_picture
              FROM users u
              JOIN friends f ON (f.friend_id = u.user_id AND f.user_id = ? AND f.status = 'accepted')
              OR (f.user_id = u.user_id AND f.friend_id = ? AND f.status = 'accepted')";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("ii", $user_id, $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $friends = [];
  while ($row = $result->fetch_assoc()) {
    $friends[] = $row;
  }

  return $friends;
}

/**
 * Get pending friend requests for a user
 *
 * @param int $user_id User ID
 * @return array List of pending friend requests
 */
function getPendingFriendRequests($user_id)
{
  $conn = dbConnect();

  $query = "SELECT u.user_id, u.username, u.profile_picture, f.created_at
              FROM friends f
              JOIN users u ON f.user_id = u.user_id
              WHERE f.friend_id = ? AND f.status = 'pending'";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $requests = [];
  while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
  }

  return $requests;
}

/**
 * User Management Functions
 */

/**
 * Block a user
 *
 * @param int $user_id ID of the blocker
 * @param int $blocked_id ID of the user to block
 * @return array Status of the block action
 */
function blockUser($user_id, $blocked_id)
{
  $conn = dbConnect();

  // Check if already blocked
  $check_query = "SELECT * FROM blocks WHERE user_id = ? AND blocked_id = ?";
  $check_stmt = $conn->prepare($check_query);
  $check_stmt->bind_param("ii", $user_id, $blocked_id);
  $check_stmt->execute();
  $result = $check_stmt->get_result();

  if ($result->num_rows > 0) {
    return ["success" => false, "message" => "User already blocked"];
  }

  // Create block
  $query = "INSERT INTO blocks (user_id, blocked_id, created_at) VALUES (?, ?, NOW())";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("ii", $user_id, $blocked_id);

  if ($stmt->execute()) {
    // Remove any friend connection
    $remove_friend = "DELETE FROM friends
                          WHERE (user_id = ? AND friend_id = ?)
                          OR (user_id = ? AND friend_id = ?)";
    $friend_stmt = $conn->prepare($remove_friend);
    $friend_stmt->bind_param("iiii", $user_id, $blocked_id, $blocked_id, $user_id);
    $friend_stmt->execute();

    return ["success" => true, "message" => "User blocked successfully"];
  } else {
    return ["success" => false, "message" => "Failed to block user: " . $conn->error];
  }
}

/**
 * Unblock a user
 *
 * @param int $user_id ID of the blocker
 * @param int $blocked_id ID of the blocked user
 * @return array Status of the unblock action
 */
function unblockUser($user_id, $blocked_id)
{
  $conn = dbConnect();

  $query = "DELETE FROM blocks WHERE user_id = ? AND blocked_id = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("ii", $user_id, $blocked_id);

  if ($stmt->execute()) {
    return ["success" => true, "message" => "User unblocked successfully"];
  } else {
    return ["success" => false, "message" => "Failed to unblock user: " . $conn->error];
  }
}

/**
 * Report a user
 *
 * @param int $reporter_id ID of the reporting user
 * @param int $reported_id ID of the reported user
 * @param string $reason Reason for reporting
 * @return array Status of the report action
 */
function reportUser($reporter_id, $reported_id, $reason)
{
  $conn = dbConnect();

  $query = "INSERT INTO reports (reporter_id, reported_id, reason, status, created_at)
              VALUES (?, ?, ?, 'pending', NOW())";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("iis", $reporter_id, $reported_id, $reason);

  if ($stmt->execute()) {
    return ["success" => true, "message" => "User reported successfully"];
  } else {
    return ["success" => false, "message" => "Failed to report user: " . $conn->error];
  }
}

/**
 * Get all users (for user discovery)
 *
 * @param int $current_user_id Current user ID to exclude
 * @return array List of users with basic information
 */
function getAllUsers($current_user_id)
{
  $conn = dbConnect();

  $query = "SELECT user_id, username, profile_picture
              FROM users
              WHERE user_id != ? AND is_admin = 0
              ORDER BY username";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $current_user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $users = [];
  while ($row = $result->fetch_assoc()) {
    $users[] = $row;
  }

  return $users;
}

/**
 * Admin Functions
 */

/**
 * Delete a user (admin only)
 *
 * @param int $user_id ID of the user to delete
 * @return array Status of the deletion
 */
function deleteUser($user_id)
{
  $conn = dbConnect();

  // Start transaction
  $conn->begin_transaction();

  try {
    // Delete all related data
    $tables = [
      "DELETE FROM likes WHERE user_id = ?",
      "DELETE FROM comments WHERE user_id = ?",
      "DELETE FROM posts WHERE user_id = ?",
      "DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?",
      "DELETE FROM friends WHERE user_id = ? OR friend_id = ?",
      "DELETE FROM blocks WHERE user_id = ? OR blocked_id = ?",
      "DELETE FROM reports WHERE reporter_id = ? OR reported_id = ?",
      "DELETE FROM users WHERE user_id = ?"
    ];

    foreach ($tables as $query) {
      $stmt = $conn->prepare($query);

      if (substr_count($query, '?') == 1) {
        $stmt->bind_param("i", $user_id);
      } else if (substr_count($query, '?') == 2) {
        $stmt->bind_param("ii", $user_id, $user_id);
      }

      $stmt->execute();
    }

    // Commit the transaction
    $conn->commit();

    return ["success" => true, "message" => "User deleted successfully"];
  } catch (Exception $e) {
    // Rollback in case of error
    $conn->rollback();
    return ["success" => false, "message" => "Failed to delete user: " . $e->getMessage()];
  }
}

/**
 * Get post statistics for admin report
 *
 * @param string $period 'week', 'month', or 'year'
 * @return array Post statistics per user
 */
function getPostStatistics($period)
{
  $conn = dbConnect();

  $period_clause = "";
  switch ($period) {
    case 'week':
      $period_clause = "WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
      break;
    case 'month':
      $period_clause = "WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
      break;
    case 'year':
      $period_clause = "WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
      break;
  }

  $query = "SELECT u.user_id, u.username, COUNT(p.post_id) as post_count
              FROM users u
              LEFT JOIN posts p ON u.user_id = p.user_id
              $period_clause
              GROUP BY u.user_id
              ORDER BY post_count DESC";

  $stmt = $conn->prepare($query);
  $stmt->execute();
  $result = $stmt->get_result();

  $statistics = [];
  while ($row = $result->fetch_assoc()) {
    $statistics[] = $row;
  }

  return $statistics;
}

/**
 * Get all user reports for admin review
 *
 * @return array List of reports with user information
 */
function getAllReports()
{
  $conn = dbConnect();

  $query = "SELECT r.*,
              reporter.username as reporter_username,
              reported.username as reported_username
              FROM reports r
              JOIN users reporter ON r.reporter_id = reporter.user_id
              JOIN users reported ON r.reported_id = reported.user_id
              ORDER BY r.created_at DESC";

  $stmt = $conn->prepare($query);
  $stmt->execute();
  $result = $stmt->get_result();

  $reports = [];
  while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
  }

  return $reports;
}

/**
 * Take action on a report (admin only)
 *
 * @param int $report_id ID of the report
 * @param string $action 'block_30_days', 'request_id', 'dismiss'
 * @param string $admin_note Admin's note on the action
 * @return array Status of the action
 */
function takeActionOnReport($report_id, $action, $admin_note)
{
  $conn = dbConnect();

  // Get the report details
  $report_query = "SELECT * FROM reports WHERE report_id = ?";
  $report_stmt = $conn->prepare($report_query);
  $report_stmt->bind_param("i", $report_id);
  $report_stmt->execute();
  $report_result = $report_stmt->get_result();

  if ($report_result->num_rows == 0) {
    return ["success" => false, "message" => "Report not found"];
  }

  $report = $report_result->fetch_assoc();
  $reported_id = $report['reported_id'];

  // Start transaction
  $conn->begin_transaction();

  try {
    // Update report status
    $update_query = "UPDATE reports SET status = ?, admin_note = ?, action_taken = ? WHERE report_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $status = "resolved";
    $update_stmt->bind_param("sssi", $status, $admin_note, $action, $report_id);
    $update_stmt->execute();

    // Take the specific action
    if ($action == 'block_30_days') {
      // Block the user for 30 days
      $block_query = "UPDATE users SET blocked_until = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE user_id = ?";
      $block_stmt = $conn->prepare($block_query);
      $block_stmt->bind_param("i", $reported_id);
      $block_stmt->execute();
    } else if ($action == 'request_id') {
      // Mark user as needing to verify identity
      $id_query = "UPDATE users SET id_verification_required = 1 WHERE user_id = ?";
      $id_stmt = $conn->prepare($id_query);
      $id_stmt->bind_param("i", $reported_id);
      $id_stmt->execute();
    }

    // Commit the transaction
    $conn->commit();

    return ["success" => true, "message" => "Action taken successfully"];
  } catch (Exception $e) {
    // Rollback in case of error
    $conn->rollback();
    return ["success" => false, "message" => "Failed to take action: " . $e->getMessage()];
  }
}

/**
 * Upload helper function for images
 *
 * @param array $file $_FILES array element
 * @param string $target_dir Directory to save the file
 * @return array Upload status and file path
 */
function uploadImage($file, $target_dir = "uploads/")
{
  // Check if directory exists, create if not
  if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
  }

  $target_file = $target_dir . basename($file["name"]);
  $upload_ok = 1;
  $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

  // Check if image file is an actual image
  $check = getimagesize($file["tmp_name"]);
  if ($check === false) {
    return ["success" => false, "message" => "File is not an image."];
  }

  // Check file size (limit to 5MB)
  if ($file["size"] > 5000000) {
    return ["success" => false, "message" => "File is too large."];
  }

  // Allow certain file formats
  if ($image_file_type != "jpg" && $image_file_type != "png" && $image_file_type != "jpeg" && $image_file_type != "gif") {
    return ["success" => false, "message" => "Only JPG, JPEG, PNG & GIF files are allowed."];
  }

  // Generate a unique filename to prevent overwrites
  $new_filename = uniqid() . "." . $image_file_type;
  $target_file = $target_dir . $new_filename;

  // Upload the file
  if (move_uploaded_file($file["tmp_name"], $target_file)) {
    return ["success" => true, "file_path" => $target_file];
  } else {
    return ["success" => false, "message" => "There was an error uploading your file."];
  }
}

/**
 * Get user profile information
 *
 * @param int $user_id User ID to get profile for
 * @return array User profile data
 */
function getUserProfile($user_id)
{
  $conn = dbConnect();

  $query = "SELECT user_id, username, email, profile_picture, created_at,
              (SELECT COUNT(*) FROM posts WHERE user_id = ?) as post_count,
              (SELECT COUNT(*) FROM friends WHERE (user_id = ? OR friend_id = ?) AND status = 'accepted') as friend_count
              FROM users
              WHERE user_id = ?";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    return null;
  }

  return $result->fetch_assoc();
}
