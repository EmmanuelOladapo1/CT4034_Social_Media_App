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

// Process search query
$search_results = [];
$search_query = '';
$search_type = 'all';

if (isset($_GET['query']) && !empty($_GET['query'])) {
    $search_query = trim($_GET['query']);
    $search_type = isset($_GET['type']) ? $_GET['type'] : 'all';

    try {
        // Search users
        if ($search_type == 'users' || $search_type == 'all') {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username LIKE ? OR email LIKE ? LIMIT 20");
            $stmt->execute(['%' . $search_query . '%', '%' . $search_query . '%']);
            $user_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $search_results['users'] = $user_results;
        }

        // Search posts
        if ($search_type == 'posts' || $search_type == 'all') {
            $stmt = $conn->prepare("SELECT p.*, u.username, u.profile_image FROM posts p
                                   JOIN users u ON p.user_id = u.user_id
                                   WHERE p.content LIKE ? OR p.location_name LIKE ?
                                   ORDER BY p.created_at DESC LIMIT 50");
            $stmt->execute(['%' . $search_query . '%', '%' . $search_query . '%']);
            $post_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $search_results['posts'] = $post_results;
        }
    } catch (PDOException $e) {
        $error = "Search error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Search - SocialNet</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>

<body class="bg-gray-100">
    <!-- Navigation bar -->
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <a href="feed.php" class="text-2xl font-bold">SocialNet</a>

            <div class="flex items-center space-x-4">
                <a href="profile.php" class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-300">
                        <?php if ($current_user['profile_image']): ?>
                            <img src="<?php echo htmlspecialchars($current_user['profile_image']); ?>" class="w-full h-full object-cover" alt="Profile">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span><?php echo htmlspecialchars($current_user['username']); ?></span>
                </a>
                <a href="auth/logout.php" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-4 mt-4">
        <!-- Search Form -->
        <div class="bg-white p-4 rounded-lg shadow mb-4">
            <form action="search.php" method="GET" class="flex flex-col md:flex-row gap-2">
                <input type="text" name="query" value="<?php echo htmlspecialchars($search_query); ?>"
                    class="flex-grow p-2 border rounded" placeholder="Search for users or posts...">

                <select name="type" class="p-2 border rounded">
                    <option value="all" <?php echo $search_type == 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="users" <?php echo $search_type == 'users' ? 'selected' : ''; ?>>Users</option>
                    <option value="posts" <?php echo $search_type == 'posts' ? 'selected' : ''; ?>>Posts</option>
                </select>

                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Search</button>
            </form>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($search_query)): ?>
            <h2 class="text-xl font-bold mb-4">Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h2>

            <?php if ($search_type == 'users' || $search_type == 'all'): ?>
                <!-- User Results -->
                <div class="bg-white p-4 rounded-lg shadow mb-4">
                    <h3 class="text-lg font-bold mb-2">Users</h3>

                    <?php if (!empty($search_results['users'])): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($search_results['users'] as $user): ?>
                                <div class="border rounded p-4 flex items-center">
                                    <div class="w-12 h-12 rounded-full overflow-hidden bg-gray-300 mr-4">
                                        <?php if (!empty($user['profile_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" class="w-full h-full object-cover" alt="Profile">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="font-bold"><?php echo htmlspecialchars($user['username']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No users found matching "<?php echo htmlspecialchars($search_query); ?>"</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($search_type == 'posts' || $search_type == 'all'): ?>
                <!-- Post Results -->
                <div class="bg-white p-4 rounded-lg shadow mb-4">
                    <h3 class="text-lg font-bold mb-2">Posts</h3>

                    <?php if (!empty($search_results['posts'])): ?>
                        <?php foreach ($search_results['posts'] as $post): ?>
                            <div class="border-b last:border-b-0 py-4">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-300 mr-2">
                                        <?php if (!empty($post['profile_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($post['profile_image']); ?>" class="w-full h-full object-cover" alt="Profile">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="font-bold"><?php echo htmlspecialchars($post['username']); ?></p>
                                </div>

                                <p class="mb-2"><?php echo htmlspecialchars($post['content']); ?></p>

                                <?php if ($post['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($post['image_url']); ?>" class="max-w-md mb-2">
                                <?php endif; ?>

                                <?php if ($post['location_name']): ?>
                                    <p class="text-sm text-gray-600">Posted from: <?php echo htmlspecialchars($post['location_name']); ?></p>
                                <?php endif; ?>

                                <p class="text-sm text-gray-500"><?php echo $post['created_at']; ?></p>

                                <a href="feed.php#post-<?php echo $post['post_id']; ?>" class="text-blue-500 hover:underline">View post</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No posts found matching "<?php echo htmlspecialchars($search_query); ?>"</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="bg-white p-8 rounded-lg shadow text-center">
                <p class="text-gray-600">Enter a search term to find users or posts</p>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="feed.php" class="text-blue-500 hover:underline">Back to feed</a>
        </div>
    </div>
</body>
// In the search.php file, update the search functionality to include comments:

if ($search_type == 'posts' || $search_type == 'all') {
// Search posts
$stmt = $conn->prepare("SELECT p.*, u.username, u.profile_image FROM posts p
JOIN users u ON p.user_id = u.user_id
WHERE p.content LIKE ? OR p.location_name LIKE ?
ORDER BY p.created_at DESC LIMIT 50");
$stmt->execute(['%' . $search_query . '%', '%' . $search_query . '%']);
$post_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
$search_results['posts'] = $post_results;

// Add this section to search comments
$stmt = $conn->prepare("SELECT c.*, p.content as post_content, p.post_id,
u.username, u.profile_image
FROM comments c
JOIN users u ON c.user_id = u.user_id
JOIN posts p ON c.post_id = p.post_id
WHERE c.content LIKE ?
ORDER BY c.created_at DESC LIMIT 50");
$stmt->execute(['%' . $search_query . '%']);
$comment_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
$search_results['comments'] = $comment_results;
}
<?php if ($search_type == 'posts' || $search_type == 'all'): ?>
    <!-- Comments Results -->
    <?php if (!empty($search_results['comments'])): ?>
        <div class="bg-white p-4 rounded-lg shadow mb-4">
            <h3 class="text-lg font-bold mb-2">Comments</h3>

            <?php foreach ($search_results['comments'] as $comment): ?>
                <div class="border-b last:border-b-0 py-3">
                    <div class="flex items-center mb-1">
                        <div class="w-6 h-6 rounded-full overflow-hidden bg-gray-300 mr-2">
                            <?php if (!empty($comment['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($comment['profile_image']); ?>" class="w-full h-full object-cover" alt="Profile">
                            <?php else: ?>
                                <!-- Default profile icon -->
                                <div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="font-bold"><?php echo htmlspecialchars($comment['username']); ?></p>
                    </div>

                    <p class="mb-1 pl-8"><?php echo htmlspecialchars($comment['content']); ?></p>
                    <p class="text-xs text-gray-500 pl-8">Comment on post: "<?php echo htmlspecialchars(substr($comment['post_content'], 0, 50)); ?><?php echo strlen($comment['post_content']) > 50 ? '...' : ''; ?>"</p>

                    <a href="feed.php#comment-<?php echo $comment['comment_id']; ?>" class="text-blue-500 hover:underline pl-8">View comment</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

</html>