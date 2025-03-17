<?php
//test_social_features.php
session_start();
require_once '../config/database.php';

echo "<h1>Social Features Test</h1>";

if (!isset($_SESSION['user_id'])) {
  echo "<p style='color:red'>You must be logged in to run this test.</p>";
  echo "<a href='../auth/login.php'>Login here</a>";
  exit;
}

try {
  // Test 1: Check if tables exist
  echo "<h2>1. Database Structure</h2>";

  $tables = ['likes', 'comments'];
  foreach ($tables as $table) {
    $stmt = $conn->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() > 0) {
      echo "<p style='color:green'>✓ Table '$table' exists</p>";
    } else {
      echo "<p style='color:red'>✗ Table '$table' missing</p>";
    }
  }

  // Test 2: Create a test post if needed
  echo "<h2>2. Create Test Post</h2>";

  $stmt = $conn->query("SELECT COUNT(*) as count FROM posts");
  $postCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

  if ($postCount == 0) {
    $stmt = $conn->prepare("INSERT INTO posts (user_id, content) VALUES (?, 'Test post for social features')");
    $stmt->execute([$_SESSION['user_id']]);
    $testPostId = $conn->lastInsertId();
    echo "<p style='color:green'>Created test post with ID: $testPostId</p>";
  } else {
    $stmt = $conn->query("SELECT post_id FROM posts ORDER BY created_at DESC LIMIT 1");
    $testPostId = $stmt->fetch(PDO::FETCH_ASSOC)['post_id'];
    echo "<p>Using existing post with ID: $testPostId</p>";
  }

  // Test 3: Like functionality
  echo "<h2>3. Like Functionality</h2>";

  // Add a test like
  $stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE like_id=like_id");
  $result = $stmt->execute([$testPostId, $_SESSION['user_id']]);

  if ($result) {
    echo "<p style='color:green'>✓ Successfully added like</p>";

    // Count likes
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
    $stmt->execute([$testPostId]);
    $likeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Post has $likeCount likes</p>";

    // Test removing like
    $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$testPostId, $_SESSION['user_id']]);
    echo "<p style='color:green'>✓ Successfully removed like</p>";
  } else {
    echo "<p style='color:red'>✗ Failed to add like</p>";
  }

  // Test 4: Comment functionality
  echo "<h2>4. Comment Functionality</h2>";

  // Add a test comment
  $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, 'Test comment')");
  $result = $stmt->execute([$testPostId, $_SESSION['user_id']]);

  if ($result) {
    $commentId = $conn->lastInsertId();
    echo "<p style='color:green'>✓ Successfully added comment (ID: $commentId)</p>";

    // Count comments
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
    $stmt->execute([$testPostId]);
    $commentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Post has $commentCount comments</p>";
  } else {
    echo "<p style='color:red'>✗ Failed to add comment</p>";
  }

  // Test 5: Ajax endpoints
  echo "<h2>5. API Endpoints</h2>";

  echo "<p>Manual testing required for these endpoints:</p>";
  echo "<ul>";
  echo "<li>like_post.php - Should toggle like status</li>";
  echo "<li>add_comment.php - Should add a new comment</li>";
  echo "</ul>";

  echo "<p>To test interactively, go to the <a href='../feed.php'>feed page</a> and:</p>";
  echo "<ol>";
  echo "<li>Click the like button on a post</li>";
  echo "<li>Try adding a comment on a post</li>";
  echo "</ol>";
} catch (PDOException $e) {
  echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
