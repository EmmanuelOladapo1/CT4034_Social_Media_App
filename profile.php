<?php
// profile.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
  $profile_image = $user['profile_image']; // Default to current image

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
      $user = $stmt->fetch(PDO::FETCH_ASSOC);
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
  <title>Profile</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl">Profile Settings</h1>
      <a href="feed.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Back to Feed</a>
    </div>

    <?php if (!empty($errors)): ?>
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
          <div class="w-32 h-32 rounded-full overflow-hidden mb-4">
            <?php if ($user['profile_image']): ?>
              <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" class="w-full h-full object-cover" alt="Profile picture">
            <?php else: ?>
              <div class="w-full h-full bg-gray-300 flex items-center justify-center text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
            <?php endif; ?>
          </div>
          <p class="text-lg font-bold"><?php echo htmlspecialchars($user['username']); ?></p>
          <p class="text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
          <p class="text-sm text-gray-500">Joined: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
        </div>

        <div class="md:w-2/3 md:pl-6">
          <h2 class="text-xl font-bold mb-4">Edit Profile</h2>
          <form action="profile.php" method="POST" enctype="multipart/form-data">
            <div class="mb-4">
              <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
              <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>"
                class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="mb-4">
              <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"
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
      </div>
    </div>
  </div>
</body>

</html>