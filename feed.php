// Purpose: Display the feed of posts and allow users to create new posts
<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Feed - Social Media</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
  <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY"></script>
</head>

<body class="bg-gray-100">
  <div class="container mx-auto px-4 py-8">
    <!-- Create Post Form -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
      <form action="process_post.php" method="POST" enctype="multipart/form-data">
        <textarea name="content" class="w-full p-2 border rounded mb-4" placeholder="What's on your mind?"></textarea>

        <div class="flex items-center gap-4">
          <input type="file" name="image" accept="image/*">
          <button type="button" onclick="getLocation()" class="px-4 py-2 bg-gray-500 text-white rounded">Add Location</button>
          <input type="hidden" name="latitude" id="latitude">
          <input type="hidden" name="longitude" id="longitude">
        </div>

        <button type="submit" class="mt-4 px-6 py-2 bg-blue-500 text-white rounded">Post</button>
      </form>
    </div>

    <!-- Posts Feed -->
    <?php
    try {
      $stmt = $conn->query("SELECT posts.*, users.username
                                 FROM posts
                                 JOIN users ON posts.user_id = users.user_id
                                 ORDER BY posts.created_at DESC");
      while ($post = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<div class='bg-white rounded-lg shadow-md p-6 mb-4'>";
        echo "<div class='flex items-center mb-4'>";
        echo "<span class='font-bold'>" . htmlspecialchars($post['username']) . "</span>";
        echo "<span class='text-gray-500 ml-2'>" . $post['created_at'] . "</span>";
        echo "</div>";
        echo "<p class='mb-4'>" . htmlspecialchars($post['content']) . "</p>";
        if ($post['image_url']) {
          echo "<img src='" . htmlspecialchars($post['image_url']) . "' class='max-w-full mb-4'>";
        }
        if ($post['latitude'] && $post['longitude']) {
          echo "<div id='map-" . $post['post_id'] . "' class='h-48 mb-4'></div>";
        }
        echo "</div>";
      }
    } catch (PDOException $e) {
      echo "<p class='text-red-500'>Error loading posts: " . $e->getMessage() . "</p>";
    }
    ?>
  </div>

  <script>
    let map;

    function getLocation() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(showPosition);
      } else {
        alert("Geolocation not supported");
      }
    }

    function showPosition(position) {
      document.getElementById('latitude').value = position.coords.latitude;
      document.getElementById('longitude').value = position.coords.longitude;
      alert("Location added!");
      initMap(position.coords.latitude, position.coords.longitude);
    }
  </script>
</body>

</html>