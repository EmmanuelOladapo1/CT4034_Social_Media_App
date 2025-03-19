<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Feed</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body class="bg-gray-100">
  <!-- Navigation bar -->
  <nav class="bg-blue-600 text-white p-4">
    <div class="container mx-auto flex justify-between items-center">
      <a href="feed.php" class="text-2xl font-bold">SocialNet</a>

      <div class="flex items-center space-x-4">
        <a href="profile.php" class="flex items-center space-x-2">
          <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-300">
            <?php if ($current_user['profile_image']): ?>
              <img src="<?php echo htmlspecialchars($current_user['profile_image']); ?>" class="w-full h-full object-cover" alt="Profile">
            <?php else: ?>
              <div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
            <?php endif; ?>
          </div>
          <span><?php echo htmlspecialchars($current_user['username']); ?></span>
        </a>
        <a href="auth/logout.php" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container mx-auto p-4 mt-4">
    <!-- Post Form -->
    <div class="bg-white p-4 rounded-lg shadow mb-4">
      <form action="process_post.php" method="POST" enctype="multipart/form-data" class="space-y-4">
        <textarea name="content" class="w-full p-2 border rounded" placeholder="What's on your mind?"></textarea>

        <div class="flex items-center gap-4">
          <input type="file" name="image" accept="image/*" class="border p-1">
          <button type="button" onclick="getLocation()" class="bg-gray-500 text-white px-4 py-2 rounded">
            Add Location
          </button>
          <span id="locationStatus" class="text-sm text-gray-600"></span>
          <input type="hidden" name="latitude" id="latitude">
          <input type="hidden" name="longitude" id="longitude">
          <input type="hidden" name="location_name" id="location_name">
        </div>

        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Post</button>
      </form>
    </div>

    <!-- Display Posts -->
    <?php
    // Get posts with like counts
    $stmt = $conn->query("SELECT p.*, u.username, u.profile_image,
                          (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count
                          FROM posts p
                          JOIN users u ON p.user_id = u.user_id
                          ORDER BY p.created_at DESC");

    while ($post = $stmt->fetch(PDO::FETCH_ASSOC)) {
      echo "<div class='bg-white p-4 rounded-lg shadow mb-4'>";

      // Post header with username and timestamp
      echo "<div class='flex justify-between items-start mb-3'>";
      echo "<div class='flex items-center'>";

      // User profile picture
      echo "<div class='w-10 h-10 rounded-full overflow-hidden bg-gray-300 mr-3'>";
      if ($post['profile_image']) {
        echo "<img src='" . htmlspecialchars($post['profile_image']) . "' class='w-full h-full object-cover' alt='Profile'>";
      } else {
        echo "<div class='w-full h-full flex items-center justify-center text-gray-500 bg-white'>";
        echo "<svg xmlns='http://www.w3.org/2000/svg' class='h-6 w-6' fill='none' viewBox='0 0 24 24' stroke='currentColor'>";
        echo "<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' />";
        echo "</svg>";
        echo "</div>";
      }
      echo "</div>";

      echo "<p class='font-bold'>" . htmlspecialchars($post['username']) . "</p>";
      echo "</div>";

      // Only show edit/delete for post owner
      if ($post['user_id'] == $_SESSION['user_id']) {
        echo "<div class='flex space-x-2'>";
        echo "<a href='edit_post.php?id=" . $post['post_id'] . "' class='text-blue-500 hover:underline'>Edit</a>";
        echo "<a href='delete_post.php?id=" . $post['post_id'] . "' class='text-red-500 hover:underline' onclick='return confirm(\"Are you sure you want to delete this post?\")'>Delete</a>";
        echo "</div>";
      }
      echo "</div>";

      // Post content
      echo "<p class='mb-2'>" . htmlspecialchars($post['content']) . "</p>";

      // Post image
      if ($post['image_url']) {
        echo "<img src='" . htmlspecialchars($post['image_url']) . "' class='max-w-md mb-2'>";
      }

      // Post location
      if ($post['latitude'] && $post['longitude']) {
        echo "<div id='map-" . $post['post_id'] . "' class='h-32 w-full mb-2'></div>"; // Reduced height
        echo "<p class='text-sm text-gray-600'>Posted from: <span id='location-name-" . $post['post_id'] . "'>";
        echo $post['location_name'] ? htmlspecialchars($post['location_name']) : "Loading location...";
        echo "</span></p>";
        echo "<script>
            const map" . $post['post_id'] . " = L.map('map-" . $post['post_id'] . "').setView([" . $post['latitude'] . ", " . $post['longitude'] . "], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map" . $post['post_id'] . ");
            L.marker([" . $post['latitude'] . ", " . $post['longitude'] . "]).addTo(map" . $post['post_id'] . ");";

        // Only fetch location name if it's not already in the database
        if (!$post['location_name']) {
            echo "
            // Fetch location name
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=" . $post['latitude'] . "&lon=" . $post['longitude'] . "&zoom=18&addressdetails=1`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('location-name-" . $post['post_id'] . "').textContent = data.display_name || 'Unknown location';
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('location-name-" . $post['post_id'] . "').textContent = 'Unknown location';
                });";
        }

        echo "</script>";
      }

      echo "<p class='text-sm text-gray-500'>" . $post['created_at'] . "</p>";

      // Like button with star icon
      $likeStmt = $conn->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ? AND user_id = ?");
      $likeStmt->execute([$post['post_id'], $_SESSION['user_id']]);
      $liked = ($likeStmt->fetchColumn() > 0);

      echo "<div class='flex items-center mt-2 mb-2'>";
      echo "<button class='like-button " . ($liked ? 'text-yellow-500' : 'text-gray-500') . " hover:text-yellow-500' data-post-id='" . $post['post_id'] . "'>";
      // Star icon
      echo "<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 inline' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z' /></svg>";
      echo " <span class='like-count'>" . ($post['like_count'] ?? 0) . "</span>";
      echo "</button>";
      echo "</div>";

      // Comment form
      echo "<div class='