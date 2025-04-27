<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

// Check if user is admin
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_role = $stmt->fetchColumn();
$isAdmin = ($user_role === 'admin');

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<head>
  <meta charset="UTF-8">
  <title>Feed</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body class="bg-gray-100">
  <!-- Navigation bar with centered search and profile icon on right -->
  <nav class="bg-blue-600 text-white p-4">
    <div class="container mx-auto flex items-center justify-between">
      <!-- Simple text logo only -->
      <div class="flex items-center">
        <a href="feed.php" class="text-2xl font-bold">SocialNet</a>
      </div>

      <!-- Simple Search Bar -->
      <div class="flex-grow max-w-xl mx-auto">
        <form action="feed.php" method="GET" class="flex">
          <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8') : ''; ?>"
            placeholder="Search posts..."
            class="w-full px-4 py-2 rounded-l text-gray-800 border-2 border-gray-200 focus:outline-none">
          <button type="submit" class="bg-blue-600 px-4 py-2 rounded-r text-white hover:bg-blue-700">
            Search
          </button>
        </form>
      </div>

      <!-- User Profile Icon on right -->
      <div class="relative">
        <button id="profileDropdown" class="flex items-center">
          <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-300">
            <?php if ($current_user['profile_image']): ?>
              <img src="<?php echo htmlspecialchars($current_user['profile_image']); ?>" class="w-full h-full object-cover" alt="Profile">
            <?php else: ?>
              <div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
            <?php endif; ?>
          </div>
        </button>
        <div id="profileMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
          <a href="profile.php?id=<?php echo $_SESSION['user_id']; ?>" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">My Profile</a>
          <a href="messages.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Messages</a>
          <a href="settings.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Settings</a>
          <?php if ($isAdmin): ?>
            <a href="admin_dashboard.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Admin Dashboard</a>
          <?php endif; ?>
          <a href="auth/logout.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Logout</a>
        </div>
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
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

    if (!empty($search_query)) {
      // If search query exists, filter posts
      $stmt = $conn->prepare("SELECT p.*, u.username, u.profile_image,
                        (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count
                        FROM posts p
                        JOIN users u ON p.user_id = u.user_id
                        WHERE p.content LIKE ?
                        ORDER BY p.created_at DESC");
      $stmt->execute(["%{$search_query}%"]);

      // Add a heading for search results
      echo "<div class='bg-white p-4 rounded-lg shadow mb-4'>";
      echo "<h2 class='text-xl font-bold mb-2'>Search Results for \"" . htmlspecialchars($search_query) . "\"</h2>";

      if ($stmt->rowCount() === 0) {
        echo "<p class='text-gray-500'>No posts found matching your search.</p>";
      }
      echo "</div>";
    } else {
      // No search, show all posts
      $stmt = $conn->query("SELECT p.*, u.username, u.profile_image,
                      (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count
                      FROM posts p
                      JOIN users u ON p.user_id = u.user_id
                      ORDER BY p.created_at DESC");
    }

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
      echo "<div class='mt-3'>";
      echo "<form class='comment-form flex' data-post-id='" . $post['post_id'] . "'>";
      echo "<input type='text' name='comment_content' placeholder='Write a comment...' class='w-full p-2 border rounded-l'>";
      echo "<button type='submit' class='bg-blue-500 text-white px-4 py-2 rounded-r'>Comment</button>";
      echo "</form>";
      echo "</div>";

      // Display comments with reply functionality
      echo "<div class='comments-section mt-2 ml-4 text-sm'>";
      $commentStmt = $conn->prepare("SELECT c.*, u.username FROM comments c JOIN users u ON c.user_id = u.user_id WHERE c.post_id = ? AND c.parent_id IS NULL ORDER BY c.created_at ASC");
      $commentStmt->execute([$post['post_id']]);
      while ($comment = $commentStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<div class='comment p-2 mb-1 bg-gray-50 rounded' id='comment-" . $comment['comment_id'] . "'>";
        echo "<div class='flex justify-between'>";
        echo "<div>";
        echo "<strong>" . htmlspecialchars($comment['username']) . ":</strong> ";
        echo htmlspecialchars($comment['content']);
        echo "</div>";

        echo "<div class='flex space-x-2'>";
        echo "<button class='reply-toggle text-blue-500 text-xs hover:underline' data-comment-id='" . $comment['comment_id'] . "'>Reply</button>";

        // Only show delete button if comment belongs to current user
        if ($comment['user_id'] == $_SESSION['user_id']) {
          echo "<button class='delete-comment text-red-500 text-xs hover:underline' data-comment-id='" . $comment['comment_id'] . "'>Delete</button>";
        }
        echo "</div>";
        echo "</div>";

        // Add reply form (hidden by default)
        echo "<div class='reply-form hidden mt-2 ml-4' id='reply-form-" . $comment['comment_id'] . "'>";
        echo "<form class='flex' data-parent-id='" . $comment['comment_id'] . "' data-post-id='" . $post['post_id'] . "'>";
        echo "<input type='text' name='reply_content' placeholder='Write a reply...' class='w-full p-1 text-xs border rounded-l'>";
        echo "<button type='submit' class='bg-blue-500 text-white px-2 py-1 text-xs rounded-r'>Reply</button>";
        echo "</form>";
        echo "</div>";

        // Display replies to this comment
        $replyStmt = $conn->prepare("SELECT c.*, u.username FROM comments c JOIN users u ON c.user_id = u.user_id WHERE c.parent_id = ? ORDER BY c.created_at ASC");
        $replyStmt->execute([$comment['comment_id']]);

        echo "<div class='replies ml-4 mt-1' id='replies-" . $comment['comment_id'] . "'>";
        while ($reply = $replyStmt->fetch(PDO::FETCH_ASSOC)) {
          echo "<div class='reply p-1 mb-1 bg-gray-100 rounded flex justify-between' id='comment-" . $reply['comment_id'] . "'>";
          echo "<div>";
          echo "<strong>" . htmlspecialchars($reply['username']) . ":</strong> ";
          echo htmlspecialchars($reply['content']);
          echo "</div>";

          // Only show delete button if reply belongs to current user
          if ($reply['user_id'] == $_SESSION['user_id']) {
            echo "<button class='delete-comment text-red-500 text-xs hover:underline' data-comment-id='" . $reply['comment_id'] . "'>Delete</button>";
          }

          echo "</div>";
        }
        echo "</div>"; // End replies

        echo "</div>"; // End comment
      }
      echo "</div>"; // End comments section
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
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
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
              body: 'post_id=' + postId
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                countElement.textContent = data.count;
                this.classList.toggle('text-yellow-500', data.action === 'liked');
                this.classList.toggle('text-gray-500', data.action === 'unliked');
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

          if (!content) return;

          fetch('add_comment.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: 'post_id=' + postId + '&content=' + encodeURIComponent(content)
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                const comment = data.comment;
                // Updated HTML structure to match existing comments with delete button
                const commentHTML = `
            <div class="comment p-2 mb-1 bg-gray-50 rounded flex justify-between" id="comment-${comment.comment_id}">
              <div>
                <strong>${comment.username}:</strong> ${comment.content}
              </div>
              <button class="delete-comment text-red-500 text-xs hover:underline" data-comment-id="${comment.comment_id}">Delete</button>
            </div>
          `;
                const commentsSection = this.parentElement.nextElementSibling;
                commentsSection.innerHTML += commentHTML;
                input.value = '';
              }
            });
        });
      });

      // Handle comment deletion
      document.addEventListener('click', function(e) {
        if (e.target.classList.contains('delete-comment')) {
          if (confirm('Are you sure you want to delete this comment?')) {
            const commentId = e.target.dataset.commentId;

            fetch('delete_comment.php?id=' + commentId, {
                method: 'GET'
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  // Remove the comment from the DOM
                  document.getElementById('comment-' + commentId).remove();
                } else {
                  alert('Error: ' + data.message);
                }
              })
              .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the comment');
              });
          }
        }
      });

      // Profile dropdown toggle
      const profileDropdown = document.getElementById('profileDropdown');
      const profileMenu = document.getElementById('profileMenu');

      if (profileDropdown && profileMenu) {
        profileDropdown.addEventListener('click', function() {
          profileMenu.classList.toggle('hidden');
        });

        // Close the dropdown when clicking outside
        document.addEventListener('click', function(event) {
          if (!profileDropdown.contains(event.target) && !profileMenu.contains(event.target)) {
            profileMenu.classList.add('hidden');
          }
        });
      }

      // Toggle reply forms
      document.querySelectorAll('.reply-toggle').forEach(button => {
        button.addEventListener('click', function() {
          const commentId = this.dataset.commentId;
          const replyForm = document.getElementById('reply-form-' + commentId);
          replyForm.classList.toggle('hidden');
        });
      });

      // Handle reply submissions
      document.addEventListener('submit', function(e) {
        if (e.target.dataset.parentId) {
          e.preventDefault();
          const parentId = e.target.dataset.parentId;
          const postId = e.target.dataset.postId;
          const input = e.target.querySelector('input[name="reply_content"]');
          const content = input.value.trim();

          if (!content) return;

          fetch('add_comment.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: 'post_id=' + postId + '&content=' + encodeURIComponent(content) + '&parent_id=' + parentId
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                const reply = data.comment;
                const replyHTML = `
          <div class="reply p-1 mb-1 bg-gray-100 rounded flex justify-between" id="comment-${reply.comment_id}">
            <div>
              <strong>${reply.username}:</strong> ${reply.content}
            </div>
            <button class="delete-comment text-red-500 text-xs hover:underline" data-comment-id="${reply.comment_id}">Delete</button>
          </div>
        `;
                const repliesContainer = document.getElementById('replies-' + parentId);
                repliesContainer.innerHTML += replyHTML;
                input.value = '';
                document.getElementById('reply-form-' + parentId).classList.add('hidden');
              }
            });
        }
      });
    });
  </script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('liveSearch');
      const postsContainer = document.querySelector('.container');
      const debugElement = document.getElementById('searchDebug');
      let searchTimeout;

      searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        debugElement.textContent = `Searching: "${query}"`;

        // Add a small delay to prevent too many requests
        searchTimeout = setTimeout(() => {
          fetch('feed.php?live_search=' + encodeURIComponent(query))
            .then(response => response.text())
            .then(html => {
              const tempDiv = document.createElement('div');
              tempDiv.innerHTML = html;

              // Extract the posts container from the response
              const newPosts = tempDiv.querySelector('.posts-container');
              if (newPosts) {
                document.querySelector('.posts-container').innerHTML = newPosts.innerHTML;
                debugElement.textContent = `Found: ${document.querySelectorAll('.post-item').length} posts`;
              }
            })
            .catch(error => {
              console.error('Search error:', error);
              debugElement.textContent = 'Search error';
            });
        }, 300);
      });
    });
  </script>
</body>

</html>