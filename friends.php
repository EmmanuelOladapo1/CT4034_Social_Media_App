<?php
// friends.php
// Purpose: Handles friend management and displays friends list
session_start();
require_once 'config/database.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle friend actions
if (isset($_GET['action']) && isset($_GET['user_id'])) {
  $action = $_GET['action'];
  $user_id = $_GET['user_id'];

  // Validate user_id
  if (!is_numeric($user_id)) {
    $error = "Invalid user ID";
  } else {
    // Check if user exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    if ($stmt->rowCount() == 0) {
      $error = "User does not exist";
    } else {
      switch ($action) {
        case 'add':
          // Check if already friends
          $stmt = $conn->prepare("SELECT * FROM friends
                                 WHERE (user_id1 = ? AND user_id2 = ?)
                                 OR (user_id1 = ? AND user_id2 = ?)");
          $stmt->execute([$_SESSION['user_id'], $user_id, $user_id, $_SESSION['user_id']]);

          if ($stmt->rowCount() > 0) {
            $error = "Already friends with this user";
          } else {
            // Add friend
            try {
              $stmt = $conn->prepare("INSERT INTO friends (user_id1, user_id2, created_at) VALUES (?, ?, NOW())");
              $stmt->execute([$_SESSION['user_id'], $user_id]);
              $success = "Friend added successfully";
            } catch (PDOException $e) {
              $error = "Error adding friend: " . $e->getMessage();
            }
          }
          break;

        case 'remove':
          // Remove friend
          $stmt = $conn->prepare("DELETE FROM friends
                                 WHERE (user_id1 = ? AND user_id2 = ?)
                                 OR (user_id1 = ? AND user_id2 = ?)");
          $stmt->execute([$_SESSION['user_id'], $user_id, $user_id, $_SESSION['user_id']]);

          if ($stmt->rowCount() > 0) {
            $success = "Friend removed successfully";
          } else {
            $error = "Friend relationship not found";
          }
          break;

        default:
          $error = "Invalid action";
          break;
      }
    }
  }

  // Redirect to avoid form resubmission
  $redirect_url = "friends.php";
  if (isset($success)) {
    $redirect_url .= "?success=" . urlencode($success);
  } else if (isset($error)) {
    $redirect_url .= "?error=" . urlencode($error);
  }

  header("Location: " . $redirect_url);
  exit();
}

// Get friends list
$stmt = $conn->prepare("
    SELECT u.user_id, u.username, u.profile_image, u.created_at
    FROM users u
    JOIN friends f ON (
        (f.user_id1 = u.user_id AND f.user_id2 = ?) OR
        (f.user_id2 = u.user_id AND f.user_id1 = ?)
    )
    WHERE u.user_id != ?
    ORDER BY u.username
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get other users who are not friends
$stmt = $conn->prepare("
    SELECT u.user_id, u.username, u.profile_image, u.created_at
    FROM users u
    WHERE u.user_id != ? AND u.user_id NOT IN (
        SELECT CASE
            WHEN f.user_id1 = ? THEN f.user_id2
            WHEN f.user_id2 = ? THEN f.user_id1
        END
        FROM friends f
        WHERE f.user_id1 = ? OR f.user_id2 = ?
    )
    ORDER BY u.username
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$other_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Friends</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">Friends</h1>
      <div class="flex space-x-2">
        <a href="messages.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Messages</a>
        <a href="feed.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Back to Feed</a>
      </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($_GET['success']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($_GET['error']); ?>
      </div>
    <?php endif; ?>

    <!-- Friends List Section -->
    <div class="bg-white p-4 rounded-lg shadow-md mb-6">
      <h2 class="text-xl font-bold mb-4">My Friends (<?php echo count($friends); ?>)</h2>

      <?php if (empty($friends)): ?>
        <p class="text-gray-500">You don't have any friends yet. Add some friends below!</p>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <?php foreach ($friends as $friend): ?>
            <div class="border rounded-lg p-4 flex items-center relative group">
              <div class="w-12 h-12 rounded-full overflow-hidden bg-gray-300 mr-3 relative">
                <?php if ($friend['profile_image']): ?>
                  <img src="<?php echo htmlspecialchars($friend['profile_image']); ?>" class="w-full h-full object-cover" alt="Profile">
                <?php else: ?>
                  <div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </div>
                <?php endif; ?>

                <!-- Hover menu -->
                <div class="hidden group-hover:flex absolute inset-0 bg-black bg-opacity-50 rounded-full items-center justify-center">
                  <a href="messages.php?user_id=<?php echo $friend['user_id']; ?>" class="text-white hover:text-blue-200" title="Message">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                  </a>
                </div>
              </div>

              <div class="flex-grow">
                <a href="profile.php?id=<?php echo $friend['user_id']; ?>" class="font-medium hover:underline">
                  <?php echo htmlspecialchars($friend['username']); ?>
                </a>
                <p class="text-xs text-gray-500">Joined: <?php echo date('M d, Y', strtotime($friend['created_at'])); ?></p>
              </div>

              <div class="ml-2 flex space-x-2">
                <a href="messages.php?user_id=<?php echo $friend['user_id']; ?>"
                  class="px-2 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600">
                  Message
                </a>
                <a href="friends.php?action=remove&user_id=<?php echo $friend['user_id']; ?>"
                  class="px-2 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600"
                  onclick="return confirm('Are you sure you want to remove this friend?')">
                  Remove
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Other Users Section -->
    <div class="bg-white p-4 rounded-lg shadow-md">
      <h2 class="text-xl font-bold mb-4">Other Users</h2>

      <?php if (empty($other_users)): ?>
        <p class="text-gray-500">No other users found.</p>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <?php foreach ($other_users as $user): ?>
            <div class="border rounded-lg p-4 flex items-center relative group">
              <div class="w-12 h-12 rounded-full overflow-hidden bg-gray-300 mr-3 relative">
                <?php if ($user['profile_image']): ?>
                  <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" class="w-full h-full object-cover" alt="Profile">
                <?php else: ?>
                  <div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </div>
                <?php endif; ?>

                <!-- Hover menu -->
                <div class="hidden group-hover:flex absolute inset-0 bg-black bg-opacity-50 rounded-full items-center justify-center">
                  <a href="messages.php?user_id=<?php echo $user['user_id']; ?>" class="text-white hover:text-blue-200" title="Message">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                  </a>
                </div>
              </div>

              <div class="flex-grow">
                <a href="profile.php?id=<?php echo $user['user_id']; ?>" class="font-medium hover:underline">
                  <?php echo htmlspecialchars($user['username']); ?>
                </a>
                <p class="text-xs text-gray-500">Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
              </div>

              <div class="ml-2 flex space-x-2">
                <a href="messages.php?user_id=<?php echo $user['user_id']; ?>"
                  class="px-2 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600">
                  Message
                </a>
                <a href="friends.php?action=add&user_id=<?php echo $user['user_id']; ?>"
                  class="px-2 py-1 bg-green-500 text-white text-sm rounded hover:bg-green-600">
                  Add Friend
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>