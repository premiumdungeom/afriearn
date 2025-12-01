<?php
require_once 'config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user = getCurrentUser();
$user_id = $user['id'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message') {
    $message = trim($_POST['message'] ?? '');
    $attachment_url = null;
    
    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/chat/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '_' . $user_id . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $file_path)) {
            $attachment_url = $file_path;
        }
    }
    
    if (empty($message) && !$attachment_url) {
        echo json_encode(['success' => false, 'error' => 'Message or attachment required']);
        exit;
    }
    
    if (sendChatMessage($user_id, 'user', $message, $attachment_url)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }
    
} elseif ($_GET['action'] ?? '' === 'get_messages') {
    $last_id = $_GET['last_id'] ?? '';
    $messages = getUserChatMessages($user_id);
    
    // Filter messages after last_id
    if ($last_id) {
        $found = false;
        $filtered_messages = [];
        foreach ($messages as $msg) {
            if ($found) {
                $filtered_messages[] = $msg;
            }
            if ($msg['id'] === $last_id) {
                $found = true;
            }
        }
        $messages = $filtered_messages;
    }
    
    // Only return admin messages (user already sees their own messages instantly)
    $admin_messages = array_filter($messages, function($msg) {
        return $msg['sender_type'] === 'admin';
    });
    
    echo json_encode([
        'success' => true,
        'messages' => array_values($admin_messages)
    ]);
    
} elseif ($_POST['action'] ?? '' === 'mark_read') {
    markMessagesAsRead($user_id);
    echo json_encode(['success' => true]);
    
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>