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

  // Check post ownership
  $stmt = $conn->prepare("SELECT user_id FROM posts WHERE post_id = ?");
  $stmt->execute([$post_id]);
  $post = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$post || $post['user_id'] != $_SESSION['user_id']) {
    header("Location: feed.php");
    exit();
  }

  try {
    $stmt = $conn->prepare("UPDATE posts SET content = ? WHERE post_id = ?");
    $stmt->execute([$content, $post_id]);
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
</head>

<body class="bg-gray-100">
  <div class="container mx-auto p-4">
    <h1 class="text-2xl mb-4">Edit Post</h1>

    <div class="bg-white p-4 rounded-lg shadow mb-4">
      <form action="edit_post.php" method="POST" class="space-y-4">
        <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['post_id']); ?>">
        <textarea name="content" class="w-full p-2 border rounded" rows="4"><?php echo htmlspecialchars($post['content']); ?></textarea>

        <div class="flex justify-between">
          <a href="feed.php" class="bg-gray-500 text-white px-4 py-2 rounded">Cancel</a>
          <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</body>

</html>