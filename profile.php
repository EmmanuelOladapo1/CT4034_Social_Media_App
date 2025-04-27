<?php
session_start();
require_once 'config/database.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

// Determine profile to view
$profile_id = $_GET['id'] ?? $_SESSION['user_id'];
$viewing_own_profile = ($profile_id == $_SESSION['user_id']);

// Get profile data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$profile_id]);
$profile_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile_user) {
  header("Location: feed.php");
  exit();
}

// Admin check
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$isAdmin = ($stmt->fetchColumn() === 'admin');

// Friend check
$is_friend = false;
if (!$viewing_own_profile) {
  $stmt = $conn->prepare("SELECT id FROM friends
                          WHERE (user_id1 = ? AND user_id2 = ?)
                          OR (user_id1 = ? AND user_id2 = ?)");
  $stmt->execute([$_SESSION['user_id'], $profile_id, $profile_id, $_SESSION['user_id']]);
  $is_friend = $stmt->rowCount() > 0;
}

// Handle profile update
$errors = [];
$success_message = null;

if ($viewing_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $email = trim($_POST['email']);

  // Validation
  if (empty($username)) $errors[] = "Username is required";
  if (empty($email)) {
    $errors[] = "Email is required";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format";
  }

  // Check existing credentials
  if (empty($errors)) {
    $stmt = $conn->prepare("SELECT user_id FROM users
                              WHERE (username = ? OR email = ?)
                              AND user_id != ?");
    $stmt->execute([$username, $email, $_SESSION['user_id']]);
    if ($stmt->rowCount() > 0) {
      $errors[] = "Username or email already exists";
    }
  }

  // Handle image upload
  $profile_image = $profile_user['profile_image'];
  if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
    $target_dir = "uploads/profile/";
    if (!file_exists($target_dir)) {
      mkdir($target_dir, 0777, true);
    }

    $target_file = $target_dir . time() . '_' . basename($_FILES['profile_image']['name']);
    $check = getimagesize($_FILES['profile_image']['tmp_name']);

    if ($check !== false) {
      if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
        $profile_image = $target_file;
      } else {
        $errors[] = "Error uploading image";
      }
    } else {
      $errors[] = "Invalid image file";
    }
  }

  // Update database
  if (empty($errors)) {
    try {
      $stmt = $conn->prepare("UPDATE users
                                  SET username = ?, email = ?, profile_image = ?
                                  WHERE user_id = ?");
      $stmt->execute([$username, $email, $profile_image, $_SESSION['user_id']]);

      $_SESSION['username'] = $username;
      $success_message = "Profile updated successfully";

      // Refresh user data
      $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
      $stmt->execute([$_SESSION['user_id']]);
      $profile_user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      $errors[] = "Database error: " . $e->getMessage();
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= $viewing_own_profile ? 'My Profile' : htmlspecialchars($profile_user['username']) . "'s Profile" ?></title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl">
        <?= $viewing_own_profile ? 'My Profile' : htmlspecialchars($profile_user['username']) . "'s Profile" ?>
      </h1>
      <a href="feed.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Back to Feed</a>
    </div>

    <?php if (!empty($errors) && $viewing_own_profile): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?= htmlspecialchars($success_message) ?>
      </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex flex-col md:flex-row">
        <div class="md:w-1/3 mb-6 md:mb-0 flex flex-col items-center">
          <div class="w-32 h-32 rounded-full overflow-hidden mb-4">
            <?php if ($profile_user['profile_image']): ?>
              <img src="<?= htmlspecialchars($profile_user['profile_image']) ?>"
                class="w-full h-full object-cover"
                alt="Profile picture">
            <?php else: ?>
              <div class="w-full h-full bg-gray-300 flex items-center justify-center text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
            <?php endif; ?>
          </div>
          <p class="text-lg font-bold"><?= htmlspecialchars($profile_user['username']) ?></p>
          <p class="text-gray-500"><?= htmlspecialchars($profile_user['email']) ?></p>
          <p class="text-sm text-gray-500">Joined: <?= date('F j, Y', strtotime($profile_user['created_at'])) ?></p>

          <?php if (!$viewing_own_profile): ?>
            <div class="mt-4 w-full">
              <?php if (!$is_friend): ?>
                <a href="friends.php?action=send_request&user_id=<?= $profile_user['user_id'] ?>"
                  class="block w-full py-2 mb-2 bg-blue-500 text-white text-center rounded hover:bg-blue-600">
                  Add Friend
                </a>
              <?php else: ?>
                <a href="friends.php?action=remove&user_id=<?= $profile_user['user_id'] ?>"
                  class="block w-full py-2 mb-2 bg-red-500 text-white text-center rounded hover:bg-red-600">
                  Remove Friend
                </a>
              <?php endif; ?>
            </div>

            <div class="mt-4 flex space-x-2 w-full">
              <button type="button" onclick="showReportModal()"
                class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600">
                Report User
              </button>
              <button type="button" onclick="showBlockModal()"
                class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                Block User
              </button>

              <?php if ($isAdmin): ?>
                <a href="admin_dashboard.php?delete_user=<?= $profile_user['user_id'] ?>"
                  onclick="return confirm('Are you sure you want to delete this user?')"
                  class="px-4 py-2 bg-red-700 text-white rounded hover:bg-red-800">
                  Delete User
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($viewing_own_profile): ?>
          <div class="md:w-2/3 md:pl-6">
            <h2 class="text-xl font-bold mb-4">Edit Profile</h2>
            <form action="profile.php" method="POST" enctype="multipart/form-data">
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($profile_user['username']) ?>"
                  class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
              </div>
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($profile_user['email']) ?>"
                  class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
              </div>
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Profile Image</label>
                <input type="file" name="profile_image" accept="image/*"
                  class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
              </div>
              <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                Save Changes
              </button>
            </form>
          </div>
        <?php else: ?>
          <div class="md:w-2/3 md:pl-6">
            <h2 class="text-xl font-bold mb-4">Recent Posts</h2>
            <?php
            $post_stmt = $conn->prepare("SELECT p.*,
                                                 (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count,
                                                 (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count
                                                 FROM posts p
                                                 WHERE p.user_id = ?
                                                 ORDER BY p.created_at DESC
                                                 LIMIT 5");
            $post_stmt->execute([$profile_user['user_id']]);
            $posts = $post_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($posts) > 0):
              foreach ($posts as $post): ?>
                <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                  <?php if ($post['image_url']): ?>
                    <img src="<?= htmlspecialchars($post['image_url']) ?>"
                      class="max-w-full h-auto rounded-lg mb-2"
                      alt="Post image">
                  <?php endif; ?>
                  <p class="mb-2"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                  <div class="text-sm text-gray-500">
                    <span><?= date('M d, Y g:i a', strtotime($post['created_at'])) ?></span>
                    <span class="ml-3"><?= $post['like_count'] ?> likes</span>
                    <span class="ml-3"><?= $post['comment_count'] ?> comments</span>
                  </div>
                </div>
              <?php endforeach;
            else: ?>
              <p class="text-gray-500">No posts to display</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!$viewing_own_profile): ?>
    <div id="reportModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-lg max-w-md w-full">
        <h3 class="text-xl font-bold mb-4">Report User</h3>
        <form action="report_user.php" method="POST">
          <input type="hidden" name="user_id" value="<?= $profile_user['user_id'] ?>">
          <div class="mb-4">
            <label class="block text-gray-700 mb-2">Reason:</label>
            <textarea name="reason" rows="4" class="w-full border rounded p-2" required></textarea>
          </div>
          <div class="flex justify-end space-x-2">
            <button type="button" onclick="hideReportModal()" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Report</button>
          </div>
        </form>
      </div>
    </div>

    <div id="blockModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-lg max-w-md w-full">
        <h3 class="text-xl font-bold mb-4">Block User</h3>
        <p class="mb-4">Are you sure? You won't see their posts or messages.</p>
        <form action="block_user.php" method="POST">
          <input type="hidden" name="user_id" value="<?= $profile_user['user_id'] ?>">
          <div class="flex justify-end space-x-2">
            <button type="button" onclick="hideBlockModal()" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Block</button>
          </div>
        </form>
      </div>
    </div>

    <script>
      function showReportModal() {
        document.getElementById('reportModal').classList.remove('hidden');
      }

      function hideReportModal() {
        document.getElementById('reportModal').classList.add('hidden');
      }

      function showBlockModal() {
        document.getElementById('blockModal').classList.remove('hidden');
      }

      function hideBlockModal() {
        document.getElementById('blockModal').classList.add('hidden');
      }
    </script>
  <?php endif; ?>
</body>

</html>