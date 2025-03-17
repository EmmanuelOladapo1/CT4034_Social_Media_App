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
    <h1 class="text-2xl mb-4">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>

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
    $stmt = $conn->query("SELECT posts.*, users.username FROM posts
                     JOIN users ON posts.user_id = users.user_id
                     ORDER BY posts.created_at DESC");
    while ($post = $stmt->fetch(PDO::FETCH_ASSOC)) {
      echo "<div class='bg-white p-4 rounded-lg shadow mb-4'>";
      echo "<p class='font-bold'>" . htmlspecialchars($post['username']) . "</p>";
      echo "<p class='mb-2'>" . htmlspecialchars($post['content']) . "</p>";

      if ($post['image_url']) {
        echo "<img src='" . htmlspecialchars($post['image_url']) . "' class='max-w-md mb-2'>";
      }

      if ($post['latitude'] && $post['longitude']) {
        echo "<div id='map-" . $post['post_id'] . "' class='h-48 w-full mb-2'></div>";
        echo "<script>
        const map" . $post['post_id'] . " = L.map('map-" . $post['post_id'] . "').setView([" . $post['latitude'] . ", " . $post['longitude'] . "], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map" . $post['post_id'] . ");
        L.marker([" . $post['latitude'] . ", " . $post['longitude'] . "]).addTo(map" . $post['post_id'] . ");
    </script>";
      }

      echo "<p class='text-sm text-gray-500'>" . $post['created_at'] . "</p>";
      echo "</div>";
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
  </script>
</body>

</html>