<?php
// tests/test_comment_delete.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
  echo "<p style='color:red'>Please log in to run this test</p>";
  echo "<a href='../auth/login.php'>Login</a>";
  exit;
}

echo "<h1>Comment Deletion Test</h1>";

try {
  // Step 1: Create a test post if needed
  echo "<h2>Creating test post</h2>";
  $stmt = $conn->prepare("INSERT INTO posts (user_id, content) VALUES (?, 'Test post for comment deletion')");
  $stmt->execute([$_SESSION['user_id']]);
  $post_id = $conn->lastInsertId();
  echo "<p>Test post created with ID: $post_id</p>";

  // Step 2: Create test comments
  echo "<h2>Creating test comments</h2>";

  // Create a comment by current user
  $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, 'Test comment by current user')");
  $stmt->execute([$post_id, $_SESSION['user_id']]);
  $my_comment_id = $conn->lastInsertId();
  echo "<p>Your comment created with ID: $my_comment_id</p>";

  // Test fetching the comment
  $stmt = $conn->prepare("SELECT * FROM comments WHERE comment_id = ?");
  $stmt->execute([$my_comment_id]);
  $comment = $stmt->fetch(PDO::FETCH_ASSOC);

  echo "<h2>Test Deletion</h2>";
  echo "<p>The following comment belongs to you and should be deletable:</p>";
  echo "<div style='border:1px solid #ccc; padding:10px; margin-bottom:10px;'>";
  echo "<p><strong>Comment ID:</strong> " . $comment['comment_id'] . "</p>";
  echo "<p><strong>Content:</strong> " . $comment['content'] . "</p>";
  echo "<a href='../delete_comment.php?id=$my_comment_id' style='color:red;' onclick='return confirm(\"Are you sure you want to delete this comment?\")'>Delete Comment</a>";
  echo "</div>";

  echo "<p>After deletion, verify that the comment is removed from the database.</p>";

  echo "<h2>Interactive Test</h2>";
  echo "<p>Go to <a href='../feed.php'>the feed page</a> and:</p>";
  echo "<ol>";
  echo "<li>Find a post and add a new comment</li>";
  echo "<li>Verify your comment appears with a Delete button</li>";
  echo "<li>Click Delete and confirm</li>";
  echo "<li>Verify the comment is removed</li>";
  echo "</ol>";
} catch (PDOException $e) {
  echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
