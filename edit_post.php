<?php
// edit_post.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

// Handle GET request - display form
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
  $post_id = $_GET['id'];

  // Get the post
  $stmt = $conn->prepare("SELECT * FROM posts WHERE post_id = ? AND user_id = ?");
  $stmt->execute([$post_id, $_SESSION['user_id']]);
  $post = $stmt->fetch(PDO::FETCH_ASSOC);

  // If post doesn't exist or doesn't belong to user
  if (!$post) {
    header("Location: feed.php");
    exit();
  }
}

// Handle POST request - update post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'])) {
  $post_id = $_POST['post_id'];
  $content = trim($_POST['content']);
  $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
  $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
  $location_name = !empty($_POST['location_name']) ? $_POST['location_name'] : null;

  // Check post ownership
  $stmt = $conn->prepare("SELECT * FROM posts WHERE post_id = ?");
  $stmt->execute([$post_id]);
  $post = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$post || $post['user_id'] != $_SESSION['user_id']) {
    header("Location: feed.php");
    exit();
  }

  // Handle image update
  $image_url = $post['image_url']; // Default to current image

  if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
      mkdir($target_dir, 0777, true);
    }

    $target_file = $target_dir . time() . '_' . basename($_FILES["image"]["name"]);
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
      $image_url = $target_file;
    }
  }

  try {
    $stmt = $conn->prepare("UPDATE posts SET content = ?, image_url = ?, latitude = ?, longitude = ?, location_name = ? WHERE post_id = ?");
    $stmt->execute([$content, $image_url, $latitude, $longitude, $location_name, $post_id]);
    header("Location: feed.php");
    exit();
  } catch (PDOException $e) {
    die("Error updating post: " . $e->getMessage());
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Edit Post</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body class="bg-gray-100">
  <div class="container mx-auto p-4">
    <h1 class="text-2xl mb-4">Edit Post</h1>

    <div class="bg-white p-4 rounded-lg shadow mb-4">
      <form action="edit_post.php" method="POST" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['post_id']); ?>">
        <textarea name="content" class="w-full p-2 border rounded" rows="4"><?php echo htmlspecialchars($post['content']); ?></textarea>

        <!-- Image upload -->
        <div class="mb-4">
          <label class="block text-sm font-medium mb-2">Update Image</label>
          <?php if ($post['image_url']): ?>
            <div class="mb-2">
              <img src="<?php echo htmlspecialchars($post['image_url']); ?>" class="max-w-xs mb-2" alt="Current post image">
              <p class="text-sm text-gray-500">Current image</p>
            </div>
          <?php endif; ?>
          <input type="file" name="image" accept="image/*" class="border p-1">
        </div>

        <!-- Location edit -->
        <div class="mb-4">
          <label class="block text-sm font-medium mb-2">Update Location</label>
          <div class="flex items-center gap-4">
            <button type="button" onclick="getLocation()" class="bg-gray-500 text-white px-4 py-2 rounded">
              Update Location
            </button>
            <span id="locationStatus" class="text-sm text-gray-600">
              <?php if ($post['location_name']): ?>
                Current location: <?php echo htmlspecialchars($post['location_name']); ?>
              <?php endif; ?>
            </span>
            <input type="hidden" name="latitude" id="latitude" value="<?php echo htmlspecialchars($post['latitude'] ?? ''); ?>">
            <input type="hidden" name="longitude" id="longitude" value="<?php echo htmlspecialchars($post['longitude'] ?? ''); ?>">
            <input type="hidden" name="location_name" id="location_name" value="<?php echo htmlspecialchars($post['location_name'] ?? ''); ?>">
          </div>

          <?php if ($post['latitude'] && $post['longitude']): ?>
            <div id="map" class="h-32 w-full mt-2"></div>
            <script>
              document.addEventListener('DOMContentLoaded', function() {
                const map = L.map('map').setView([<?php echo $post['latitude']; ?>, <?php echo $post['longitude']; ?>], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                  attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);
                L.marker([<?php echo $post['latitude']; ?>, <?php echo $post['longitude']; ?>]).addTo(map);
              });
            </script>
          <?php endif; ?>
        </div>

        <div class="flex justify-between">
          <a href="feed.php" class="bg-gray-500 text-white px-4 py-2 rounded">Cancel</a>
          <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Save Changes</button>
        </div>
      </form>
    </div>
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
                status.textContent = "Location updated: " + locationName;
                status.style.color = "green";

                // Update map if it exists
                if (window.map) {
                  window.map.setView([lat, lng], 13);
                  // Remove old markers
                  window.map.eachLayer(function(layer) {
                    if (layer instanceof L.Marker) {
                      window.map.removeLayer(layer);
                    }
                  });
                  // Add new marker
                  L.marker([lat, lng]).addTo(window.map);
                }
              })
              .catch(error => {
                console.error('Error:', error);
                status.textContent = "Location updated";
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
  </script>
</body>

</html>