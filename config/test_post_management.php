<?php
//test_post_management.php
session_start();
require_once '../config/database.php';

echo "<h1>Post Management Test</h1>";

if (!isset($_SESSION['user_id'])) {
  echo "<p style='color:red'>You must be logged in to run this test.</p>";
  echo "<a href='../auth/login.php'>Login here</a>";
  exit;
}

try {
  // Create test post if needed
  echo "<h2>1. Create Test Post</h2>";

  $stmt = $conn->prepare("INSERT INTO posts (user_id, content) VALUES (?, 'This is a test post for editing and deleting')");
  $stmt->execute([$_SESSION['user_id']]);
  $post_id = $conn->lastInsertId();

  echo "<p style='color:green'>Created test post with ID: $post_id</p>";

  // Test post editing
  echo "<h2>2. Edit Post Test</h2>";
  $new_content = "This post has been edited for testing";

  $stmt = $conn->prepare("UPDATE posts SET content = ? WHERE post_id = ? AND user_id = ?");
  $result = $stmt->execute([$new_content, $post_id, $_SESSION['user_id']]);

  if ($result) {
    echo "<p style='color:green'>Successfully edited post</p>";

    // Verify content was updated
    $stmt = $conn->prepare("SELECT content FROM posts WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $updated_post = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($updated_post['content'] === $new_content) {
      echo "<p style='color:green'>Verified content was updated correctly</p>";
    } else {
      echo "<p style='color:red'>Content was not updated correctly</p>";
    }
  } else {
    echo "<p style='color:red'>Failed to edit post</p>";
  }

  // Test delete functionality
  echo "<h2>3. Delete Post Test</h2>";

  echo "<p>This will delete the test post with ID: $post_id</p>";
  echo "<a href='../delete_post.php?id=$post_id' style='color:blue;'>Click here to delete the test post</a>";
  echo "<p>After deletion, you should be redirected to the feed page</p>";
} catch (PDOException $e) {
  echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
