<?php
session_start();
require_once 'config/database.php';

// Get search query
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

// Start building output
$output = '<div class="p-4 mt-4">';

if (empty($query)) {
  // If no query, fetch all posts (normal feed)
  $stmt = $conn->query("SELECT p.*, u.username, u.profile_image,
                      (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count
                      FROM posts p
                      JOIN users u ON p.user_id = u.user_id
                      ORDER BY p.created_at DESC");
} else {
  // Search for posts matching query
  $stmt = $conn->prepare("SELECT p.*, u.username, u.profile_image,
                        (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count
                        FROM posts p
                        JOIN users u ON p.user_id = u.user_id
                        WHERE p.content LIKE ?
                        ORDER BY p.created_at DESC");
  $stmt->execute(["%{$query}%"]);
}

// Output debugging info
$output .= '<div style="font-size:10px;color:gray;margin-bottom:10px;">Search query: "' . htmlspecialchars($query) . '"</div>';

// Build post HTML
while ($post = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $output .= '<div class="bg-white p-4 rounded-lg shadow mb-4">';
  // Add existing post HTML here
  $output .= '</div>';
}

if ($stmt->rowCount() === 0) {
  $output .= '<div class="bg-white p-4 rounded-lg shadow text-center">No posts found matching "' . htmlspecialchars($query) . '"</div>';
}

$output .= '</div>';
echo $output;
