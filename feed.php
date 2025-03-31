<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

try {
  // Get current user data
  $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
  $stmt->execute([$_SESSION['user_id']]);
  $current_user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$current_user) {
    throw new Exception("User not found");
  }
} catch (PDOException $e) {
  error_log("Database error: " . $e->getMessage());
  die("An error occurred. Please try again later.");
} catch (Exception $e) {
  error_log("Error: " . $e->getMessage());
  die($e->getMessage());
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Feed</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    /* Additional styles for very small text */
    .text-xxs {
      font-size: 0.65rem;
      line-height: 0.85rem;
    }
  </style>
</head>

<body class="bg-gray-100">
  <!-- Navigation bar -->
  <nav class="bg-blue-600 text-white p-4">
    <div class="container mx-auto flex flex-col md:flex-row md:items-center md:justify-between">
      <div class="flex items-center justify-between mb-2 md:mb-0">
        <a href="feed.php" class="text-2xl font-bold">SocialNet</a>
      </div>

      <!-- Search Box -->
      <div class="w-full md:w-1/3 mb-2 md:mb-0">
        <form action="search.php" method="GET" class="flex">
          <input type="text" name="query" placeholder="Search..." class="w-full px-3 py-1 text-gray-700 rounded-l">
          <button type="submit" class="bg-blue-700 px-4 py-1 rounded-r hover:bg-blue-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
      </div>` : ''}
    </div>
    </div>
    </div>
    </div>`;
    </div>`}
    </div>
    </div>
    </div>
    </div>`;
    </button>
    </form>
    </div>

    <div class="flex items-center space-x-4">
      <a href="profile.php" class="flex items-center space-x-2">
        <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-300">
          <?php if (!empty($current_user['profile_image'])): ?>
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
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <textarea name="content" class="w-full p-2 border rounded" placeholder="What's on your mind?" required></textarea>

        <div class="flex flex-wrap items-center gap-4">
          <div>
            <input type="file" name="image" accept="image/*" class="border p-1">
            <p class="text-xs text-gray-500">Max 2MB (JPEG, PNG)</p>
          </div>

          <button type="button" onclick="getLocation()" class="bg-gray-500 text-white px-4 py-2 rounded">
            Add Location
          </button>
          <span id="locationStatus" class="text-sm text-gray-600"></span>
          <input type="hidden" name="latitude" id="latitude">
          <input type="hidden" name="longitude" id="longitude">
          <input type="hidden" name="location_name" id="location_name">
        </div>

        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Post</button>
      </form>
    </div>
    <!-- Display Posts -->
    <?php
    try {
      // Get posts with like counts and pagination
      $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
      $limit = 10;
      $offset = ($page - 1) * $limit;

      $stmt = $conn->prepare("SELECT p.*, u.username, u.profile_image,
                                (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count
                                FROM posts p
                                JOIN users u ON p.user_id = u.user_id
                                ORDER BY p.created_at DESC
                                LIMIT :limit OFFSET :offset");
      $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
      $stmt->execute();

      $post_count = $stmt->rowCount();

      if ($post_count === 0) {
        echo "<p class='text-center text-gray-500'>No posts found. Be the first to post!</p>";
      }

      while ($post = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<div class='bg-white p-4 rounded-lg shadow mb-4'>";

        // Post header with username and timestamp
        echo "<div class='flex justify-between items-start mb-3'>";
        echo "<div class='flex items-center'>";

        // User profile picture
        echo "<div class='w-10 h-10 rounded-full overflow-hidden bg-gray-300 mr-3'>";
        if (!empty($post['profile_image'])) {
          echo "<img src='" . htmlspecialchars($post['profile_image']) . "' class='w-full h-full object-cover' alt='Profile'>";
        } else {
          echo "<div class='w-full h-full flex items-center justify-center text-gray-500 bg-white'>";
          echo "<svg xmlns='http://www.w3.org/2000/svg' class='h-6 w-6' fill='none' viewBox='0 0 24 24' stroke='currentColor'>";
          echo "<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' />";
          echo "</svg>";
          echo "</div>";
        }
        echo "</div>";

        echo "<div>";
        echo "<p class='font-bold'>" . htmlspecialchars($post['username']) . "</p>";
        echo "<p class='text-xs text-gray-500'>" . date('M j, Y g:i a', strtotime($post['created_at'])) . "</p>";
        echo "</div>";
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
        echo "<p class='mb-2 whitespace-pre-wrap'>" . nl2br(htmlspecialchars($post['content'])) . "</p>";

        // Post image
        if (!empty($post['image_url'])) {
          echo "<div class='mb-2 flex justify-center'>";
          echo "<img src='" . htmlspecialchars($post['image_url']) . "' class='max-w-full h-auto max-h-96 rounded' alt='Post image'>";
          echo "</div>";
        }

        // Post location
        if (!empty($post['latitude']) && !empty($post['longitude'])) {
          echo "<div id='map-" . $post['post_id'] . "' class='h-48 w-full mb-2 rounded border'></div>";
          echo "<p class='text-sm text-gray-600'>Posted from: <span id='location-name-" . $post['post_id'] . "'>";
          echo !empty($post['location_name']) ? htmlspecialchars($post['location_name']) : "Loading location...";
          echo "</span></p>";

          echo "<script>
                        const map" . $post['post_id'] . " = L.map('map-" . $post['post_id'] . "').setView([" . $post['latitude'] . ", " . $post['longitude'] . "], 13);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap contributors'
                        }).addTo(map" . $post['post_id'] . ");
                        L.marker([" . $post['latitude'] . ", " . $post['longitude'] . "]).addTo(map" . $post['post_id'] . ");";

          // Only fetch location name if it's not already in the database
          if (empty($post['location_name'])) {
            echo "
                        // Fetch location name
                        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=" . $post['latitude'] . "&lon=" . $post['longitude'] . "&zoom=18&addressdetails=1`, {
                            headers: {
                                'User-Agent': 'SocialNet/1.0 (contact@example.com)'
                            }
                        })
                            .then(response => response.json())
                            .then(data => {
                                const locationElement = document.getElementById('location-name-" . $post['post_id'] . "');
                                if (data.display_name) {
                                    locationElement.textContent = data.display_name;
                                    // Update database with location name
                                    fetch('update_location_name.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: 'post_id=" . $post['post_id'] . "&location_name=' + encodeURIComponent(data.display_name)
                                    });
                                } else {
                                    locationElement.textContent = 'Unknown location';
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                document.getElementById('location-name-" . $post['post_id'] . "').textContent = 'Unknown location';
                            });";
          }
          echo "</script>";
        }

        // Like button with star icon
        $likeStmt = $conn->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ? AND user_id = ?");
        $likeStmt->execute([$post['post_id'], $_SESSION['user_id']]);
        $liked = ($likeStmt->fetchColumn() > 0);

        echo "<div class='flex items-center mt-3 mb-2'>";
        echo "<button class='like-button flex items-center " . ($liked ? 'text-yellow-500' : 'text-gray-500') . " hover:text-yellow-500' data-post-id='" . $post['post_id'] . "'>";
        // Star icon
        echo "<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 mr-1' fill='" . ($liked ? 'currentColor' : 'none') . "' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z' /></svg>";
        echo "<span class='like-count'>" . ($post['like_count'] ?? 0) . "</span>";
        echo "</button>";
        echo "</div>";

        // Comment form
        echo "<div class='mt-3'>";
        echo "<form class='comment-form flex' data-post-id='" . $post['post_id'] . "'>";
        echo "<input type='hidden' name='csrf_token' value='" . $_SESSION['csrf_token'] . "'>";
        echo "<input type='text' name='comment_content' placeholder='Write a comment...' class='flex-1 p-2 border rounded-l' required>";
        echo "<button type='submit' class='bg-blue-500 text-white px-4 py-2 rounded-r hover:bg-blue-600'>Comment</button>";
        echo "</form>";
        echo "</div>";
        // Display comments
        echo "<div class='comments-section mt-2 ml-4 text-sm'>";
        $commentStmt = $conn->prepare("SELECT c.*, u.username, u.profile_image FROM comments c JOIN users u ON c.user_id = u.user_id WHERE c.post_id = ? ORDER BY c.created_at ASC");
        $commentStmt->execute([$post['post_id']]);

        while ($comment = $commentStmt->fetch(PDO::FETCH_ASSOC)) {
          // Fixed comment structure with reply functionality
          echo "<div class='comment p-2 mb-1 bg-gray-50 rounded flex flex-col' id='comment-" . $comment['comment_id'] . "'>";
          echo "<div class='flex justify-between items-start w-full'>";
          echo "<div class='flex items-start'>";

          // Commenter's profile picture
          echo "<div class='w-6 h-6 rounded-full overflow-hidden bg-gray-300 mr-2 mt-1 flex-shrink-0'>";
          if (!empty($comment['profile_image'])) {
            echo "<img src='" . htmlspecialchars($comment['profile_image']) . "' class='w-full h-full object-cover' alt='Profile'>";
          } else {
            echo "<div class='w-full h-full flex items-center justify-center text-gray-500 bg-white'>";
            echo "<svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4' fill='none' viewBox='0 0 24 24' stroke='currentColor'>";
            echo "<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' />";
            echo "</svg>";
            echo "</div>";
          }
          echo "</div>";

          echo "<div>";
          echo "<p class='font-semibold'>" . htmlspecialchars($comment['username']) . "</p>";
          echo "<p class='whitespace-pre-wrap'>" . htmlspecialchars($comment['content']) . "</p>";
          echo "<p class='text-xs text-gray-500 mt-1'>" . date('M j, Y g:i a', strtotime($comment['created_at'])) . "</p>";
          echo "</div>";
          echo "</div>";

          // Action buttons
          echo "<div class='flex space-x-2'>";
          echo "<button class='reply-toggle text-blue-500 text-xs hover:underline' data-comment-id='" . $comment['comment_id'] . "'>Reply</button>";
          // Delete button (if owner)
          if ($comment['user_id'] == $_SESSION['user_id']) {
            echo "<button class='delete-comment text-red-500 text-xs hover:underline' data-comment-id='" . $comment['comment_id'] . "'>Delete</button>";
          }
          echo "</div>";
          echo "</div>";

          // Reply form (hidden by default)
          echo "<div class='reply-form-container mt-2 ml-6 hidden' id='reply-form-" . $comment['comment_id'] . "'>";
          echo "<form class='reply-form flex' data-comment-id='" . $comment['comment_id'] . "'>";
          echo "<input type='hidden' name='csrf_token' value='" . $_SESSION['csrf_token'] . "'>";
          echo "<input type='text' name='reply_content' placeholder='Write a reply...' class='flex-1 p-1 text-sm border rounded-l'>";
          echo "<button type='submit' class='bg-blue-500 text-white px-2 py-1 text-sm rounded-r'>Reply</button>";
          echo "</form>";
          echo "</div>";

          // Replies container
          echo "<div class='replies-container ml-6 mt-1' id='replies-" . $comment['comment_id'] . "'>";

          // Fetch and display existing replies
          $replyStmt = $conn->prepare("SELECT r.*, u.username, u.profile_image FROM comment_replies r JOIN users u ON r.user_id = u.user_id WHERE r.comment_id = ? ORDER BY r.created_at ASC");
          $replyStmt->execute([$comment['comment_id']]);

          while ($reply = $replyStmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<div class='reply p-1 bg-gray-100 rounded mb-1 flex justify-between items-start' id='reply-" . $reply['reply_id'] . "'>";
            echo "<div class='flex items-start'>";

            // Reply user profile pic
            echo "<div class='w-4 h-4 rounded-full overflow-hidden bg-gray-300 mr-1 mt-1 flex-shrink-0'>";
            if (!empty($reply['profile_image'])) {
              echo "<img src='" . htmlspecialchars($reply['profile_image']) . "' class='w-full h-full object-cover' alt='Profile'>";
            } else {
              echo "<div class='w-full h-full flex items-center justify-center text-gray-500 bg-white'>";
              echo "<svg xmlns='http://www.w3.org/2000/svg' class='h-2 w-2' fill='none' viewBox='0 0 24 24' stroke='currentColor'>";
              echo "<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' />";
              echo "</svg>";
              echo "</div>";
            }
            echo "</div>";

            echo "<div class='text-xs'>";
            echo "<span class='font-semibold'>" . htmlspecialchars($reply['username']) . "</span> ";
            echo "<span>" . htmlspecialchars($reply['content']) . "</span>";
            echo "<p class='text-xxs text-gray-500'>" . date('M j, Y g:i a', strtotime($reply['created_at'])) . "</p>";
            echo "</div>";
            echo "</div>";

            // Delete reply button if owner
            if ($reply['user_id'] == $_SESSION['user_id']) {
              echo "<button class='delete-reply text-red-500 text-xxs hover:underline' data-reply-id='" . $reply['reply_id'] . "'>Delete</button>";
            }
            echo "</div>";
          }

          echo "</div>"; // End replies container
          echo "</div>"; // End comment div
        }

        echo "</div>"; // Close comments section
        echo "</div>"; // Close post div
      }

      // Pagination
      $stmt = $conn->query("SELECT COUNT(*) FROM posts");
      $total_posts = $stmt->fetchColumn();
      $total_pages = ceil($total_posts / $limit);

      if ($total_pages > 1) {
        echo "<div class='flex justify-center mt-4'>";
        echo "<div class='flex space-x-2'>";
        for ($i = 1; $i <= $total_pages; $i++) {
          echo "<a href='?page=$i' class='px-3 py-1 " . ($page == $i ? 'bg-blue-500 text-white' : 'bg-white text-blue-500') . " rounded'>";
          echo $i;
          echo "</a>";
        }
        echo "</div>";
        echo "</div>";
      }
    } catch (PDOException $e) {
      error_log("Database error: " . $e->getMessage());
      echo "<p class='text-red-500 text-center'>Error loading posts. Please try again later.</p>";
    }
    ?>
  </div>
  <script>
    function getLocation() {
      const status = document.getElementById('locationStatus');
      status.textContent = "Getting location...";

      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;

            // Get location name using reverse geocoding
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`, {
                headers: {
                  'User-Agent': 'SocialNet/1.0 (contact@example.com)'
                }
              })
              .then(response => response.json())
              .then(data => {
                const locationName = data.display_name || 'Unknown location';
                document.getElementById('location_name').value = locationName;
                status.textContent = "Location added: " + locationName;
                status.style.color = "green";
              })
              .catch(error => {
                console.error('Error:', error);
                status.textContent = "Location added ✓";
                status.style.color = "green";
              });
          },
          function(error) {
            status.textContent = "Error getting location: " + error.message;
            status.style.color = "red";
          }
        );
      } else {
        status.textContent = "Geolocation is not supported by this browser.";
        status.style.color = "red";
      }
    }

    // Handle likes and comments
    document.addEventListener('DOMContentLoaded', function() {
          // Handle likes
          document.querySelectorAll('.like-button').forEach(button => {
            button.addEventListener('click', function() {
              const postId = this.dataset.postId;
              const countElement = this.querySelector('.like-count');

              fetch('like_post.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                  },
                  body: 'post_id=' + postId + '&csrf_token=' + encodeURIComponent('<?php echo $_SESSION["csrf_token"]; ?>')
                })
                .then(response => response.json())
                .then(data => {
                  if (data.success) {
                    countElement.textContent = data.count;
                    this.classList.toggle('text-yellow-500', data.action === 'liked');
                    this.classList.toggle('text-gray-500', data.action === 'unliked');

                    // Update SVG fill
                    const svg = this.querySelector('svg');
                    if (svg) {
                      svg.setAttribute('fill', data.action === 'liked' ? 'currentColor' : 'none');
                    }
                  }
                });
            });
          });

          // Handle comments
          document.querySelectorAll('.comment-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                      e.preventDefault();
                      const postId = this.dataset.postId;
                      const input = this.querySelector('input[name="comment_content"]');
                      const content = input.value.trim();
                      const csrf = this.querySelector('input[name="csrf_token"]').value;

                      if (!content) return;

                      fetch('add_comment.php', {
                          method: 'POST',
                          headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                          },
                          body: 'post_id=' + postId + '&content=' + encodeURIComponent(content) + '&csrf_token=' + encodeURIComponent(csrf)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                              const comment = data.comment;

                              // Create new comment HTML
                              const commentHTML = `
                  <div class="comment p-2 mb-1 bg-gray-50 rounded flex flex-col" id="comment-${comment.comment_id}">
                    <div class="flex justify-between items-start w-full">
                      <div class="flex items-start">
                        <div class="w-6 h-6 rounded-full overflow-hidden bg-gray-300 mr-2 mt-1 flex-shrink-0">
                          ${comment.profile_image ?
                            `<img src="${comment.profile_image}" class="w-full h-full object-cover" alt="Profile">` :
                            `<div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                              </svg>