<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}
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
  <div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
      <a href="auth/logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Logout</a>
    </div>

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
        </div>

        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Post</button>
      </form>
    </div>

    <!-- Display Posts -->
    <?php
    // Get posts with like counts
    $stmt = $conn->query("SELECT p.*, u.username,
                          (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count
                          FROM posts p
                          JOIN users u ON p.user_id = u.user_id
                          ORDER BY p.created_at DESC");

    while ($post = $stmt->fetch(PDO::FETCH_ASSOC)) {
      echo "<div class='bg-white p-4 rounded-lg shadow mb-4'>";
      echo "<p class='font-bold'>" . htmlspecialchars($post['username']) . "</p>";
      echo "<p class='mb-2'>" . htmlspecialchars($post['content']) . "</p>";

      if ($post['image_url']) {
        echo "<img src='" . htmlspecialchars($post['image_url']) . "' class='max-w-md mb-2'>";
      }

      if ($post['latitude'] && $post['longitude']) {
        echo "<div id='map-" . $post['post_id'] . "' class='h-48 w-full mb-2'></div>";
        echo "<p class='text-sm text-gray-600'>Posted from: <span id='location-name-" . $post['post_id'] . "'>Loading location...</span></p>";
        echo "<script>
            const map" . $post['post_id'] . " = L.map('map-" . $post['post_id'] . "').setView([" . $post['latitude'] . ", " . $post['longitude'] . "], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map" . $post['post_id'] . ");
            L.marker([" . $post['latitude'] . ", " . $post['longitude'] . "]).addTo(map" . $post['post_id'] . ");

            // Fetch location name
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=" . $post['latitude'] . "&lon=" . $post['longitude'] . "&zoom=18&addressdetails=1`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('location-name-" . $post['post_id'] . "').textContent = data.display_name || 'Unknown location';
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('location-name-" . $post['post_id'] . "').textContent = 'Unknown location';
                });
        </script>";
      }

      echo "<p class='text-sm text-gray-500'>" . $post['created_at'] . "</p>";

      // Like button
      $likeStmt = $conn->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ? AND user_id = ?");
      $likeStmt->execute([$post['post_id'], $_SESSION['user_id']]);
      $liked = ($likeStmt->fetchColumn() > 0);

      echo "<div class='flex items-center mt-2 mb-2'>";
      echo "<button class='like-button " . ($liked ? 'text-blue-500' : 'text-gray-500') . " hover:text-blue-500' data-post-id='" . $post['post_id'] . "'>";
      echo "<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 inline' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905V10m5 0v2a2 2 0 01-2 2h-2.5' /></svg>";
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

      // Display comments
      echo "<div class='comments-section mt-2 ml-4 text-sm'>";
      $commentStmt = $conn->prepare("SELECT c.*, u.username FROM comments c JOIN users u ON c.user_id = u.user_id WHERE c.post_id = ? ORDER BY c.created_at ASC");
      $commentStmt->execute([$post['post_id']]);
      while ($comment = $commentStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<div class='comment p-2 mb-1 bg-gray-50 rounded'>";
        echo "<strong>" . htmlspecialchars($comment['username']) . ":</strong> ";
        echo htmlspecialchars($comment['content']);
        echo "</div>";
      }
      echo "</div>";

      echo "</div>"; // Close post div
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
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
            status.textContent = "Location added ✓";
            status.style.color = "green";
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
              body: 'post_id=' + postId
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                countElement.textContent = data.count;
                this.classList.toggle('text-blue-500', data.action === 'liked');
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
                const commentHTML = `
                            <div class="comment p-2 mb-1 bg-gray-50 rounded">
                                <strong>${comment.username}:</strong> ${comment.content}
                            </div>
                        `;
                const commentsSection = this.parentElement.nextElementSibling;
                commentsSection.innerHTML += commentHTML;
                input.value = '';
              }
            });
        });
      });
    });
  </script>
</body>

</html>