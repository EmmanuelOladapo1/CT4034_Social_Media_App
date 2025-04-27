<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

// Get all users except current user
$stmt = $conn->prepare("SELECT user_id, username, profile_image FROM users WHERE user_id != ? ORDER BY username");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Users</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl">All Users</h1>
      <a href="feed.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Back to Feed</a>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($users as $user): ?>
          <a href="profile.php?id=<?php echo $user['user_id']; ?>" class="flex items-center p-3 border rounded hover:bg-gray-50">
            <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-300 mr-3">
              <?php if ($user['profile_image']): ?>
                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" class="w-full h-full object-cover" alt="Profile">
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
              <?php endif; ?>
            </div>
            <span class="font-medium"><?php echo htmlspecialchars($user['username']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</body>

</html>