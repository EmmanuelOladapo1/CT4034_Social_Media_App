<?php
// Test script for block/report functionality
// Save this as test_block_report.php in your project directory

// Include your main file to access functions
include 'index.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Test user IDs (update these with real user IDs from your database)
$test_user_id = 2; // Your test account
$target_user_id = 3; // User to block/report

// Set session variables to simulate being logged in
$_SESSION['user_id'] = $test_user_id;
$_SESSION['username'] = 'test_user';
$_SESSION['role'] = 'user';

echo "<h2>Testing Block Functionality</h2>";

// Test blocking a user
$block_result = manage_block($test_user_id, $target_user_id, 'block');
echo "Block result: <pre>" . print_r($block_result, true) . "</pre><br>";

// Check if user is blocked
$check_query = "SELECT * FROM blocked_users WHERE blocker_id = $test_user_id AND blocked_id = $target_user_id";
$check_result = $conn->query($check_query);
echo "User blocked status: " . ($check_result->num_rows > 0 ? "Blocked" : "Not blocked") . "<br><br>";

// Test reporting a user
echo "<h2>Testing Report Functionality</h2>";
$report_result = report_user($test_user_id, $target_user_id, 'Test report reason');
echo "Report result: <pre>" . print_r($report_result, true) . "</pre><br>";

// Check if report exists
$report_query = "SELECT * FROM reports WHERE reporter_id = $test_user_id AND reported_id = $target_user_id";
$report_result = $conn->query($report_query);
echo "Report status: " . ($report_result->num_rows > 0 ? "Report submitted" : "No report found") . "<br>";
