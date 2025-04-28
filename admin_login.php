<?php
session_start();
require_once 'config/database.php';

// Create default admin account if it doesn't exist
$stmt = $conn->prepare("SELECT COUNT(*) FROM admins WHERE username = 'admin'");
$stmt->execute();
if ($stmt->fetchColumn() == 0) {
  // Create default admin account
  $default_username = 'admin';
  $default_password = 'admin123'; // This will be the password you can use
  $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

  $stmt = $conn->prepare("INSERT INTO admins (username, email, password_hash, full_name, created_at)
                        VALUES (?, ?, ?, ?, NOW())");
  $stmt->execute([$default_username, 'admin@example.com', $hashed_password, 'Administrator']);

  // Show message about created account
  $admin_created = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'];
  $password = $_POST['password'];

  // Query the separate admins table
  $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
  $stmt->execute([$username]);
  $admin = $stmt->fetch();

  // FIXED: Changed from 'password' to 'password_hash' to match the column name
  if ($admin && password_verify($password, $admin['password_hash'])) {
    // Set session variables for admin
    $_SESSION['admin_id'] = $admin['admin_id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['is_admin'] = true;

    // Update last login time
    $stmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?");
    $stmt->execute([$admin['admin_id']]);

    header("Location: admin_dashboard.php");
    exit();
  } else {
    $error = "Invalid username or password";
  }
}

// Default credentials for display
$default_login = [
  'username' => 'admin',
  'password' => 'admin123'
];
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

      <?php if (isset($admin_created)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
          Default admin account has been created!
        </div>
      <?php endif; ?>

      <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <!-- Default Login Info Box -->
      <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-6">
        <h3 class="font-bold">Default Login:</h3>
        <p>Username: <?php echo htmlspecialchars($default_login['username']); ?></p>
        <p>Password: <?php echo htmlspecialchars($default_login['password']); ?></p>
      </div>

      <form method="POST" action="admin_login.php">
        <div class="mb-4">
          <label for="username" class="block text-gray-700 mb-2">Username</label>
          <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($default_login['username']); ?>" class="w-full px-3 py-2 border rounded">
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