<?php
session_start();

/**
 * core_functions.php
 * Contains all core functions and database connection for the social media platform
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
