<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'];
  $password = $_POST['password'];

  // Query the separate admins table
  $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
  $stmt->execute([$username]);
  $admin = $stmt->fetch();

  if ($admin && password_verify($password, $admin['password'])) {
    // Set session variables for admin
    $_SESSION['admin_id'] = $admin['admin_id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['is_admin'] = true;

    header("Location: admin_dashboard.php");
    exit();
  } else {
    $error = "Invalid username or password";
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Admin Login</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <div class="min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-96">
      <h1 class="text-2xl font-bold mb-6 text-center">Admin Login</h1>

      <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="admin_login.php">
        <div class="mb-4">
          <label for="username" class="block text-gray-700 mb-2">Username</label>
          <input type="text" id="username" name="username" class="w-full px-3 py-2 border rounded">
        </div>

        <div class="mb-6">
          <label for="password" class="block text-gray-700 mb-2">Password</label>
          <input type="password" id="password" name="password" class="w-full px-3 py-2 border rounded">
        </div>

        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
          Login
        </button>
      </form>

      <div class="mt-4 text-center">
        <a href="login.php" class="text-blue-600 hover:underline">Back to User Login</a>
      </div>
    </div>
  </div>
</body>

</html>