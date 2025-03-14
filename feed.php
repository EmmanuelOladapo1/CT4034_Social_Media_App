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
  <!-- Google Maps API with your key -->
  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBH49WG8ok6dWR_0PL8qa4q7c1FhBte71I"></script>
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
          <input type="hidden" name="location_name" id="location_name">
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

      if ($post['location_name']) {
        echo "<p class='text-sm text-gray-600'>Posted from: " . htmlspecialchars($post['location_name']) . "</p>";
      } else if ($post['latitude'] && $post['longitude']) {
        echo "<p class='text-sm text-gray-600'>Posted from: " .
          round($post['latitude'], 4) . ", " . round($post['longitude'], 4) . "</p>";
      }

      echo "<p class='text-sm text-gray-500'>" . $post['created_at'] . "</p>";
      echo "</div>";
    }
    ?>
  </div>

  <script>
    let geocoder;

    function initGeocoder() {
      geocoder = new google.maps.Geocoder();
    }

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
            geocoder.geocode({
              location: {
                lat: lat,
                lng: lng
              }
            }, (results, status) => {
              if (status === "OK" && results[0]) {
                const locationName = results[0].formatted_address;
                document.getElementById('location_name').value = locationName;
                status.textContent = "Location added: " + locationName;
              } else {
                status.textContent = "Location added ✓";
              }
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

    window.onload = function() {
      initGeocoder();
    }
  </script>
</body>

</html>