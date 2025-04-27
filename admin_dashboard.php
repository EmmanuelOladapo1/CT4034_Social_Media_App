<?php
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
  header("Location: admin_login.php");
  exit();
}

// Get admin information
$stmt = $conn->prepare("SELECT * FROM admins WHERE admin_id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle delete user
if (isset($_GET['delete_user'])) {
  $user_id = $_GET['delete_user'];
  $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
  $stmt->execute([$user_id]);
  header("Location: admin_dashboard.php?deleted=1");
  exit();
}

// Get post statistics
$timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'week';

switch ($timeframe) {
  case 'month':
    $interval = '1 MONTH';
    break;
  case 'year':
    $interval = '1 YEAR';
    break;
  default: // week
    $interval = '1 WEEK';
}

$stmt = $conn->prepare("SELECT COUNT(*) as post_count, user_id,
                       (SELECT username FROM users WHERE user_id = p.user_id) as username
                       FROM posts p
                       WHERE created_at > DATE_SUB(NOW(), INTERVAL $interval)
                       GROUP BY user_id
                       ORDER BY post_count DESC");
$stmt->execute();
$post_statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get reports - using reported_id as shown in your database schema
$stmt = $conn->prepare("SELECT r.*,
                      (SELECT username FROM users WHERE user_id = r.reporter_id) as reporter_name,
                      (SELECT username FROM users WHERE user_id = r.reported_id) as reported_name
                      FROM reports r
                      ORDER BY created_at DESC");
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if there was a successful deletion
$deleted = isset($_GET['deleted']) && $_GET['deleted'] == 1;
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <div class="container mx-auto p-4">
    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">Admin Dashboard</h1>
        <div>
          <span class="mr-4">Welcome, <?php echo htmlspecialchars($admin['username']); ?></span>
          <a href="auth/logout.php?admin=1" class="text-red-500 hover:underline">Logout</a>
        </div>
      </div>
      <a href="feed.php" class="text-blue-500 hover:underline">Back to Site</a>
    </div>

    <?php if ($deleted): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        User has been successfully deleted.
      </div>
    <?php endif; ?>

    <!-- Post Statistics -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
      <h2 class="text-xl font-bold mb-4">Post Statistics</h2>

      <div class="mb-4">
        <a href="?timeframe=week" class="px-4 py-2 bg-blue-500 text-white rounded mr-2 <?php echo $timeframe === 'week' ? 'bg-blue-700' : ''; ?>">Week</a>
        <a href="?timeframe=month" class="px-4 py-2 bg-blue-500 text-white rounded mr-2 <?php echo $timeframe === 'month' ? 'bg-blue-700' : ''; ?>">Month</a>
        <a href="?timeframe=year" class="px-4 py-2 bg-blue-500 text-white rounded <?php echo $timeframe === 'year' ? 'bg-blue-700' : ''; ?>">Year</a>
      </div>

      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Posts Count</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php foreach ($post_statistics as $stat): ?>
            <tr>
              <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($stat['username']); ?></td>
              <td class="px-6 py-4 whitespace-nowrap"><?php echo $stat['post_count']; ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($post_statistics) === 0): ?>
            <tr>
              <td colspan="2" class="px-6 py-4 text-center">No posts in the selected timeframe</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- User Reports -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
      <h2 class="text-xl font-bold mb-4">User Reports</h2>

      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reporter</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reported User</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php foreach ($reports as $report): ?>
            <tr>
              <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($report['reporter_name']); ?></td>
              <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($report['reported_name']); ?></td>
              <td class="px-6 py-4"><?php echo htmlspecialchars($report['reason']); ?></td>
              <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M d, Y g:i a', strtotime($report['created_at'])); ?></td>
              <td class="px-6 py-4 whitespace-nowrap">
                <a href="block_user.php?user_id=<?php echo $report['reported_id']; ?>&days=30" class="text-yellow-600 hover:underline mr-2">Block 30 Days</a>
                <a href="admin_dashboard.php?delete_user=<?php echo $report['reported_id']; ?>" onclick="return confirm('Are you sure you want to delete this user?')" class="text-red-600 hover:underline">Delete User</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($reports) === 0): ?>
            <tr>
              <td colspan="5" class="px-6 py-4 text-center">No reports found</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>

</html>