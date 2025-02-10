<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - Social Media</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <div class="min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-96">
      <h2 class="text-2xl font-bold mb-6 text-center">Create Account</h2>

      <!-- Registration Form -->
      <form id="registerForm" action="register_process.php" method="POST" class="space-y-4">
        <div>
          <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
          <input type="text" id="username" name="username" required
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>

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

        <div>
          <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>

        <div id="error-message" class="text-red-500 text-sm hidden"></div>

        <button type="submit"
          class="w-full bg-blue-500 text-white rounded-md py-2 px-4 hover:bg-blue-600">
          Register
        </button>
      </form>

      <p class="mt-4 text-center text-sm text-gray-600">
        Already have an account?
        <a href="login.php" class="text-blue-500 hover:text-blue-700">Login here</a>
      </p>
    </div>
  </div>

  <script>
    document.getElementById('registerForm').addEventListener('submit', function(e) {
      e.preventDefault();

      const username = document.getElementById('username').value;
      const email = document.getElementById('email').value;
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      const errorMessage = document.getElementById('error-message');

      // Reset error message
      errorMessage.classList.add('hidden');

      // Validation
      if (password.length < 8) {
        showError('Password must be at least 8 characters long');
        return;
      }

      if (password !== confirmPassword) {
        showError('Passwords do not match');
        return;
      }

      if (!isValidEmail(email)) {
        showError('Please enter a valid email address');
        return;
      }

      // If validation passes, submit the form
      this.submit();
    });

    function showError(message) {
      const errorMessage = document.getElementById('error-message');
      errorMessage.textContent = message;
      errorMessage.classList.remove('hidden');
    }

    function isValidEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
    }
  </script>
</body>

</html>