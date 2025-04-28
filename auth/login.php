<?php
session_start();
require_once '../config/database.php';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
  // Redirect based on role
  if ($_SESSION['role'] === 'admin') {
    header("Location: ../admin_dashboard.php");
  } else {
    header("Location: ../feed.php");
  }
  exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'];
  $password = $_POST['password'];

  if (empty($username) || empty($password)) {
    $error = "Please enter both username and password";
  } else {
    // Get user from database
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
      // Set session variables
      $_SESSION['user_id'] = $user['user_id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['role'] = $user['role']; // Store the user's role

      // Update last login time
      $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
      $stmt->execute([$user['user_id']]);

      // Redirect based on role
      if ($user['role'] === 'admin') {
        header("Location: ../admin_dashboard.php");
      } else {
        header("Location: ../feed.php");
      }
      exit();
    } else {
      $error = "Invalid username or password";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <div class="min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-96">
      <h1 class="text-2xl font-bold mb-6 text-center">Login</h1>

      <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <div class="mb-4">
          <label for="username" class="block text-gray-700 mb-2">Username</label>
          <input type="text" id="username" name="username"
            class="w-full px-3 py-2 border rounded"
            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
        </div>

        <div class="mb-6">
          <label for="password" class="block text-gray-700 mb-2">Password</label>
          <input type="password" id="password" name="password"
            class="w-full px-3 py-2 border rounded">
        </div>

        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
          Login
        </button>
      </form>

      <div class="mt-4 text-center">
        <a href="register.php" class="text-blue-600 hover:underline">Don't have an account? Register</a>
      </div>
    </div>
  </div>
</body>

</html>