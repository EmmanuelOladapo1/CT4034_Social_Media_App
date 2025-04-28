<?php

/**
 * Security Functions for Social Media Website
 *
 * This file contains security-focused functionality for the social media platform
 * including authentication protection, input validation, and password management.
 *
 * @author Student
 * @version 1.0
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/**
 * Security Constants
 */
// Hardcoded admin credentials - only for demonstration purposes
// In a real production environment, admin accounts should be managed in the database
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'S3cure@dmin2025!'); // Strong password example

/**
 * Input Validation Functions
 */

/**
 * Sanitize user input to prevent XSS attacks
 *
 * @param string $input User input to sanitize
 * @return string Sanitized input
 */
function sanitizeInput($input)
{
  return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 *
 * @param string $email Email to validate
 * @return bool True if email is valid
 */
function validateEmail($email)
{
  return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate username format (alphanumeric, underscore, 3-20 characters)
 *
 * @param string $username Username to validate
 * @return bool True if username is valid
 */
function validateUsername($username)
{
  return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username) === 1;
}

/**
 * Validate password strength
 *
 * @param string $password Password to validate
 * @return array Validation result and message
 */
function validatePassword($password)
{
  $errors = [];

  // Check length
  if (strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters long";
  }

  // Check complexity
  if (!preg_match('/[A-Z]/', $password)) {
    $errors[] = "Password must contain at least one uppercase letter";
  }

  if (!preg_match('/[a-z]/', $password)) {
    $errors[] = "Password must contain at least one lowercase letter";
  }

  if (!preg_match('/[0-9]/', $password)) {
    $errors[] = "Password must contain at least one number";
  }

  if (empty($errors)) {
    return ["valid" => true];
  } else {
    return ["valid" => false, "errors" => $errors];
  }
}

/**
 * Password Management Functions
 */

/**
 * Generate a secure password reset token
 *
 * @return string Secure token
 */
function generateResetToken()
{
  return bin2hex(random_bytes(32));
}

/**
 * Initiate password reset process
 *
 * @param string $email User's email
 * @return array Status of reset initiation
 */
function initiatePasswordReset($email)
{
  $conn = dbConnect();

  // Find user by email
  $query = "SELECT user_id, username FROM users WHERE email = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    // Don't reveal if email exists for security
    return ["success" => true, "message" => "If your email is registered, you will receive reset instructions"];
  }

  $user = $result->fetch_assoc();

  // Generate token and expiry time (24 hours)
  $token = generateResetToken();
  $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

  // Store token in database
  $update_query = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?";
  $update_stmt = $conn->prepare($update_query);
  $update_stmt->bind_param("ssi", $token, $expiry, $user['user_id']);
  $update_stmt->execute();

  // In a real application, you would send an email with the reset link
  // For this assignment, we'll just return the token
  return [
    "success" => true,
    "message" => "Password reset initiated",
    "token" => $token,
    "username" => $user['username']
  ];
}

/**
 * Verify password reset token
 *
 * @param string $token Reset token
 * @return array Verification result and user ID if valid
 */
function verifyResetToken($token)
{
  $conn = dbConnect();

  $query = "SELECT user_id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    return ["valid" => false, "message" => "Invalid or expired token"];
  }

  $user = $result->fetch_assoc();
  return ["valid" => true, "user_id" => $user['user_id']];
}

/**
 * Complete password reset with security PIN verification
 *
 * @param string $token Reset token
 * @param string $pin User's security PIN
 * @param string $new_password New password
 * @return array Status of password reset
 */
function resetPasswordWithPin($token, $pin, $new_password)
{
  $conn = dbConnect();

  // Verify token first
  $token_verification = verifyResetToken($token);
  if (!$token_verification["valid"]) {
    return $token_verification;
  }

  $user_id = $token_verification["user_id"];

  // Get user's PIN for verification
  $user_query = "SELECT pin FROM users WHERE user_id = ?";
  $user_stmt = $conn->prepare($user_query);
  $user_stmt->bind_param("i", $user_id);
  $user_stmt->execute();
  $user_result = $user_stmt->get_result();
  $user = $user_result->fetch_assoc();

  // Verify PIN
  if (!password_verify($pin, $user['pin'])) {
    return ["success" => false, "message" => "Invalid security PIN"];
  }

  // Validate new password
  $password_validation = validatePassword($new_password);
  if (!$password_validation["valid"]) {
    return ["success" => false, "errors" => $password_validation["errors"]];
  }

  // Hash new password
  $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

  // Update password and clear reset token
  $update_query = "UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?";
  $update_stmt = $conn->prepare($update_query);
  $update_stmt->bind_param("si", $hashed_password, $user_id);

  if ($update_stmt->execute()) {
    return ["success" => true, "message" => "Password reset successfully"];
  } else {
    return ["success" => false, "message" => "Failed to reset password"];
  }
}

/**
 * Security Question Functions
 */

/**
 * Verify security question answer
 *
 * @param int $user_id User ID
 * @param string $answer Answer to security question
 * @return bool True if answer is correct
 */
function verifySecurityAnswer($user_id, $answer)
{
  $conn = dbConnect();

  $query = "SELECT security_answer FROM users WHERE user_id = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    return false;
  }

  $user = $result->fetch_assoc();
  // Normalize answer (lowercase) and verify
  return password_verify(strtolower($answer), $user['security_answer']);
}

/**
 * Get security question for a user
 *
 * @param string $username Username or email
 * @return array Security question and user ID
 */
function getSecurityQuestion($username)
{
  $conn = dbConnect();

  $query = "SELECT user_id, security_question FROM users WHERE username = ? OR email = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("ss", $username, $username);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    return ["success" => false, "message" => "User not found"];
  }

  $user = $result->fetch_assoc();
  return [
    "success" => true,
    "user_id" => $user['user_id'],
    "security_question" => $user['security_question']
  ];
}

/**
 * Session Security Functions
 */

/**
 * Generate a secure CSRF token
 *
 * @return string CSRF token
 */
function generateCSRFToken()
{
  if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 *
 * @param string $token Token to verify
 * @return bool True if token is valid
 */
function verifyCSRFToken($token)
{
  if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
    return false;
  }
  return true;
}

/**
 * Set session security properties
 */
function secureSession()
{
  // Set session cookie parameters
  $secure = true; // Only transmit over HTTPS
  $httponly = true; // JavaScript cannot access cookie

  // Check if session has started
  if (session_status() === PHP_SESSION_ACTIVE) {
    setcookie(session_name(), session_id(), [
      'expires' => 0,
      'path' => '/',
      'domain' => '',
      'secure' => $secure,
      'httponly' => $httponly,
      'samesite' => 'Lax'
    ]);
  }

  // Set additional security headers
  header("X-Frame-Options: DENY");
  header("X-XSS-Protection: 1; mode=block");
  header("X-Content-Type-Options: nosniff");
  header("Referrer-Policy: strict-origin-when-cross-origin");
  header("Content-Security-Policy: default-src 'self'; script-src 'self' https://maps.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: https://maps.gstatic.com; connect-src 'self' https://maps.googleapis.com; font-src 'self' https://fonts.gstatic.com;");
}

/**
 * Login Attempt Tracking
 */

/**
 * Track failed login attempts
 *
 * @param string $username Username or email attempted
 * @param string $ip IP address of the attempt
 */
function trackFailedLogin($username, $ip)
{
  $conn = dbConnect();

  $query = "INSERT INTO login_attempts (username, ip_address, created_at) VALUES (?, ?, NOW())";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("ss", $username, $ip);
  $stmt->execute();

  // Check for brute force attempts (5 failures in 15 minutes)
  $check_query = "SELECT COUNT(*) as attempt_count FROM login_attempts
                   WHERE (username = ? OR ip_address = ?)
                   AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
  $check_stmt = $conn->prepare($check_query);
  $check_stmt->bind_param("ss", $username, $ip);
  $check_stmt->execute();
  $result = $check_stmt->get_result();
  $row = $result->fetch_assoc();

  return $row['attempt_count'] >= 5;
}

/**
 * Check if login is allowed (not locked out)
 *
 * @param string $username Username or email
 * @param string $ip IP address
 * @return bool True if login is allowed
 */
function isLoginAllowed($username, $ip)
{
  $conn = dbConnect();

  $query = "SELECT COUNT(*) as attempt_count FROM login_attempts
              WHERE (username = ? OR ip_address = ?)
              AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("ss", $username, $ip);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();

  return $row['attempt_count'] < 5;
}

/**
 * Clear login attempts for a user after successful login
 *
 * @param string $username Username or email
 * @param string $ip IP address
 */
function clearLoginAttempts($username, $ip)
{
  $conn = dbConnect();

  $query = "DELETE FROM login_attempts WHERE username = ? OR ip_address = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("ss", $username, $ip);
  $stmt->execute();
}

/**
 * Secure Admin Authentication
 */

/**
 * Authenticate admin using hardcoded credentials
 *
 * @param string $username Admin username attempt
 * @param string $password Admin password attempt
 * @return bool True if authentication succeeds
 */
function authenticateAdmin($username, $password)
{
  // Use the hardcoded admin credentials
  if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
    // Set admin session variables
    $_SESSION['user_id'] = 0; // Special ID for admin
    $_SESSION['username'] = ADMIN_USERNAME;
    $_SESSION['is_admin'] = 1;

    return true;
  }

  return false;
}

/**
 * Database Connection Function
 * This is duplicated from core_functions.php for completeness
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
 * Create necessary database tables if they don't exist
 * Includes security-focused tables like login_attempts
 */
function createSecurityTables()
{
  $conn = dbConnect();

  // Security-focused tables
  $tables = [
    // Login attempts for tracking brute force
    "CREATE TABLE IF NOT EXISTS login_attempts (
            attempt_id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

    // Make sure users table has security fields
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS security_question VARCHAR(255) AFTER password",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS security_answer VARCHAR(255) AFTER security_question",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS pin VARCHAR(255) AFTER security_answer",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) NULL AFTER pin",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expiry DATETIME NULL AFTER reset_token",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS blocked_until DATETIME NULL AFTER is_admin",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS id_verification_required TINYINT(1) DEFAULT 0 AFTER blocked_until"
  ];

  // Execute each query
  foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
      error_log("Error creating table: " . $conn->error);
    }
  }

  $conn->close();
}

// Create security tables when this file is included
createSecurityTables();
