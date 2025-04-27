<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Reports</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">Reports</h1>
      <div>
        <a href="blocked.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 mr-2">View Blocked Users</a>
        <a href="feed.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Back to Feed</a>
      </div>
    </div>

    <div class="bg-white p-4 rounded-lg shadow-md">
      <h2 class="text-xl font-bold mb-3">Your Reports</h2>
      <?php
      // Get reports made by user
      $stmt = $conn->prepare("
        SELECT r.*, u.username, u.profile_image
        FROM reports r
        JOIN users u ON r.reported_id = u.user_id
        WHERE r.reporter_id = ?
        ORDER BY r.created_at DESC
      ");
      $stmt->execute([$_SESSION['user_id']]);
      $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if (count($reports) > 0):
      ?>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reported User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report Date</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($reports as $report): ?>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-10 w-10 rounded-full overflow-hidden bg-gray-300">
                        <?php if ($report['profile_image']): ?>
                          <img src="<?php echo htmlspecialchars($report['profile_image']); ?>" class="h-10 w-10 object-cover" alt="">
                        <?php else: ?>
                          <div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                          </div>
                        <?php endif; ?>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($report['username']); ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($report['reason']); ?></div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500"><?php echo date('M d, Y g:i a', strtotime($report['created_at'])); ?></div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-gray-500">You haven't reported any users.</p>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>