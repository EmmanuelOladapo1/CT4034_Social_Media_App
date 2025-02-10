<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Social Media</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <div class="min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-96">
      <h2 class="text-2xl font-bold mb-6 text-center">Login</h2>

      <?php
      session_start();
      if (isset($_SESSION['login_error'])) {
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">';
        echo htmlspecialchars($_SESSION['login_error']);
        echo '</div>';
        unset($_SESSION['login_error']);
      }
      ?>

      <form id="loginForm" action="login_process.php" method="POST" class="space-y-4">
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
          <input type="email" id="email" name="email" required
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
          <input type="password" id="password" name="password" required
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>

        <button type="submit"
          class="w-full bg-blue-500 text-white rounded-md py-2 px-4 hover:bg-blue-600">
          Login
        </button>
      </form>

      <p class="mt-4 text-center text-sm text-gray-600">
        Don't have an account?
        <a href="register.php" class="text-blue-500 hover:text-blue-700">Register here</a>
      </p>
    </div>
  </div>
</body>

</html>