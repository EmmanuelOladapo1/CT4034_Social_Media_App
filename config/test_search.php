<?php
// tests/test_search.php
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
    <title>Search Feature Test</title>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css' rel='stylesheet'>
</head>
<body class='bg-gray-100 p-8'>";

echo "<h1 class='text-2xl font-bold mb-6'>Search Feature Test</h1>";

try {
  // Check database for test data
  echo "<div class='bg-white p-4 rounded shadow mb-6'>";
  echo "<h2 class='text-xl font-bold mb-4'>1. Database Content Check</h2>";

  // Count users
  $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
  $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
  echo "<p>Users in database: $userCount</p>";

  // Count posts
  $stmt = $conn->query("SELECT COUNT(*) as count FROM posts");
  $postCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
  echo "<p>Posts in database: $postCount</p>";

  // List some sample searchable content
  $stmt = $conn->query("SELECT username FROM users LIMIT 3");
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "<p class='mt-2'>Sample usernames to search for:</p>";
  echo "<ul class='list-disc pl-5'>";
  foreach ($users as $user) {
    echo "<li>" . htmlspecialchars($user['username']) . "</li>";
  }
  echo "</ul>";

  $stmt = $conn->query("SELECT LEFT(content, 30) as snippet FROM posts LIMIT 3");
  $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "<p class='mt-2'>Sample post content to search for:</p>";
  echo "<ul class='list-disc pl-5'>";
  foreach ($posts as $post) {
    echo "<li>" . htmlspecialchars($post['snippet']) . "...</li>";
  }
  echo "</ul>";

  echo "</div>";

  // Test manual search
  echo "<div class='bg-white p-4 rounded shadow mb-6'>";
  echo "<h2 class='text-xl font-bold mb-4'>2. Manual Search Tests</h2>";

  echo "<form action='../search.php' method='GET' class='mb-4'>";
  echo "<div class='flex flex-col md:flex-row gap-2'>";
  echo "<input type='text' name='query' placeholder='Enter search term' class='flex-grow p-2 border rounded'>";
  echo "<select name='type' class='p-2 border rounded'>";
  echo "<option value='all'>All</option>";
  echo "<option value='users'>Users only</option>";
  echo "<option value='posts'>Posts only</option>";
  echo "</select>";
  echo "<button type='submit' class='bg-blue-500 text-white px-4 py-2 rounded'>Test Search</button>";
  echo "</div>";
  echo "</form>";

  echo "<p>Try searching for:</p>";
  echo "<ul class='list-disc pl-5'>";
  echo "<li>A username from the list above</li>";
  echo "<li>A word from a post snippet above</li>";
  echo "<li>A common word like 'test' or 'hello'</li>";
  echo "<li>A location name (if you've added location data to posts)</li>";
  echo "</ul>";
  echo "</div>";

  // Automated test
  echo "<div class='bg-white p-4 rounded shadow mb-6'>";
  echo "<h2 class='text-xl font-bold mb-4'>3. Automated Search Test</h2>";

  // Get a random username to search for
  $stmt = $conn->query("SELECT username FROM users ORDER BY RAND() LIMIT 1");
  $randomUser = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($randomUser) {
    $searchTerm = $randomUser['username'];
    echo "<p>Testing search for username: <strong>" . htmlspecialchars($searchTerm) . "</strong></p>";

    $stmt = $conn->prepare("SELECT * FROM users WHERE username LIKE ?");
    $stmt->execute(['%' . $searchTerm . '%']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Found " . count($results) . " results</p>";
    if (count($results) > 0) {
      echo "<p class='text-green-600'>✓ Search is working correctly</p>";
    } else {
      echo "<p class='text-red-600'>✗ Search may not be working correctly</p>";
    }
  } else {
    echo "<p>No users found to test search</p>";
  }

  echo "</div>";

  // Navigation
  echo "<div class='bg-white p-4 rounded shadow'>";
  echo "<h2 class='text-xl font-bold mb-4'>4. Navigation Links</h2>";
  echo "<p>Make sure the search box appears in the navigation bar:</p>";
  echo "<ul class='list-disc pl-5 space-y-2'>";
  echo "<li><a href='../feed.php' class='text-blue-500 hover:underline'>Check Feed Page Navigation</a></li>";
  echo "<li><a href='../search.php' class='text-blue-500 hover:underline'>Go to Search Page</a></li>";
  echo "</ul>";
  echo "</div>";
} catch (PDOException $e) {
  echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>";
  echo "Database Error: " . $e->getMessage();
  echo "</div>";
}

echo "</body></html>";
