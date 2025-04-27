<?php
// messages.php - simplified version
// Purpose: Handles basic messaging functionality (sending/receiving messages only)
session_start();
require_once 'config/database.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

// Get current user information
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
  $receiver_id = $_POST['receiver_id'];
  $content = trim($_POST['message_content']);

  // Validate inputs
  $errors = [];

  if (empty($content)) {
    $errors[] = "Message cannot be empty";
  }

  // Check if receiver exists
  $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
  $stmt->execute([$receiver_id]);
  if ($stmt->rowCount() == 0) {
    $errors[] = "Invalid recipient";
  }

  // Send message if no errors
  if (empty($errors)) {
    try {
      $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, content, created_at)
                             VALUES (?, ?, ?, NOW())");
      $stmt->execute([$_SESSION['user_id'], $receiver_id, $content]);

      $success = "Message sent successfully";
    } catch (PDOException $e) {
      $errors[] = "Error sending message: " . $e->getMessage();
    }
  }
}

// Get conversation partner if provided
$conversation_partner = null;
$conversation_partner_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;

if ($conversation_partner_id) {
  $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
  $stmt->execute([$conversation_partner_id]);
  $conversation_partner = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$conversation_partner) {
    $errors[] = "User not found";
    $conversation_partner_id = null;
  }
}

// Get all users the current user has messaged with
$stmt = $conn->prepare("
  SELECT DISTINCT u.user_id, u.username, u.profile_image,
    (SELECT content FROM messages
     WHERE (sender_id = ? AND receiver_id = u.user_id)
        OR (sender_id = u.user_id AND receiver_id = ?)
     ORDER BY created_at DESC LIMIT 1) as last_message,
    (SELECT created_at FROM messages
     WHERE (sender_id = ? AND receiver_id = u.user_id)
        OR (sender_id = u.user_id AND receiver_id = ?)
     ORDER BY created_at DESC LIMIT 1) as last_message_time
  FROM users u
  JOIN messages m ON (m.sender_id = u.user_id AND m.receiver_id = ?)
                    OR (m.sender_id = ? AND m.receiver_id = u.user_id)
  WHERE u.user_id != ?
  GROUP BY u.user_id
  ORDER BY last_message_time DESC
");
$stmt->execute([
  $_SESSION['user_id'],
  $_SESSION['user_id'],
  $_SESSION['user_id'],
  $_SESSION['user_id'],
  $_SESSION['user_id'],
  $_SESSION['user_id'],
  $_SESSION['user_id']
]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get messages for the selected conversation
$messages = [];
if ($conversation_partner_id) {
  $stmt = $conn->prepare("SELECT m.*,
                           sender.username as sender_username,
                           sender.profile_image as sender_profile_image
                         FROM messages m
                         JOIN users sender ON m.sender_id = sender.user_id
                         WHERE (m.sender_id = ? AND m.receiver_id = ?)
                            OR (m.sender_id = ? AND m.receiver_id = ?)
                         ORDER BY m.created_at ASC");
  $stmt->execute([$_SESSION['user_id'], $conversation_partner_id, $conversation_partner_id, $_SESSION['user_id']]);
  $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Mark messages as read (simplified)
  $stmt = $conn->prepare("UPDATE messages SET is_read = 1
                         WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
  $stmt->execute([$conversation_partner_id, $_SESSION['user_id']]);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Messages</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
  <style>
    .message-container {
      height: calc(100vh - 300px);
      min-height: 300px;
    }

    .messages-list {
      height: calc(100vh - 300px);
      min-height: 300px;
      overflow-y: auto;
    }
  </style>
</head>

<body class="bg-gray-100">
  <div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">Messages</h1>
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

    <?php if (isset($success)): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($success); ?>
      </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row bg-white rounded-lg shadow-md overflow-hidden">
      <!-- Conversations List -->
      <div class="w-full md:w-1/3 border-r border-gray-200">
        <div class="p-4 border-b border-gray-200 bg-gray-50">
          <h2 class="font-bold">Conversations</h2>
        </div>
        <div class="messages-list">
          <?php if (empty($conversations)): ?>
            <div class="p-4 text-gray-500 text-center">
              <p>No conversations yet</p>
              <p class="mt-2 text-sm">Start a new message by visiting a user's profile</p>
            </div>
          <?php else: ?>
            <?php foreach ($conversations as $conversation): ?>
              <a href="messages.php?user_id=<?php echo $conversation['user_id']; ?>"
                class="block p-4 border-b border-gray-100 hover:bg-gray-50 <?php echo ($conversation_partner_id == $conversation['user_id']) ? 'bg-blue-50' : ''; ?>">
                <div class="flex items-start">
                  <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-300 mr-3 flex-shrink-0">
                    <?php if ($conversation['profile_image']): ?>
                      <img src="<?php echo htmlspecialchars($conversation['profile_image']); ?>" class="w-full h-full object-cover" alt="Profile">
                    <?php else: ?>
                      <div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="flex-grow min-w-0">
                    <div class="flex justify-between items-center mb-1">
                      <span class="font-medium truncate"><?php echo htmlspecialchars($conversation['username']); ?></span>
                      <span class="text-xs text-gray-500">
                        <?php echo date('M d', strtotime($conversation['last_message_time'])); ?>
                      </span>
                    </div>
                    <p class="text-gray-600 text-sm truncate">
                      <?php echo htmlspecialchars(substr($conversation['last_message'], 0, 50)); ?>
                      <?php echo strlen($conversation['last_message']) > 50 ? '...' : ''; ?>
                    </p>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Conversation Area -->
      <div class="w-full md:w-2/3 flex flex-col">
        <?php if ($conversation_partner): ?>
          <!-- Conversation Header -->
          <div class="p-4 border-b border-gray-200 bg-gray-50 flex items-center">
            <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-300 mr-3">
              <?php if ($conversation_partner['profile_image']): ?>
                <img src="<?php echo htmlspecialchars($conversation_partner['profile_image']); ?>" class="w-full h-full object-cover" alt="Profile">
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
              <?php endif; ?>
            </div>
            <div>
              <h2 class="font-bold"><?php echo htmlspecialchars($conversation_partner['username']); ?></h2>
              <a href="profile.php?id=<?php echo $conversation_partner['user_id']; ?>" class="text-blue-500 text-sm hover:underline">View Profile</a>
            </div>
          </div>

          <!-- Messages -->
          <div class="flex-grow p-4 overflow-y-auto message-container" id="messageContainer">
            <?php if (empty($messages)): ?>
              <div class="text-center text-gray-500 py-8">
                <p>No messages yet</p>
                <p class="text-sm">Start a conversation below</p>
              </div>
            <?php else: ?>
              <?php foreach ($messages as $message): ?>
                <?php $is_own_message = ($message['sender_id'] == $_SESSION['user_id']); ?>
                <div class="mb-4 flex <?php echo $is_own_message ? 'justify-end' : 'justify-start'; ?>">
                  <?php if (!$is_own_message): ?>
                    <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-300 mr-2 flex-shrink-0">
                      <?php if ($message['sender_profile_image']): ?>
                        <img src="<?php echo htmlspecialchars($message['sender_profile_image']); ?>" class="w-full h-full object-cover" alt="Profile">
                      <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-gray-500 bg-white">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                          </svg>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                  <div class="max-w-xs md:max-w-md">
                    <div class="px-4 py-2 rounded-lg <?php echo $is_own_message ? 'bg-blue-500 text-white' : 'bg-gray-200'; ?>">
                      <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                    </div>
                    <div class="text-xs text-gray-500 mt-1 <?php echo $is_own_message ? 'text-right' : ''; ?>">
                      <?php echo date('M d, g:i a', strtotime($message['created_at'])); ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Message Input -->
          <form method="POST" class="p-4 border-t border-gray-200 bg-white">
            <input type="hidden" name="receiver_id" value="<?php echo $conversation_partner['user_id']; ?>">
            <div class="flex">
              <textarea name="message_content" rows="2" placeholder="Type a message..."
                class="w-full border rounded-l-lg p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
              <button type="submit" name="send_message" class="bg-blue-500 text-white px-4 py-2 rounded-r-lg hover:bg-blue-600">
                Send
              </button>
            </div>
          </form>
        <?php else: ?>
          <!-- No conversation selected -->
          <div class="flex-grow flex items-center justify-center p-8 text-center text-gray-500">
            <div>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
              </svg>
              <h3 class="text-xl font-medium mb-2">Your Messages</h3>
              <p class="mb-4">Select a conversation or start a new one from a user's profile</p>
              <a href="friends.php" class="inline-block px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                Find Users
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    // Scroll to bottom of message container when page loads
    document.addEventListener('DOMContentLoaded', function() {
      const messageContainer = document.getElementById('messageContainer');
      if (messageContainer) {
        messageContainer.scrollTop = messageContainer.scrollHeight;
      }
    });
  </script>
</body>

</html>