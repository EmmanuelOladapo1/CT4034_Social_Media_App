<?php
// tests/test_location.php
session_start();
require_once '../config/database.php';

echo "<h1>Location Feature Test</h1>";

try {
  // Check for posts with location
  $stmt = $conn->query("SELECT COUNT(*) as count FROM posts WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  echo "<p>Posts with location data: " . $result['count'] . "</p>";

  // Display a sample post with location
  $stmt = $conn->query("SELECT p.*, u.username FROM posts p
                         JOIN users u ON p.user_id = u.user_id
                         WHERE p.latitude IS NOT NULL AND p.longitude IS NOT NULL
                         LIMIT 1");

  if ($stmt->rowCount() > 0) {
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h2>Sample Post with Location</h2>";
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin-bottom: 20px;'>";
    echo "<p><strong>User:</strong> " . htmlspecialchars($post['username']) . "</p>";
    echo "<p><strong>Content:</strong> " . htmlspecialchars($post['content']) . "</p>";
    echo "<p><strong>Location:</strong> <span id='location-name'>Fetching location name...</span></p>";
    echo "<p><strong>Posted at:</strong> " . $post['created_at'] . "</p>";

    // Include Leaflet map to test
    echo "</div>";
    echo "<h3>Location Map Test</h3>";
    echo "<link rel='stylesheet' href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css' />";
    echo "<script src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'></script>";
    echo "<div id='test-map' style='height: 300px; width: 100%;'></div>";
    echo "<script>
            const testMap = L.map('test-map').setView([" . $post['latitude'] . ", " . $post['longitude'] . "], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(testMap);
            L.marker([" . $post['latitude'] . ", " . $post['longitude'] . "]).addTo(testMap);

            // Fetch location name using Nominatim (OpenStreetMap's reverse geocoding service)
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=" . $post['latitude'] . "&lon=" . $post['longitude'] . "&zoom=18&addressdetails=1`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('location-name').textContent = data.display_name || 'Location not found';
                })
                .catch(error => {
                    console.error('Error fetching location name:', error);
                    document.getElementById('location-name').textContent = 'Error fetching location name';
                });
        </script>";
  } else {
    echo "<p>No posts with location data found. Create a post with location first.</p>";
  }
} catch (PDOException $e) {
  echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
