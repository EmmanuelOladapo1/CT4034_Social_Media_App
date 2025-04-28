<?php
// Start session only once at the beginning
session_start();

/**
 * Combined core_functions.php and index.php
 * Contains all database functions and routing logic
 */

// Database configuration
$config = [
  'DB_HOST' => 'localhost',
  'DB_NAME' => 's4413678_db',
  'DB_USER' => 'admin_social',
  'DB_PASS' => '7q0fe22B~'
];

// Create database connection
$conn = new mysqli($config['DB_HOST'], $config['DB_USER'], $config['DB_PASS'], $config['DB_NAME']);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

/*
 * CORE FUNCTIONS SECTION
 */

/**
 * Function to sanitize user inputs
 * Helps prevent SQL injection and XSS attacks
 *
 * @param string $data - The data to be sanitized
 * @return string - Sanitized data
 */
function sanitize_input($data)
{
  global $conn;
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  $data = $conn->real_escape_string($data);
  return $data;
}

/**
 * Function to check if a user is logged in
 *
 * @return boolean - True if user is logged in, false otherwise
 */
function is_logged_in()
{
  return isset($_SESSION['user_id']);
}

/**
 * Function to check if a user is an admin
 *
 * @return boolean - True if user is an admin, false otherwise
 */
function is_admin()
{
  return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

/**
 * Function to check if a user account is blocked
 *
 * @param int $user_id - The user ID to check
 * @return boolean - True if user is blocked, false otherwise
 */
function is_blocked($user_id)
{
  global $conn;

  $query = "SELECT is_blocked, block_end_date FROM users WHERE user_id = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($row = $result->fetch_assoc()) {
    // If not blocked, return false
    if (!$row['is_blocked']) {
      return false;
    }

    // If block end date is set and has passed, unblock user
    if ($row['block_end_date'] && strtotime($row['block_end_date']) < time()) {
      $update = "UPDATE users SET is_blocked = FALSE, block_end_date = NULL WHERE user_id = ?";
      $update_stmt = $conn->prepare($update);
      $update_stmt->bind_param("i", $user_id);
      $update_stmt->execute();
      return false;
    }

    // User is still blocked
    return true;
  }

  return false;
}

/**
 * Function to redirect to another page
 *
 * @param string $location - URL to redirect to
 */
function redirect($location)
{
  header("Location: " . $location);
  exit;
}

/**
 * Function to register a new user
 *
 * @param string $username - Username for the new account
 * @param string $email - Email for the new account
 * @param string $password - Password for the new account
 * @param string $full_name - Full name of the user
 * @param string $security_question - Security question for account recovery
 * @param string $security_answer - Answer to the security question
 * @return array - Status and message of the registration process
 */
function register_user($username, $email, $password, $full_name, $security_question, $security_answer)
{
  global $conn;

  // Sanitize inputs
  $username = sanitize_input($username);
  $email = sanitize_input($email);
  $full_name = sanitize_input($full_name);
  $security_question = sanitize_input($security_question);
  $security_answer = sanitize_input($security_answer);

  // Check if username already exists
  $query = "SELECT user_id FROM users WHERE username = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    return [
      'status' => 'error',
      'message' => 'Username already exists. Please choose a different username.'
    ];
  }

  // Check if email already exists
  $query = "SELECT user_id FROM users WHERE email = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    return [
      'status' => 'error',
      'message' => 'Email already registered. Please use a different email.'
    ];
  }

  // Hash password for security
  $hashed_password = password_hash($password, PASSWORD_DEFAULT);

  // Insert new user into the database
  $query = "INSERT INTO users (username, email, password, full_name, role, security_question, security_answer)
              VALUES (?, ?, ?, ?, 'user', ?, ?)";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("ssssss", $username, $email, $hashed_password, $full_name, $security_question, $security_answer);

  if ($stmt->execute()) {
    return [
      'status' => 'success',
      'message' => 'Registration successful! You can now login.'
    ];
  } else {
    return [
      'status' => 'error',
      'message' => 'Registration failed. Please try again later. Error: ' . $conn->error
    ];
  }
}

/**
 * Function to authenticate a user
 *
 * @param string $username - Username of the account
 * @param string $password - Password of the account
 * @param string $role - Role of the user (user or admin)
 * @return array - Status and message of the login process
 */
function login_user($username, $password, $role = 'user')
{
  global $conn;

  // Sanitize inputs
  $username = sanitize_input($username);

  // Get user from database
  $query = "SELECT user_id, username, password, role, is_blocked, block_end_date FROM users WHERE username = ? AND role = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("ss", $username, $role);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows == 0) {
    return [
      'status' => 'error',
      'message' => 'Invalid username or password.'
    ];
  }

  $user = $result->fetch_assoc();

  // Check if account is blocked
  if ($user['is_blocked']) {
    // Check if block period has ended
    if ($user['block_end_date'] && strtotime($user['block_end_date']) < time()) {
      // Unblock user
      $update = "UPDATE users SET is_blocked = FALSE, block_end_date = NULL WHERE user_id = ?";
      $update_stmt = $conn->prepare($update);
      $update_stmt->bind_param("i", $user['user_id']);
      $update_stmt->execute();
    } else {
      $block_end = $user['block_end_date'] ? date('F j, Y, g:i a', strtotime($user['block_end_date'])) : 'indefinitely';
      return [
        'status' => 'error',
        'message' => "Your account is blocked until $block_end. Please contact the administrator."
      ];
    }
  }

  // Verify password
  if (password_verify($password, $user['password'])) {
    // Start session and set user data
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    return [
      'status' => 'success',
      'message' => 'Login successful!'
    ];
  } else {
    return [
      'status' => 'error',
      'message' => 'Invalid username or password.'
    ];
  }
}

/**
 * Function to log out a user
 */
function logout_user()
{
  // Unset all session variables
  $_SESSION = array();

  // Destroy the session
  session_destroy();

  // Redirect to login page
  redirect('index.php');
}

/**
 * Function to verify admin login
 *
 * @param string $username - Admin username
 * @param string $password - Admin password
 * @return array - Status and message of the admin login process
 */
function admin_login($username, $password)
{
  global $conn;

  // Sanitize inputs
  $username = sanitize_input($username);

  // Get admin user from database
  $query = "SELECT user_id, username, password FROM users WHERE username = ? AND role = 'admin'";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows == 0) {
    return [
      'status' => 'error',
      'message' => 'Invalid admin credentials.'
    ];
  }

  $admin = $result->fetch_assoc();

  // Verify password
  if (password_verify($password, $admin['password'])) {
    // Start session and set admin data
    $_SESSION['user_id'] = $admin['user_id'];
    $_SESSION['username'] = $admin['username'];
    $_SESSION['role'] = 'admin';

    return [
      'status' => 'success',
      'message' => 'Admin login successful!'
    ];
  } else {
    return [
      'status' => 'error',
      'message' => 'Invalid admin credentials.'
    ];
  }
}

/**
 * Function to handle liking/unliking posts
 *
 * @param int $post_id - ID of the post to like/unlike
 * @param int $user_id - ID of the user performing the action
 * @return array - Status, message, and updated like count
 */
function toggle_like($post_id, $user_id)
{
  global $conn;

  // Check if user already liked the post
  $check_query = "SELECT like_id FROM likes WHERE post_id = ? AND user_id = ?";
  $check_stmt = $conn->prepare($check_query);
  $check_stmt->bind_param("ii", $post_id, $user_id);
  $check_stmt->execute();
  $check_result = $check_stmt->get_result();

  if ($check_result->num_rows > 0) {
    // User already liked the post, so unlike it
    $unlike_query = "DELETE FROM likes WHERE post_id = ? AND user_id = ?";
    $unlike_stmt = $conn->prepare($unlike_query);
    $unlike_stmt->bind_param("ii", $post_id, $user_id);

    if ($unlike_stmt->execute()) {
      // Get updated like count
      $count_query = "SELECT COUNT(*) AS like_count FROM likes WHERE post_id = ?";
      $count_stmt = $conn->prepare($count_query);
      $count_stmt->bind_param("i", $post_id);
      $count_stmt->execute();
      $count_result = $count_stmt->get_result();
      $like_count = $count_result->fetch_assoc()['like_count'];

      return [
        'status' => 'success',
        'message' => 'Post unliked successfully.',
        'liked' => false,
        'like_count' => $like_count
      ];
    } else {
      return [
        'status' => 'error',
        'message' => 'Failed to unlike post.'
      ];
    }
  } else {
    // User has not liked the post, so like it
    $like_query = "INSERT INTO likes (post_id, user_id) VALUES (?, ?)";
    $like_stmt = $conn->prepare($like_query);
    $like_stmt->bind_param("ii", $post_id, $user_id);

    if ($like_stmt->execute()) {
      // Get updated like count
      $count_query = "SELECT COUNT(*) AS like_count FROM likes WHERE post_id = ?";
      $count_stmt = $conn->prepare($count_query);
      $count_stmt->bind_param("i", $post_id);
      $count_stmt->execute();
      $count_result = $count_stmt->get_result();
      $like_count = $count_result->fetch_assoc()['like_count'];

      return [
        'status' => 'success',
        'message' => 'Post liked successfully.',
        'liked' => true,
        'like_count' => $like_count
      ];
    } else {
      return [
        'status' => 'error',
        'message' => 'Failed to like post.'
      ];
    }
  }
}

/**
 * Function to add a comment to a post
 *
 * @param int $post_id - ID of the post to comment on
 * @param int $user_id - ID of the user adding the comment
 * @param string $content - Content of the comment
 * @return array - Status, message, and updated comment count
 */
function add_comment($post_id, $user_id, $content)
{
  global $conn;

  // Sanitize comment content
  $content = sanitize_input($content);

  // Add the comment
  $query = "INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("iis", $post_id, $user_id, $content);

  if ($stmt->execute()) {
    // Get updated comment count
    $count_query = "SELECT COUNT(*) AS comment_count FROM comments WHERE post_id = ?";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("i", $post_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $comment_count = $count_result->fetch_assoc()['comment_count'];

    return [
      'status' => 'success',
      'message' => 'Comment added successfully.',
      'comment_count' => $comment_count
    ];
  } else {
    return [
      'status' => 'error',
      'message' => 'Failed to add comment.'
    ];
  }
}

/**
 * Function to get comments for a post
 *
 * @param int $post_id - ID of the post to get comments for
 * @return array - Comments with user information
 */
function get_comments($post_id)
{
  global $conn;

  // Get comments for the post
  $query = "SELECT c.*, u.username, u.profile_pic
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
    // Calculate time ago
    $comment_date = new DateTime($row['created_at']);
    $now = new DateTime();
    $interval = $comment_date->diff($now);

    if ($interval->y > 0) {
      $time_ago = $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
    } elseif ($interval->m > 0) {
      $time_ago = $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
    } elseif ($interval->d > 0) {
      $time_ago = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
    } elseif ($interval->h > 0) {
      $time_ago = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
    } elseif ($interval->i > 0) {
      $time_ago = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
    } else {
      $time_ago = 'Just now';
    }

    $comments[] = [
      'comment_id' => $row['comment_id'],
      'user_id' => $row['user_id'],
      'username' => $row['username'],
      'profile_pic' => $row['profile_pic'],
      'content' => $row['content'],
      'created_at' => $row['created_at'],
      'time_ago' => $time_ago
    ];
  }

  return [
    'status' => 'success',
    'comments' => $comments
  ];
}

/**
 * Function to report a user
 *
 * @param int $reporter_id - ID of the user reporting
 * @param int $reported_id - ID of the user being reported
 * @param string $reason - Reason for the report
 * @return array - Status and message
 */
function report_user($reporter_id, $reported_id, $reason)
{
  global $conn;

  // Sanitize reason
  $reason = sanitize_input($reason);

  // Prevent reporting yourself
  if ($reporter_id == $reported_id) {
    return [
      'status' => 'error',
      'message' => 'You cannot report yourself.'
    ];
  }

  // Add the report
  $query = "INSERT INTO reports (reporter_id, reported_id, reason) VALUES (?, ?, ?)";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("iis", $reporter_id, $reported_id, $reason);

  if ($stmt->execute()) {
    return [
      'status' => 'success',
      'message' => 'User reported successfully.'
    ];
  } else {
    return [
      'status' => 'error',
      'message' => 'Failed to report user.'
    ];
  }
}

/**
 * Function to block/unblock a user
 *
 * @param int $blocker_id - ID of the user doing the blocking
 * @param int $blocked_id - ID of the user being blocked
 * @param string $action - 'block' or 'unblock'
 * @return array - Status and message
 */
function manage_block($blocker_id, $blocked_id, $action)
{
  global $conn;

  // Prevent blocking yourself
  if ($blocker_id == $blocked_id) {
    return [
      'status' => 'error',
      'message' => 'You cannot block yourself.'
    ];
  }

  if ($action == 'block') {
    // Check if already blocked
    $check_query = "SELECT block_id FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $blocker_id, $blocked_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
      return [
        'status' => 'error',
        'message' => 'User is already blocked.'
      ];
    }

    // Block the user
    $block_query = "INSERT INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)";
    $block_stmt = $conn->prepare($block_query);
    $block_stmt->bind_param("ii", $blocker_id, $blocked_id);

    if ($block_stmt->execute()) {
      return [
        'status' => 'success',
        'message' => 'User blocked successfully.'
      ];
    } else {
      return [
        'status' => 'error',
        'message' => 'Failed to block user.'
      ];
    }
  } elseif ($action == 'unblock') {
    // Unblock the user
    $unblock_query = "DELETE FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?";
    $unblock_stmt = $conn->prepare($unblock_query);
    $unblock_stmt->bind_param("ii", $blocker_id, $blocked_id);

    if ($unblock_stmt->execute()) {
      return [
        'status' => 'success',
        'message' => 'User unblocked successfully.'
      ];
    } else {
      return [
        'status' => 'error',
        'message' => 'Failed to unblock user.'
      ];
    }
  } else {
    return [
      'status' => 'error',
      'message' => 'Invalid action. Must be "block" or "unblock".'
    ];
  }
}

/**
 * Function for admin to block a user for a specific duration
 *
 * @param int $user_id - ID of the user to block
 * @param int $days - Number of days to block the user
 * @return array - Status and message
 */
function admin_block_user($user_id, $days)
{
  global $conn;

  // Calculate block end date
  $block_end = date('Y-m-d H:i:s', strtotime("+$days days"));

  // Block the user
  $block_query = "UPDATE users SET is_blocked = TRUE, block_end_date = ? WHERE user_id = ?";
  $block_stmt = $conn->prepare($block_query);
  $block_stmt->bind_param("si", $block_end, $user_id);

  if ($block_stmt->execute()) {
    return [
      'status' => 'success',
      'message' => "User blocked successfully until $block_end."
    ];
  } else {
    return [
      'status' => 'error',
      'message' => 'Failed to block user.'
    ];
  }
}

/**
 * Function for admin to delete a user
 *
 * @param int $user_id - ID of the user to delete
 * @param int $admin_id - ID of the admin performing the action
 * @return array - Status and message
 */
function admin_delete_user($user_id, $admin_id)
{
  global $conn;

  // Prevent deleting yourself
  if ($user_id == $admin_id) {
    return [
      'status' => 'error',
      'message' => 'You cannot delete your own admin account.'
    ];
  }

  // Delete the user
  $delete_query = "DELETE FROM users WHERE user_id = ?";
  $delete_stmt = $conn->prepare($delete_query);
  $delete_stmt->bind_param("i", $user_id);

  if ($delete_stmt->execute()) {
    return [
      'status' => 'success',
      'message' => 'User deleted successfully.'
    ];
  } else {
    return [
      'status' => 'error',
      'message' => 'Failed to delete user.'
    ];
  }
}

/**
 * Function for admin to resolve a report
 *
 * @param int $report_id - ID of the report to resolve
 * @return array - Status and message
 */
function admin_resolve_report($report_id)
{
  global $conn;

  // Mark report as resolved
  $resolve_query = "UPDATE reports SET status = 'resolved' WHERE report_id = ?";
  $resolve_stmt = $conn->prepare($resolve_query);
  $resolve_stmt->bind_param("i", $report_id);

  if ($resolve_stmt->execute()) {
    return [
      'status' => 'success',
      'message' => 'Report marked as resolved.'
    ];
  } else {
    return [
      'status' => 'error',
      'message' => 'Failed to resolve report.'
    ];
  }
}

/**
 * Function to send a message between users
 *
 * @param int $sender_id - ID of the user sending the message
 * @param int $receiver_id - ID of the user receiving the message
 * @param string $content - Content of the message
 * @return array - Status and message
 */
function send_message($sender_id, $receiver_id, $content)
{
  global $conn;

  // Sanitize message content
  $content = sanitize_input($content);

  // Check if users are blocked
  $block_check_query = "SELECT block_id FROM blocked_users
                         WHERE (blocker_id = ? AND blocked_id = ?)
                            OR (blocker_id = ? AND blocked_id = ?)";
  $block_check_stmt = $conn->prepare($block_check_query);
  $block_check_stmt->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
  $block_check_stmt->execute();
  $block_check_result = $block_check_stmt->get_result();

  if ($block_check_result->num_rows > 0) {
    return [
      'status' => 'error',
      'message' => 'Cannot send message. User is blocked.'
    ];
  }

  // Send the message
  $query = "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("iis", $sender_id, $receiver_id, $content);

  if ($stmt->execute()) {
    return [
      'status' => 'success',
      'message' => 'Message sent successfully.'
    ];
  } else {
    return [
      'status' => 'error',
      'message' => 'Failed to send message.'
    ];
  }
}

/**
 * Function to mark messages as read
 *
 * @param int $sender_id - ID of the message sender
 * @param int $receiver_id - ID of the message receiver
 * @return boolean - True if successful, false otherwise
 */
function mark_messages_read($sender_id, $receiver_id)
{
  global $conn;

  $query = "UPDATE messages SET is_read = 1
              WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("ii", $sender_id, $receiver_id);

  return $stmt->execute();
}

/**
 * Function to create a new post
 *
 * @param int $user_id - ID of the user creating the post
 * @param string $content - Content of the post
 * @param string $image_path - Path to the uploaded image (optional)
 * @param float $latitude - Latitude of the post location (optional)
 * @param float $longitude - Longitude of the post location (optional)
 * @param string $location_name - Name of the post location (optional)
 * @return array - Status and message
 */
function create_post($user_id, $content, $image_path = null, $latitude = null, $longitude = null, $location_name = null)
{
  global $conn;

  // Sanitize inputs
  $content = sanitize_input($content);
  $location_name = $location_name ? sanitize_input($location_name) : null;

  // Create the post
  $query = "INSERT INTO posts (user_id, content, image, latitude, longitude, location_name)
              VALUES (?, ?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("issdds", $user_id, $content, $image_path, $latitude, $longitude, $location_name);

  if ($stmt->execute()) {
    return [
      'status' => 'success',
      'message' => 'Post created successfully!'
    ];
  } else {
    return [
      'status' => 'error',
      'message' => 'Failed to create post. Please try again.'
    ];
  }
}

/**
 * Get count of unread messages for a user
 *
 * @param int $user_id - ID of the user
 * @return int - Count of unread messages
 */
function get_unread_messages_count($user_id)
{
  global $conn;

  $query = "SELECT COUNT(*) AS unread FROM messages WHERE receiver_id = ? AND is_read = 0";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->fetch_assoc()['unread'];
}

/**
 * Get user's profile picture
 *
 * @param int $user_id - ID of the user
 * @return string - Path to profile picture
 */
function get_user_profile_pic($user_id)
{
  global $conn;

  $query = "SELECT profile_pic FROM users WHERE user_id = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($row = $result->fetch_assoc()) {
    return $row['profile_pic'] ?: 'uploads/default.jpg';
  }

  return 'uploads/default.jpg';
}

/*
 * PAGE ROUTING SECTION
 */

// Check which file is being accessed
$current_file = basename($_SERVER['SCRIPT_FILENAME']);

// Only run the routing code if this file is accessed as index.php
if ($current_file == 'index.php') {
  // Basic router
  $page = isset($_GET['page']) ? sanitize_input($_GET['page']) : 'login';

  // Check authentication for protected pages
  $protected_pages = ['home', 'profile', 'messages', 'friends', 'settings'];
  $admin_pages = ['admin_dashboard', 'admin_users', 'admin_reports'];

  if (in_array($page, $protected_pages) && !is_logged_in()) {
    // Redirect to login if trying to access protected page without being logged in
    header('Location: index.php?page=login');
    exit;
  }

  if (in_array($page, $admin_pages) && !is_admin()) {
    // Redirect to login if trying to access admin page without admin privileges
    header('Location: index.php?page=login&error=unauthorized');
    exit;
  }

  // If user is logged in and tries to access login page, redirect to home
  if ($page === 'login' && is_logged_in()) {
    if (is_admin()) {
      header('Location: index.php?page=admin_dashboard');
    } else {
      header('Location: index.php?page=home');
    }
    exit;
  }

  // Handle AJAX requests
  $ajax_endpoints = ['like_post', 'add_comment', 'get_comments', 'report_user', 'block_user', 'send_message'];
  if (in_array($page, $ajax_endpoints)) {
    handle_ajax_request($page);
    exit;
  }

  // Include the HTML header
  include_header($page);

  // Route to appropriate page display function
  switch ($page) {
    case 'login':
      show_login_page();
      break;
    case 'home':
      show_home_page();
      break;
    case 'profile':
      show_profile_page();
      break;
    case 'messages':
      show_messages_page();
      break;
    case 'admin_dashboard':
      show_admin_dashboard();
      break;
    case 'admin_users':
      show_admin_users();
      break;
    case 'admin_reports':
      show_admin_reports();
      break;
    case 'logout':
      logout_user();
      break;
    default:
      show_404_page();
      break;
  }

  // Include the HTML footer
  include_footer();
}
// If accessed directly (as core_functions.php)
else {
  // Display the core functions documentation page
?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Core Functions - SocialConnect</title>
    <style>
      body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 20px;
        background-color:
          #f0f2f5;
        color: #333;
      }

      .container {
        max-width: 1000px;
        margin: 0 auto;
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      }

      h1 {
        color:
          #1877f2;
        border-bottom: 1px solid #ddd;
        padding-bottom: 10px;
      }

      h2 {
        color:
          #1877f2;
        margin-top: 30px;
      }

      .function-list {
        margin-top: 20px;
      }

      .function {
        background-color:
          #f7f7f7;
        padding: 15px;
        margin-bottom: 10px;
        border-left: 4px solid #1877f2;
        border-radius: 4px;
      }

      .function h3 {
        margin-top: 0;
        color: #333;
      }

      .connection-status {
        padding: 10px;
        margin-top: 20px;
        border-radius: 4px;
      }

      .success {
        background-color:
          #e8f5e9;
        color:
          #2e7d32;
        border-left: 4px solid #2e7d32;
      }

      .error {
        background-color:
          #ffebee;
        color:
          #c62828;
        border-left: 4px solid #c62828;
      }

      .footer {
        text-align: center;
        margin-top: 30px;
        color: #666;
        font-size: 0.9em;
      }
    </style>
  </head>

  <body>
    <div class="container">
      <h1>SocialConnect Core Functions</h1>

      <div class="connection-status <?php echo $conn->ping() ? 'success' : 'error'; ?>">
        <strong>Database Connection Status:</strong>
        <?php echo $conn->ping() ? 'Connected successfully to ' . $config['DB_NAME'] : 'Failed to connect to database'; ?>
      </div>

      <h2>Available Functions</h2>
      <div class="function-list">
        <div class="function">
          <h3>Authentication Functions</h3>
          <ul>
            <li><strong>register_user()</strong> - Register a new user account</li>
            <li><strong>login_user()</strong> - Authenticate and log in a user</li>
            <li><strong>admin_login()</strong> - Authenticate and log in an admin</li>
            <li><strong>logout_user()</strong> - Log out a user by destroying the session</li>
            <li><strong>is_logged_in()</strong> - Check if a user is currently logged in</li>
            <li><strong>is_admin()</strong> - Check if the logged-in user is an admin</li>
          </ul>
        </div>

        <div class="function">
          <h3>Post Functions</h3>
          <ul>
            <li><strong>create_post()</strong> - Create a new post with optional image and location</li>
            <li><strong>toggle_like()</strong> - Like or unlike a post</li>
            <li><strong>add_comment()</strong> - Add a comment to a post</li>
            <li><strong>get_comments()</strong> - Get all comments for a specific post</li>
          </ul>
        </div>

        <div class="function">
          <h3>User Management Functions</h3>
          <ul>
            <li><strong>is_blocked()</strong> - Check if a user account is blocked</li>
            <li><strong>manage_block()</strong> - Block or unblock a user</li>
            <li><strong>report_user()</strong> - Report a user for inappropriate behavior</li>
          </ul>
        </div>

        <div class="function">
          <h3>Messaging Functions</h3>
          <ul>
            <li><strong>send_message()</strong> - Send a message to another user</li>
            <li><strong>mark_messages_read()</strong> - Mark messages as read</li>
          </ul>
        </div>

        <div class="function">
          <h3>Admin Functions</h3>
          <ul>
            <li><strong>admin_block_user()</strong> - Block a user for a specific duration</li>
            <li><strong>admin_delete_user()</strong> - Delete a user account</li>
            <li><strong>admin_resolve_report()</strong> - Mark a user report as resolved</li>
          </ul>
        </div>

        <div class="function">
          <h3>Utility Functions</h3>
          <ul>
            <li><strong>sanitize_input()</strong> - Sanitize user input to prevent SQL injection and XSS</li>
            <li><strong>redirect()</strong> - Redirect to another page</li>
          </ul>
        </div>
      </div>

      <div class="footer">
        <p>SocialConnect Core Functions - Version 1.0</p>
        <p>&copy; <?php echo date('Y'); ?> SocialConnect. All rights reserved.</p>
      </div>
    </div>
  </body>

  </html>
<?php
}

/**
 * Handle AJAX requests
 */
function handle_ajax_request($endpoint)
{
  global $conn;

  // Check if user is logged in for all AJAX requests
  if (!is_logged_in()) {
    echo json_encode([
      'status' => 'error',
      'message' => 'You must be logged in to perform this action.'
    ]);
    exit;
  }

  $user_id = $_SESSION['user_id'];

  switch ($endpoint) {
    case 'like_post':
      // Like/unlike a post
      if (!isset($_POST['post_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Post ID is required']);
        exit;
      }

      $post_id = (int)$_POST['post_id'];
      $result = toggle_like($post_id, $user_id);
      echo json_encode($result);
      break;

    case 'add_comment':
      // Add a comment to a post
      if (!isset($_POST['post_id']) || !isset($_POST['content']) || empty($_POST['content'])) {
        echo json_encode(['status' => 'error', 'message' => 'Post ID and comment content are required']);
        exit;
      }

      $post_id = (int)$_POST['post_id'];
      $content = $_POST['content'];
      $result = add_comment($post_id, $user_id, $content);
      echo json_encode($result);
      break;

    case 'get_comments':
      // Get comments for a post
      if (!isset($_GET['post_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Post ID is required']);
        exit;
      }

      $post_id = (int)$_GET['post_id'];
      $result = get_comments($post_id);
      echo json_encode($result);
      break;

    case 'report_user':
      // Report a user
      if (!isset($_POST['reported_id']) || !isset($_POST['reason']) || empty($_POST['reason'])) {
        echo json_encode(['status' => 'error', 'message' => 'Reported user ID and reason are required']);
        exit;
      }

      $reported_id = (int)$_POST['reported_id'];
      $reason = $_POST['reason'];
      $result = report_user($user_id, $reported_id, $reason);
      echo json_encode($result);
      break;

    case 'block_user':
      // Block/unblock a user
      if (!isset($_POST['user_id']) || !isset($_POST['action'])) {
        echo json_encode(['status' => 'error', 'message' => 'User ID and action are required']);
        exit;
      }

      $blocked_id = (int)$_POST['user_id'];
      $action = $_POST['action'];
      $result = manage_block($user_id, $blocked_id, $action);
      echo json_encode($result);
      break;

    case 'send_message':
      // Send a message
      if (!isset($_POST['receiver_id']) || !isset($_POST['content']) || empty($_POST['content'])) {
        echo json_encode(['status' => 'error', 'message' => 'Receiver ID and message content are required']);
        exit;
      }

      $receiver_id = (int)$_POST['receiver_id'];
      $content = $_POST['content'];
      $result = send_message($user_id, $receiver_id, $content);
      echo json_encode($result);
      break;

    default:
      echo json_encode(['status' => 'error', 'message' => 'Invalid endpoint']);
  }
}

/**
 * Include the appropriate header based on the page
 */
function include_header($page)
{
  // Common HTML header with appropriate title based on page
  $title = "SocialConnect";

  switch ($page) {
    case 'login':
      $title .= " - Login";
      break;
    case 'home':
      $title .= " - Home";
      break;
    case 'profile':
      $title .= " - Profile";
      break;
    case 'messages':
      $title .= " - Messages";
      break;
    case 'admin_dashboard':
      $title .= " - Admin Dashboard";
      break;
    default:
      $title .= " - " . ucfirst($page);
  }

?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <?php if (in_array($page, ['home'])): ?>
      <!-- Include Google Maps API for posts with location -->
      <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places"></script>
    <?php endif; ?>
  </head>

  <body>
    <div class="container">
      <?php if (is_logged_in() && $page !== 'login'): ?>
        <!-- Navigation Bar for logged-in users -->
        <nav class="navbar">
          <a href="index.php?page=home" class="navbar-brand">SocialConnect</a>
          <ul class="navbar-nav">
            <li class="nav-item">
              <a href="index.php?page=messages">
                <i class="fas fa-envelope"></i>
                <?php
                // Check for unread messages
                $user_id = $_SESSION['user_id'];
                $unread_messages = get_unread_messages_count($user_id);
                if ($unread_messages > 0):
                ?>
                  <span class="unread-badge"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
              </a>
            </li>
            <li class="nav-item">
              <a href="index.php?page=profile">
                <?php
                $profile_pic = get_user_profile_pic($_SESSION['user_id']);
                ?>
                <img src="<?php echo $profile_pic; ?>" alt="Profile" class="profile-pic">
              </a>
            </li>
            <li class="nav-item">
              <a href="index.php?page=logout" class="btn btn-secondary">Logout</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    <?php
  }

  /**
   * Include the footer HTML
   */
  function include_footer()
  {
    ?>
      <footer class="main-footer">
        <p>&copy; <?php echo date('Y'); ?> SocialConnect. All rights reserved.</p>
      </footer>
    </div>
  </body>

  </html>
<?php
  }

  /**
   * Show the login/registration page
   */
  function show_login_page()
  {
    // Initialize variables for form data and error messages
    $login_error = '';
    $register_error = '';
    $register_success = '';

    // Handle login form submission
    if (isset($_POST['login'])) {
      $username = $_POST['username'] ?? '';
      $password = $_POST['password'] ?? '';
      $role = $_POST['role'] ?? 'user';

      if (empty($username) || empty($password)) {
        $login_error = 'Please enter both username and password.';
      } else {
        // Handle login based on role
        if ($role == 'admin') {
          $result = admin_login($username, $password);
        } else {
          $result = login_user($username, $password);
        }

        if ($result['status'] == 'success') {
          // Redirect to appropriate dashboard
          if ($role == 'admin') {
            header('Location: index.php?page=admin_dashboard');
          } else {
            header('Location: index.php?page=home');
          }
          exit;
        } else {
          $login_error = $result['message'];
        }
      }
    }

    // Handle registration form submission
    if (isset($_POST['register'])) {
      $username = $_POST['reg_username'] ?? '';
      $email = $_POST['reg_email'] ?? '';
      $password = $_POST['reg_password'] ?? '';
      $confirm_password = $_POST['reg_confirm_password'] ?? '';
      $full_name = $_POST['reg_full_name'] ?? '';
      $security_question = $_POST['security_question'] ?? '';
      $security_answer = $_POST['security_answer'] ?? '';

      // Validate inputs
      if (
        empty($username) || empty($email) || empty($password) || empty($confirm_password) ||
        empty($full_name) || empty($security_question) || empty($security_answer)
      ) {
        $register_error = 'Please fill all required fields.';
      } elseif ($password != $confirm_password) {
        $register_error = 'Passwords do not match.';
      } elseif (strlen($password) < 8) {
        $register_error = 'Password must be at least 8 characters long.';
      } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = 'Please enter a valid email address.';
      } else {
        // Register the user
        $result = register_user($username, $email, $password, $full_name, $security_question, $security_answer);

        if ($result['status'] == 'success') {
          $register_success = $result['message'];
        } else {
          $register_error = $result['message'];
        }
      }
    }

    // Display the login/registration form
?>
  <header class="main-header">
    <h1>SocialConnect</h1>
    <p>Connect with friends, share moments, and stay updated.</p>
  </header>

  <main class="auth-container">
    <div class="forms-container">
      <!-- Login Form -->
      <div class="form-box login-form">
        <h2>Login</h2>
        <?php if (!empty($login_error)): ?>
          <div class="error-message"><?php echo $login_error; ?></div>
        <?php endif; ?>

        <form action="index.php?page=login" method="post">
          <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
          </div>

          <div class="form-group">
            <label>Login As:</label>
            <div class="radio-group">
              <label>
                <input type="radio" name="role" value="user" checked> User
              </label>
              <label>
                <input type="radio" name="role" value="admin"> Admin
              </label>
            </div>
          </div>

          <div class="form-group">
            <button type="submit" name="login" class="btn btn-primary">Login</button>
          </div>

          <p class="form-toggle-link">Don't have an account? <a href="#" id="show-register">Register now</a></p>
        </form>
      </div>

      <!-- Registration Form -->
      <div class="form-box register-form" style="display: none;">
        <h2>Create an Account</h2>
        <?php if (!empty($register_error)): ?>
          <div class="error-message"><?php echo $register_error; ?></div>
        <?php endif; ?>

        <?php if (!empty($register_success)): ?>
          <div class="success-message"><?php echo $register_success; ?></div>
        <?php endif; ?>

        <form action="index.php?page=login" method="post">
          <div class="form-group">
            <label for="reg_username">Username</label>
            <input type="text" id="reg_username" name="reg_username" required>
          </div>

          <div class="form-group">
            <label for="reg_email">Email</label>
            <input type="email" id="reg_email" name="reg_email" required>
          </div>

          <div class="form-group">
            <label for="reg_full_name">Full Name</label>
            <input type="text" id="reg_full_name" name="reg_full_name" required>
          </div>

          <div class="form-group">
            <label for="reg_password">Password</label>
            <input type="password" id="reg_password" name="reg_password" required>
            <small>Password must be at least 8 characters long</small>
          </div>

          <div class="form-group">
            <label for="reg_confirm_password">Confirm Password</label>
            <input type="password" id="reg_confirm_password" name="reg_confirm_password" required>
          </div>

          <div class="form-group">
            <label for="security_question">Security Question</label>
            <input type="text" id="security_question" name="security_question" required placeholder="Enter a security question for account recovery">
          </div>

          <div class="form-group">
            <label for="security_answer">Security Answer</label>
            <input type="text" id="security_answer" name="security_answer" required placeholder="Enter your answer to the security question">
          </div>

          <div class="form-group">
            <button type="submit" name="register" class="btn btn-primary">Register</button>
          </div>

          <p class="form-toggle-link">Already have an account? <a href="#" id="show-login">Login now</a></p>
        </form>
      </div>
    </div>
  </main>

  <script>
    $(document).ready(function() {
      // Toggle between login and registration forms
      $('#show-register').click(function(e) {
        e.preventDefault();
        $('.login-form').hide();
        $('.register-form').show();
      });

      $('#show-login').click(function(e) {
        e.preventDefault();
        $('.register-form').hide();
        $('.login-form').show();
      });

      // Show register form if there was an error or success message
      <?php if (!empty($register_error) || !empty($register_success)): ?>
        $('.login-form').hide();
        $('.register-form').show();
      <?php endif; ?>
    });
  </script>
<?php
  }

  /**
   * Show the home page with news feed
   */
  function show_home_page()
  {
    global $conn;
    $user_id = $_SESSION['user_id'];
    $query = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    echo "<div class='profile-info'><h2>" . $user['username'] . "</h2><p>Email: " . $user['email'] . "</p></div>
    <div class='profile-actions'><button onclick='changePassword()'>Change Password</button><a href='index.php?page=logout' class='btn-logout'>Logout</a></div>";

    // Handle post submission
    $post_message = '';

    if (isset($_POST['create_post'])) {
      $content = $_POST['post_content'] ?? '';
      $latitude = $_POST['latitude'] ?? null;
      $longitude = $_POST['longitude'] ?? null;
      $location_name = $_POST['location_name'] ?? null;

      // Check if content is provided
      if (empty($content)) {
        $post_message = 'Post content cannot be empty.';
      } else {
        $content = sanitize_input($content);

        // Handle image upload
        $image_path = null;

        if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == 0) {
          $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
          $max_size = 5 * 1024 * 1024; // 5 MB

          // Validate image file
          if (!in_array($_FILES['post_image']['type'], $allowed_types)) {
            $post_message = 'Only JPG, PNG and GIF images are allowed.';
          } elseif ($_FILES['post_image']['size'] > $max_size) {
            $postmessage = 'Image size should not exceed 5 MB.';
          } else {
            // Create unique filename
            $filename = uniqid() . '' . basename($_FILES['post_image']['name']);
            $upload_dir = 'uploads/';

            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
              mkdir($upload_dir, 0777, true);
            }

            $upload_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['post_image']['tmp_name'], $upload_path)) {
              $image_path = $upload_path;
            } else {
              $post_message = 'Failed to upload image. Please try again.';
            }
          }
        }

        // If no errors, create the post
        if (empty($post_message)) {
          $result = create_post($user_id, $content, $image_path, $latitude, $longitude, $location_name);

          if ($result['status'] == 'success') {
            // Redirect to avoid form resubmission
            header('Location: index.php?page=home&posted=1');
            exit;
          } else {
            $post_message = $result['message'];
          }
        }
      }
    }

    // Get posts for the news feed
    $query = "SELECT p.*, u.username, u.profile_pic,
              (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) AS like_count,
              (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) AS comment_count,
              (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id AND user_id = ?) AS user_liked
              FROM posts p
              JOIN users u ON p.user_id = u.user_id
              WHERE p.user_id NOT IN (SELECT blocked_id FROM blocked_users WHERE blocker_id = ?)
              ORDER BY p.created_at DESC
              LIMIT 20";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $posts_result = $stmt->get_result();

    // Get online friends
    $friends_query = "SELECT u.user_id, u.username, u.profile_pic
                    FROM users u
                    WHERE u.user_id != ?
                    AND u.user_id NOT IN (
                        SELECT blocked_id FROM blocked_users WHERE blocker_id = ?
                    )
                    LIMIT 5";
    $friends_stmt = $conn->prepare($friends_query);
    $friends_stmt->bind_param("ii", $user_id, $user_id);
    $friends_stmt->execute();
    $friends_result = $friends_stmt->get_result();

    // Home page HTML and functionality (as in your original code)
    // ...
  }

  /**
   * Show 404 page not found
   */
  function show_404_page()
  {
?>
  <div class="error-container text-center">
    <h1>404</h1>
    <h2>Page Not Found</h2>
    <p>The page you are looking for does not exist.</p>
    <a href="index.php" class="btn btn-primary">Go Home</a>
  </div>
  <?php
  }

  /**
   * Placeholder functions for other pages - implement these as needed
   */
  function show_profile_page()
  {
    global $conn;
    $user_id = $_SESSION['user_id'];
    $query = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    echo "<div class='profile-card'><img src='" . ($user['profile_pic'] ?: 'uploads/default.jpg') . "' alt='Profile' class='profile-icon'></div>
  <div class='profile-info'><h2>" . $user['username'] . "</h2><p>Email: " . $user['email'] . "</p></div>
  <div class='profile-actions'><button onclick='changePassword()'>Change Password</button><a href='index.php?page=logout' class='btn-logout'>Logout</a></div>";
  }

  function show_messages_page()
  {
    // Implement messages page display
    echo "<div class='text-center p-20'><h2>Messages Page</h2><p>This page is under construction.</p></div>";
  }

  function show_admin_dashboard()
  {
    // Implement admin dashboard display
    echo "<div class='text-center p-20'><h2>Admin Dashboard</h2><p>This page is under construction.</p></div>";
  }

  function show_admin_users()
  {
    // Implement admin users management display
    echo "<div class='text-center p-20'><h2>Admin Users Management</h2><p>This page is under construction.</p></div>";
  }

  function show_admin_reports()
  {
    // Implement admin reports display
    echo "<div class='text-center p-20'><h2>Admin Reports</h2><p>This page is under construction.</p></div>";
  }
  ?>'