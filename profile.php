<?php
// profile.php
// Purpose: Handles user profile display and editing functionalities
session_start();
require_once 'config/database.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

// Determine if viewing own profile or someone else's
$profile_id = isset($_GET['id']) ? $_GET['id'] : $_SESSION['user_id'];
$viewing_own_profile = ($profile_id == $_SESSION['user_id']);

// Get profile user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$profile_id]);
$profile_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile_user) {
  // User not found
  header("Location: feed.php");
  exit();
}

// Fix the role check
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_role = $stmt->fetchColumn();
$isAdmin = ($user_role === 'admin');

// Check if users are friends
$is_friend = false;
if (!$viewing_own_profile) {
  $stmt = $conn->prepare("SELECT * FROM friends
                      WHERE (user_id1 = ? AND user_id2 = ?)
                      OR (user_id1 = ? AND user_id2 = ?)");
  $stmt->execute([$_SESSION['user_id'], $profile_id, $profile_id, $_SESSION['user_id']]);
  $is_friend = ($stmt->rowCount() > 0);
}

// Update profile - only process if viewing own profile
if ($viewing_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $email = trim($_POST['email']);

  // Validate inputs
  $errors = [];

  if (empty($username)) {
    $errors[] = "Username is required";
  }

  if (empty($email)) {
    $errors[] = "Email is required";
  } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format";
  }

  // Check if username or email already exists for another user
  if (!empty($username) && !empty($email)) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
    $stmt->execute([$username, $email, $_SESSION['user_id']]);

    if ($stmt->rowCount() > 0) {
      $errors[] = "Username or email already exists";
    }
  }

  // Handle profile image upload
  $profile_image = $profile_user['profile_image']; // Default to current image

  if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
    $target_dir = "uploads/profile/";
    if (!file_exists($target_dir)) {
      mkdir($target_dir, 0777, true);
    }

    $target_file = $target_dir . time() . '_' . basename($_FILES["profile_image"]["name"]);

    // Get image info and validate
    $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
    if ($check !== false) {
      // Valid image
      if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
        $profile_image = $target_file;
      } else {
        $errors[] = "Error uploading profile image";
      }
    } else {
      $errors[] = "File is not a valid image";
    }
  }

  // If no errors, update profile
  if (empty($errors)) {
    try {
      $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, profile_image = ? WHERE user_id = ?");
      $stmt->execute([$username, $email, $profile_image, $_SESSION['user_id']]);

      // Update session data
      $_SESSION['username'] = $username;

      $success_message = "Profile updated successfully";

      // Refresh user data
      $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
      $stmt->execute([$_SESSION['user_id']]);
      $profile_user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      $errors[] = "Error updating profile: " . $e->getMessage();
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?php echo $viewing_own_profile ? 'My Profile' : htmlspecialchars($profile_user['username']) . "'s Profile"; ?></title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl"><?php echo $viewing_own_profile ? 'My Profile' : htmlspecialchars($profile_user['username']) . "'s Profile"; ?></h1>
      <a href="feed.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Back to Feed</a>
    </div>

    <?php if (!empty($errors) && $viewing_own_profile): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($success_message); ?>
      </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex flex-col md:flex-row">
        <div class="md:w-1/3 mb-6 md:mb-0 flex flex-col items-center">
          <div class="w-32 h-32 rounded-full overflow-hidden mb-4 relative user-profile-image">
            <?php if ($profile_user['profile_image']): ?>
              <img src="<?php echo htmlspecialchars($profile_user['profile_image']); ?>" class="w-full h-full object-cover" alt="Profile picture">
            <?php else: ?>
              <div class="w-full h-full bg-gray-300 flex items-center justify-center text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
            <?php endif; ?>

            <!-- Profile Hover Menu (only for other users) -->
            <?php if (!$viewing_own_profile): ?>
              <div class="profile-hover-menu hidden absolute inset-0 bg-black bg-opacity-50 rounded-full flex items-center justify-center space-x-2">
                <a href="messages.php?user_id=<?php echo $profile_user['user_id']; ?>" class="bg-blue-500 text-white p-2 rounded-full hover:bg-blue-600" title="Message">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                  </svg>
                </a>
                <?php if ($is_friend): ?>
                  <a href="friends.php?action=remove&user_id=<?php echo $profile_user['user_id']; ?>" class="bg-red-500 text-white p-2 rounded-full hover:bg-red-600" title="Remove Friend">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6" />
                    </svg>
                  </a>
                <?php else: ?>
                  <a href="friends.php?action=add&user_id=<?php echo $profile_user['user_id']; ?>" class="bg-green-500 text-white p-2 rounded-full hover:bg-green-600" title="Add Friend">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                  </a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
          <p class="text-lg font-bold"><?php echo htmlspecialchars($profile_user['username']); ?></p>
          <p class="text-gray-500"><?php echo htmlspecialchars($profile_user['email']); ?></p>
          <p class="text-sm text-gray-500">Joined: <?php echo date('F j, Y', strtotime($profile_user['created_at'])); ?></p>

          <!-- Friend/Message buttons - only show when viewing other users' profiles -->
          <?php if (!$viewing_own_profile): ?>
            <div class="mt-4 space-y-2 w-full">
              <a href="messages.php?user_id=<?php echo $profile_user['user_id']; ?>"
                class="inline-block w-full py-2 bg-blue-500 text-white text-center rounded hover:bg-blue-600">
                Message
              </a>

              <?php if ($is_friend): ?>
                <a href="friends.php?action=remove&user_id=<?php echo $profile_user['user_id']; ?>"
                  class="inline-block w-full py-2 bg-red-500 text-white text-center rounded hover:bg-red-600">
                  Remove Friend
                </a>
              <?php else: ?>
                <a href="friends.php?action=add&user_id=<?php echo $profile_user['user_id']; ?>"
                  class="inline-block w-full py-2 bg-green-500 text-white text-center rounded hover:bg-green-600">
                  Add Friend
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <!-- Block/Report buttons - only show when viewing other users' profiles -->
          <?php if (!$viewing_own_profile): ?>
            <div class="mt-4 flex space-x-2">
              <button type="button" onclick="showReportModal()" class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600">Report User</button>
              <button type="button" onclick="showBlockModal()" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Block User</button>

              <?php if ($isAdmin): ?>
                <a href="admin_dashboard.php?delete_user=<?php echo $profile_user['user_id']; ?>"
                  onclick="return confirm('Are you sure you want to delete this user?')"
                  class="px-4 py-2 bg-red-700 text-white rounded hover:bg-red-800">Delete User</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($viewing_own_profile): ?>
          <!-- Profile edit form - only show when viewing own profile -->
          <div class="md:w-2/3 md:pl-6">
            <h2 class="text-xl font-bold mb-4">Edit Profile</h2>
            <form action="profile.php" method="POST" enctype="multipart/form-data">
              <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($profile_user['username']); ?>"
                  class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
              </div>

              <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile_user['email']); ?>"
                  class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
              </div>

              <div class="mb-4">
                <label for="profile_image" class="block text-sm font-medium text-gray-700 mb-1">Profile Picture</label>
                <input type="file" id="profile_image" name="profile_image" accept="image/*"
                  class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
              </div>

              <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Save Changes</button>
            </form>
          </div>
        <?php else: ?>
          <!-- Display user posts or other info when viewing someone else's profile -->
          <div class="md:w-2/3 md:pl-6">
            <h2 class="text-xl font-bold mb-4">Recent Posts</h2>

            <?php
            // Get user's posts
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
                  <p class="mb-2"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>

                  <?php if ($post['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($post['image_url']); ?>" class="max-w-full h-auto rounded-lg mb-2" alt="Post image">
                  <?php endif; ?>

                  <div class="text-sm text-gray-500">
                    <span><?php echo date('M d, Y g:i a', strtotime($post['created_at'])); ?></span>
                    <span class="ml-3"><?php echo $post['like_count']; ?> likes</span>
                    <span class="ml-3"><?php echo $post['comment_count']; ?> comments</span>
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

  <!-- Report Modal -->
  <?php if (!$viewing_own_profile): ?>
    <div id="reportModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-lg max-w-md w-full">
        <h3 class="text-xl font-bold mb-4">Report User</h3>
        <form action="report_user.php" method="POST">
          <input type="hidden" name="user_id" value="<?php echo $profile_user['user_id']; ?>">
          <div class="mb-4">
            <label class="block text-gray-700 mb-2">Reason:</label>
            <textarea name="reason" rows="4" class="w-full border rounded p-2" required></textarea>
          </div>
          <div class="flex justify-end space-x-2">
            <button type="button" onclick="hideReportModal()" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
            <button type="submit" name="report_user" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Report</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Block Modal -->
    <div id="blockModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-lg max-w-md w-full">
        <h3 class="text-xl font-bold mb-4">Block User</h3>
        <p class="mb-4">Are you sure you want to block this user? You won't see their posts or receive messages from them.</p>
        <form action="report_user.php" method="POST">
          <input type="hidden" name="user_id" value="<?php echo $profile_user['user_id']; ?>">
          <div class="flex justify-end space-x-2">
            <button type="button" onclick="hideBlockModal()" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
            <button type="submit" name="block_user" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Block User</button>
          </div>
        </form>
      </div>
    </div>

    <script>
      // Modal control functions
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

      // Profile image hover functionality
      document.addEventListener('DOMContentLoaded', function() {
        const profileImage = document.querySelector('.user-profile-image');
        const hoverMenu = document.querySelector('.profile-hover-menu');

        if (profileImage && hoverMenu) {
          profileImage.addEventListener('mouseenter', function() {
            hoverMenu.classList.remove('hidden');
          });

          profileImage.addEventListener('mouseleave', function() {
            hoverMenu.classList.add('hidden');
          });
        }
      });
    </script>
  <?php endif; ?>
</body>

</html>