<?php

/**
 * db_connection.php
 * Database connection and utility functions
 */

// Database credentials
$host = "localhost:3306";
$username = "s4413678_db"; // Change this to your actual database username
$password = "7q0fe22B~"; // Change this to your actual database password
$database = "social_media_db";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

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
?>
<?php
/**
 * auth.php
 * Authentication System for Social Media Platform
 */

// Start session
session_start();

// Include database connection
require_once 'db_connection.php';

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
 * Function to verify admin login with hardcoded password
 * Note: For better security, this should be replaced with database authentication
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
?>
<?php
/**
 * index.php
 * Entry point for the social media application
 */

// Start session and include necessary files
session_start();
require_once 'db_connection.php';
require_once 'auth.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
  // Redirect to appropriate dashboard
  if ($_SESSION['role'] == 'admin') {
    redirect('admin_dashboard.php');
  } else {
    redirect('home.php');
  }
}

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
        redirect('admin_dashboard.php');
      } else {
        redirect('home.php');
      }
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SocialConnect - Connect with Friends</title>
  <link rel="stylesheet" href="css/style.css">
  <!-- Include jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
  <div class="container">
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

          <form action="index.php" method="post">
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

          <form action="index.php" method="post">
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

    <footer class="main-footer">
      <p>&copy; <?php echo date('Y'); ?> SocialConnect. All rights reserved.</p>
    </footer>
  </div>

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
</body>

</html>
<?php
/**
 * logout.php
 * Handles user logout
 */

// Start session
session_start();

// Include necessary files
require_once 'db_connection.php';
require_once 'auth.php';

// Call the logout function
logout_user();
?>