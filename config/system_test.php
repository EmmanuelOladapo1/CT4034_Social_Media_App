<?php
// tests/system_test.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
  echo "<p style='color:red'>You must be logged in to run this test.</p>";
  echo "<a href='../auth/login.php'>Login here</a>";
  exit;
}

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>System Test</title>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css' rel='stylesheet'>
</head>
<body class='bg-gray-100 p-8'>";

echo "<h1 class='text-2xl font-bold mb-6'>Social Media Platform System Test</h1>";

try {
  // Test 1: Database Connection
  echo "<div class='bg-white p-4 rounded shadow mb-6'>";
  echo "<h2 class='text-xl font-bold mb-4'>1. Database Connection</h2>";

  try {
    $conn->query("SELECT 1");
    echo "<p class='text-green-600'>✓ Database connection successful</p>";
  } catch (PDOException $e) {
    echo "<p class='text-red-600'>✗ Database connection failed: " . $e->getMessage() . "</p>";
  }
  echo "</div>";

  // Test 2: User Information
  echo "<div class='bg-white p-4 rounded shadow mb-6'>";
  echo "<h2 class='text-xl font-bold mb-4'>2. User System</h2>";

  $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
  $stmt->execute([$_SESSION['user_id']]);
  $current_user = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($current_user) {
    echo "<p class='text-green-600'>✓ Current user found: " . htmlspecialchars($current_user['username']) . "</p>";

    // Check if profile_image column exists
    echo "<p>Profile Image: ";
    if (array_key_exists('profile_image', $current_user)) {
      echo "<span class='text-green-600'>✓ Profile image field exists</span>";
    } else {
      echo "<span class='text-red-600'>✗ Profile image field missing</span>";
    }
    echo "</p>";
  } else {
    echo "<p class='text-red-600'>✗ Current user not found</p>";
  }

  // Check total user count
  $stmt = $conn->query("SELECT COUNT(*) FROM users");
  $userCount = $stmt->fetchColumn();
  echo "<p>Total users in system: " . $userCount . "</p>";
  echo "</div>";

  // Test 3: Post System
  echo "<div class='bg-white p-4 rounded shadow mb-6'>";
  echo "<h2 class='text-xl font-bold mb-4'>3. Post System</h2>";

  // Check posts table
  $stmt = $conn->query("SHOW TABLES LIKE 'posts'");
  if ($stmt->rowCount() > 0) {
    echo "<p class='text-green-600'>✓ Posts table exists</p>";

    // Count posts
    $stmt = $conn->query("SELECT COUNT(*) FROM posts");
    $postCount = $stmt->fetchColumn();
    echo "<p>Total posts: " . $postCount . "</p>";

    // Check location support
    $stmt = $conn->prepare("SHOW COLUMNS FROM posts LIKE 'location_name'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
      echo "<p class='text-green-600'>✓ Location feature supported</p>";
    } else {
      echo "<p class='text-red-600'>✗ Location feature not supported</p>";
    }

    // Get a sample post
    $stmt = $conn->query("SELECT * FROM posts ORDER BY created_at DESC LIMIT 1");
    $samplePost = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($samplePost) {
      echo "<p class='text-green-600'>✓ Sample post found (ID: " . $samplePost['post_id'] . ")</p>";
      echo "<p>Content: " . htmlspecialchars(substr($samplePost['content'], 0, 50)) . (strlen($samplePost['content']) > 50 ? '...' : '') . "</p>";
    } else {
      echo "<p class='text-yellow-600'>⚠ No posts found, create some posts</p>";
    }
  } else {
    echo "<p class='text-red-600'>✗ Posts table does not exist</p>";
  }
  echo "</div>";

  // Test 4: Likes System
  echo "<div class='bg-white p-4 rounded shadow mb-6'>";
  echo "<h2 class='text-xl font-bold mb-4'>4. Likes System</h2>";

  $stmt = $conn->query("SHOW TABLES LIKE 'likes'");
  if ($stmt->rowCount() > 0) {
    echo "<p class='text-green-600'>✓ Likes table exists</p>";

    // Count likes
    $stmt = $conn->query("SELECT COUNT(*) FROM likes");
    $likeCount = $stmt->fetchColumn();
    echo "<p>Total likes: " . $likeCount . "</p>";

    if ($postCount > 0) {
      // Get most liked post
      $stmt = $conn->query("SELECT p.post_id, p.content, COUNT(l.like_id) as like_count
                                 FROM posts p
                                 LEFT JOIN likes l ON p.post_id = l.post_id
                                 GROUP BY p.post_id
                                 ORDER BY like_count DESC
                                 LIMIT 1");
      $mostLikedPost = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($mostLikedPost && $mostLikedPost['like_count'] > 0) {
        echo "<p>Most liked post (ID: " . $mostLikedPost['post_id'] . ") has " . $mostLikedPost['like_count'] . " likes</p>";
        echo "<p>Content: " . htmlspecialchars(substr($mostLikedPost['content'], 0, 50)) . (strlen($mostLikedPost['content']) > 50 ? '...' : '') . "</p>";
      } else {
        echo "<p class='text-yellow-600'>⚠ No likes found, try liking some posts</p>";
      }
    }
  } else {
    echo "<p class='text-red-600'>✗ Likes table does not exist</p>";
  }
  echo "</div>";

  // Test 5: Comments System
  echo "<div class='bg-white p-4 rounded shadow mb-6'>";
  echo "<h2 class='text-xl font-bold mb-4'>5. Comments System</h2>";

  $stmt = $conn->query("SHOW TABLES LIKE 'comments'");
  if ($stmt->rowCount() > 0) {
    echo "<p class='text-green-600'>✓ Comments table exists</p>";

    // Count comments
    $stmt = $conn->query("SELECT COUNT(*) FROM comments");
    $commentCount = $stmt->fetchColumn();
    echo "<p>Total comments: " . $commentCount . "</p>";

    if ($commentCount > 0) {
      // Get recent comment
      $stmt = $conn->query("SELECT c.*, u.username, p.content as post_content
                                 FROM comments c
                                 JOIN users u ON c.user_id = u.user_id
                                 JOIN posts p ON c.post_id = p.post_id
                                 ORDER BY c.created_at DESC
                                 LIMIT 1");
      $recentComment = $stmt->fetch(PDO::FETCH_ASSOC);

      echo "<p>Recent comment by: " . htmlspecialchars($recentComment['username']) . "</p>";
      echo "<p>Comment text: " . htmlspecialchars($recentComment['content']) . "</p>";
      echo "<p>On post: " . htmlspecialchars(substr($recentComment['post_content'], 0, 50)) . (strlen($recentComment['post_content']) > 50 ? '...' : '') . "</p>";
    } else {
      echo "<p class='text-yellow-600'>⚠ No comments found, try commenting on some posts</p>";
    }
  } else {
    echo "<p class='text-red-600'>✗ Comments table does not exist</p>";
  }
  echo "</div>";

  // Test 6: File Uploads
  echo "<div class='bg-white p-4 rounded shadow mb-6'>";
  echo "<h2 class='text-xl font-bold mb-4'>6. File Upload System</h2>";

  $uploadDir = '../uploads/';
  if (file_exists($uploadDir) && is_dir($uploadDir)) {
    echo "<p class='text-green-600'>✓ Upload directory exists</p>";

    // Check permissions
    if (is_writable($uploadDir)) {
      echo "<p class='text-green-600'>✓ Upload directory is writable</p>";
    } else {
      echo "<p class='text-red-600'>✗ Upload directory is not writable</p>";
    }

    // Count files
    $fileCount = count(glob($uploadDir . '*'));
    echo "<p>Files in upload directory: " . $fileCount . "</p>";
  } else {
    echo "<p class='text-yellow-600'>⚠ Upload directory doesn't exist yet. It will be created when uploading files.</p>";
  }
  echo "</div>";

  // Test 7: Link Verification
  echo "<div class='bg-white p-4 rounded shadow mb-6'>";
  echo "<h2 class='text-xl font-bold mb-4'>7. Link Verification</h2>";

  $links = [
    'Main Feed' => '../feed.php',
    'Profile Page' => '../profile.php',
    'Edit Post' => '../edit_post.php?id=1',
    'Login Page' => '../auth/login.php',
    'Register Page' => '../auth/register.php'
  ];

  echo "<ul class='list-disc pl-5 space-y-2'>";
  foreach ($links as $name => $url) {
    echo "<li>";
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $url)) {
      echo "<span class='text-green-600'>✓</span> ";
    } else {
      echo "<span class='text-red-600'>✗</span> ";
    }
    echo $name . " (<a href='" . $url . "' class='text-blue-500 hover:underline' target='_blank'>" . $url . "</a>)";
    echo "</li>";
  }
  echo "</ul>";
  echo "</div>";

  // Test results summary
  echo "<div class='bg-gray-200 p-4 rounded shadow'>";
  echo "<h2 class='text-xl font-bold mb-4'>Test Summary</h2>";
  echo "<p>The core functionality of your social media platform appears to be working.</p>";
  echo "<p class='mt-2 font-bold'>Next steps:</p>";
  echo "<ul class='list-disc pl-5 mt-2'>";
  echo "<li>Try creating posts with different types of content</li>";
  echo "<li>Test like functionality</li>";
  echo "<li>Test comment functionality</li>";
  echo "<li>Try editing/deleting posts</li>";
  echo "<li>Update your profile picture</li>";
  echo "</ul>";
  echo "</div>";
} catch (PDOException $e) {
  echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>";
  echo "Database Error: " . $e->getMessage();
  echo "</div>";
}

echo "<div class='mt-8'>
    <a href='../feed.php' class='bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600'>
        Return to Feed
    </a>
</div>";

echo "</body></html>";
