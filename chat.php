<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$user_id = $user['id'];
$messages = getUserChatMessages($user_id);

// Mark messages as read when user opens chat
markMessagesAsRead($user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Chat • Ninja Hope</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --dark-green: #0a5c36;
            --dark-purple: #4a235a;
            --dim-black: #1a1a1a;
            --dark-gray: #2d2d2d;
            --light-gray: #444;
            --text-light: #e0e0e0;
            --text-muted: #aaa;
            --accent-green: #1e8449;
            --accent-purple: #6c3483;
            --user-message: #1e8449;
            --support-message: #4a235a;
            --ai-message: #2d3748;
            --user-bubble: #1e8449;
            --admin-bubble: #4a235a;
            --ai-bubble: #374151;
        }
        
        * { 
            margin:0; 
            padding:0; 
            box-sizing:border-box; 
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--dim-black);
            height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--text-light);
            padding-bottom: 70px;
        }
        
        .header {
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            padding: 1rem;
            text-align: center;
            color: white;
            font-weight: 600;
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            flex-shrink: 0;
        }
        
        .back-btn {
            position: absolute; 
            left: 0.8rem; 
            top: 50%; 
            transform: translateY(-50%);
            color: white; 
            font-size: 1.2rem; 
            text-decoration: none;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.2);
            border-radius: 50%;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,.3);
            transform: translateY(-50%) scale(1.1);
        }
        
        .header h1 {
            font-size: 1.2rem;
            padding: 0 2rem;
        }
        
        .online-status {
            text-align: center;
            padding: 0.6rem;
            font-size: 0.85rem;
            background: rgba(30, 132, 73, 0.2);
            color: var(--accent-green);
            border-bottom: 1px solid var(--light-gray);
            flex-shrink: 0;
        }
        
        .online-status i {
            font-size: 0.6rem;
            margin-right: 0.5rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .chat-container {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            background: var(--dim-black);
            gap: 0.8rem;
        }
        
        .chat-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .chat-container::-webkit-scrollbar-track {
            background: var(--dark-gray);
        }
        
        .chat-container::-webkit-scrollbar-thumb {
            background: var(--light-gray);
            border-radius: 3px;
        }
        
        .input-area {
            display: flex;
            padding: 0.8rem;
            background: var(--dark-gray);
            border-top: 1px solid var(--light-gray);
            gap: 0.5rem;
            position: fixed;
            bottom: 70px;
            left: 0;
            right: 0;
            z-index: 99;
        }
        
        .input-area input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--light-gray);
            border-radius: 25px;
            background: var(--dim-black);
            color: var(--text-light);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .input-area input:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 0 2px rgba(30, 132, 73, 0.2);
        }
        
        .input-area input::placeholder { 
            color: var(--text-muted); 
        }
        
        .input-area button {
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white;
            border: none;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .input-area button:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .input-area button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .file-upload {
            background: var(--light-gray);
            color: white;
            border: none;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .file-upload:hover {
            background: var(--accent-green);
        }
        
        .file-upload input {
            display: none;
        }
        
        .no-messages {
            text-align: center;
            color: var(--text-muted);
            font-style: italic;
            padding: 2rem 1rem;
            background: var(--dark-gray);
            border-radius: 12px;
            margin: 1rem;
        }
        
        /* Message Styles - Improved Visibility */
        .message {
            margin: 0.5rem 0;
            display: flex;
            flex-direction: column;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .user-message {
            align-items: flex-end;
        }
        
        .admin-message, .ai-message {
            align-items: flex-start;
        }
        
        .message-bubble {
            max-width: 85%;
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            word-wrap: break-word;
            line-height: 1.4;
        }
        
        .user-message .message-bubble {
            background: var(--user-bubble);
            color: white;
            border-bottom-right-radius: 6px;
        }
        
        .admin-message .message-bubble {
            background: var(--admin-bubble);
            color: white;
            border-bottom-left-radius: 6px;
        }
        
        .ai-message .message-bubble {
            background: var(--ai-bubble);
            color: var(--text-light);
            border-bottom-left-radius: 6px;
            border: 1px solid var(--light-gray);
        }
        
        .message-sender {
            font-size: 0.75rem;
            opacity: 0.9;
            margin-bottom: 6px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .message-content {
            line-height: 1.5;
            font-size: 0.95rem;
        }
        
        .message-time {
            font-size: 0.7rem;
            opacity: 0.8;
            margin-top: 6px;
            text-align: right;
        }
        
        .admin-message .message-time,
        .ai-message .message-time {
            text-align: left;
        }
        
        .attachment {
            margin-top: 8px;
            padding: 8px 12px;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            font-size: 0.8rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .attachment a {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .attachment a:hover {
            text-decoration: underline;
        }
        
        /* Typing Indicator */
        .typing-indicator {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            background: var(--ai-bubble);
            color: var(--text-light);
            border-radius: 18px;
            max-width: 120px;
            border-bottom-left-radius: 6px;
            margin: 0.5rem 0;
            border: 1px solid var(--light-gray);
        }
        
        .typing-dots {
            display: flex;
            margin-left: 8px;
        }
        
        .typing-dot {
            width: 6px;
            height: 6px;
            background: var(--text-light);
            border-radius: 50%;
            margin: 0 2px;
            animation: typing 1.4s infinite ease-in-out;
        }
        
        .typing-dot:nth-child(1) { animation-delay: 0s; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-5px); opacity: 1; }
        }
        
        .unread-badge {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: -5px;
            right: -5px;
        }
        
        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0; 
            left: 0; 
            right: 0;
            background: var(--dark-gray);
            display: flex;
            justify-content: space-around;
            padding: 0.6rem 0;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.4);
            border-top: 1px solid var(--light-gray);
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
            overflow: hidden;
            z-index: 100;
            height: 70px;
        }
        
        .nav-item {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.7rem;
            text-decoration: none;
            flex: 1;
            padding: 0.4rem 0;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .nav-item i { 
            font-size: 1.2rem; 
            margin-bottom: 0.2rem; 
            transition: all 0.3s ease;
        }
        
        .nav-item.active {
            color: var(--accent-green);
            font-weight: 600;
        }
        
        .nav-item.active i {
            transform: scale(1.1);
        }
        
        .nav-item::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--accent-green);
            border-radius: 2px;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .nav-item.active::after {
            width: 20px;
        }

        /* Spacer to prevent messages from being hidden behind input */
        .messages-spacer {
            height: 60px;
            flex-shrink: 0;
        }

        /* Message status indicators */
        .message-status {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            margin-top: 4px;
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .status-delivered { color: var(--accent-green); }
        .status-read { color: var(--accent-green); }
        .status-pending { color: var(--text-muted); }

        /* Responsive adjustments */
        @media (max-width: 360px) {
            .chat-container {
                padding: 0.8rem;
                gap: 0.6rem;
            }
            
            .message-bubble {
                max-width: 90%;
                padding: 10px 14px;
            }
            
            .input-area {
                padding: 0.6rem;
            }
            
            .input-area input {
                padding: 10px 14px;
                font-size: 0.9rem;
            }
            
            .input-area button,
            .file-upload {
                width: 42px;
                height: 42px;
            }
            
            .header h1 {
                font-size: 1.1rem;
            }
        }

        /* Improved message grouping */
        .message-group {
            margin: 0.3rem 0;
        }

        .consecutive-message {
            margin-top: 0.2rem;
        }

        .consecutive-message .message-sender {
            display: none;
        }
    </style>
</head>
<body>

    <div class="header">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1><i class="fas fa-headset"></i> Support Chat</h1>
    </div>

    <div class="online-status">
        <i class="fas fa-circle"></i> 
        Support • We reply within minutes
    </div>

    <div class="chat-container" id="chatBox">
        <?php if (empty($messages)): ?>
            <div class="no-messages">
                <i class="fas fa-comments" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                How can we help you today?<br>
                <span style="font-size: 0.8rem;">Our support team is here to assist you</span>
            </div>
        <?php else: ?>
            <?php 
            $lastSender = null;
            foreach ($messages as $message): 
                $isConsecutive = $lastSender === $message['sender_type'];
                $lastSender = $message['sender_type'];
            ?>
                <div class="message <?php echo $message['sender_type'] === 'user' ? 'user-message' : ($message['sender_type'] === 'admin' ? 'admin-message' : 'ai-message'); ?> <?php echo $isConsecutive ? 'consecutive-message' : ''; ?>">
                    <div class="message-bubble">
                        <?php if (!$isConsecutive): ?>
                            <div class="message-sender">
                                <i class="fas fa-<?php echo $message['sender_type'] === 'user' ? 'user' : ($message['sender_type'] === 'admin' ? 'headset' : 'robot'); ?>"></i>
                                <?php 
                                if ($message['sender_type'] === 'user') echo 'You';
                                elseif ($message['sender_type'] === 'admin') echo 'Support Team';
                                else echo 'AI Assistant';
                                ?>
                            </div>
                        <?php endif; ?>
                        <div class="message-content"><?php echo htmlspecialchars($message['message']); ?></div>
                        <?php if ($message['attachment_url']): ?>
                            <div class="attachment">
                                <a href="<?php echo htmlspecialchars($message['attachment_url']); ?>" target="_blank">
                                    <i class="fas fa-paperclip"></i>
                                    View Attachment
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="message-time">
                            <?php echo date('g:i A', strtotime($message['created_at'])); ?>
                            <?php if ($message['sender_type'] === 'user'): ?>
                                <div class="message-status status-delivered">
                                    <i class="fas fa-check"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <!-- Spacer to prevent last message from being hidden -->
        <div class="messages-spacer"></div>
    </div>

    <div class="input-area">
        <label class="file-upload" for="fileInput" title="Attach file">
            <i class="fas fa-paperclip"></i>
            <input type="file" id="fileInput" accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
        </label>
        <form id="chatForm" style="width:100%;display:flex;gap:0.5rem;">
            <input type="text" id="messageInput" placeholder="Type your message..." autocomplete="off" required>
            <button type="submit" id="sendButton" title="Send message">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item">
            <i class="fas fa-home"></i>
            Home
        </a>
        <a href="auth.php" class="nav-item">
            <i class="fas fa-shield-alt"></i>
            Auth
        </a>
        <a href="tasks.php" class="nav-item">
            <i class="fas fa-gift"></i>
            Tasks
        </a>
        <a href="agent.php" class="nav-item">
            <i class="fas fa-user-friends"></i>
            Agent
        </a>
        <a href="chat.php" class="nav-item active">
            <i class="fas fa-comments"></i>
            Support
        </a>
    </div>

    <script>
        const chatBox = document.getElementById('chatBox');
        const form = document.getElementById('chatForm');
        const input = document.getElementById('messageInput');
        const sendButton = document.getElementById('sendButton');
        const fileInput = document.getElementById('fileInput');
        
        let isSending = false;
        let lastMessageId = '<?php echo end($messages)['id'] ?? ''; ?>';

        // Auto-scroll to bottom
        function scrollToBottom() {
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        // Add message to chat
        function addMessage(sender, message, attachment = null, messageId = null) {
            const noMsg = chatBox.querySelector('.no-messages');
            if (noMsg) noMsg.remove();
            
            const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            const senderClass = sender === 'user' ? 'user-message' : (sender === 'admin' ? 'admin-message' : 'ai-message');
            const senderName = sender === 'user' ? 'You' : (sender === 'admin' ? 'Support Team' : 'AI Assistant');
            const senderIcon = sender === 'user' ? 'user' : (sender === 'admin' ? 'headset' : 'robot');
            
            let attachmentHtml = '';
            if (attachment) {
                attachmentHtml = `
                    <div class="attachment">
                        <a href="${attachment}" target="_blank">
                            <i class="fas fa-paperclip"></i>
                            View Attachment
                        </a>
                    </div>
                `;
            }
            
            // Check if this is consecutive message from same sender
            const lastMessage = chatBox.querySelector('.message:last-child');
            const isConsecutive = lastMessage && lastMessage.classList.contains(senderClass);
            
            const html = `
                <div class="message ${senderClass} ${isConsecutive ? 'consecutive-message' : ''}" data-id="${messageId || Date.now()}">
                    <div class="message-bubble">
                        ${!isConsecutive ? `
                            <div class="message-sender">
                                <i class="fas fa-${senderIcon}"></i>
                                ${senderName}
                            </div>
                        ` : ''}
                        <div class="message-content">${message.replace(/\n/g, '<br>')}</div>
                        ${attachmentHtml}
                        <div class="message-time">
                            ${time}
                            ${sender === 'user' ? `
                                <div class="message-status status-delivered">
                                    <i class="fas fa-check"></i>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>`;
                
            // Insert before the spacer
            const spacer = chatBox.querySelector('.messages-spacer');
            if (spacer) {
                spacer.insertAdjacentHTML('beforebegin', html);
            } else {
                chatBox.insertAdjacentHTML('beforeend', html);
            }
            scrollToBottom();
        }

        // Show typing indicator
        function showTypingIndicator() {
            const typingHtml = `
                <div class="message ai-message" id="typingIndicator">
                    <div class="message-bubble">
                        <div class="typing-indicator">
                            <span>AI is typing</span>
                            <div class="typing-dots">
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                            </div>
                        </div>
                    </div>
                </div>`;
                
            const spacer = chatBox.querySelector('.messages-spacer');
            if (spacer) {
                spacer.insertAdjacentHTML('beforebegin', typingHtml);
            } else {
                chatBox.insertAdjacentHTML('beforeend', typingHtml);
            }
            scrollToBottom();
        }

        // Remove typing indicator
        function removeTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            if (indicator) indicator.remove();
        }

        // Send message
        async function sendMessage(message, attachment = null) {
            if (isSending) return;
            isSending = true;
            sendButton.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('message', message);
                if (attachment) {
                    formData.append('attachment', attachment);
                }
                
                const response = await fetch('chat_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Add user message
                    addMessage('user', message, attachment);
                    
                    // Show AI response after delay
                    showTypingIndicator();
                    
                    setTimeout(() => {
                        removeTypingIndicator();
                        addMessage('ai', "Thank you for your message! Our support team has been notified and will respond to you shortly. Please wait for an admin to get back to you.");
                    }, 2000);
                    
                    input.value = '';
                    fileInput.value = '';
                    input.focus();
                } else {
                    alert('Failed to send message: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Send message error:', error);
                alert('Failed to send message. Please try again.');
            } finally {
                isSending = false;
                sendButton.disabled = false;
            }
        }

        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = input.value.trim();
            if (!message && !fileInput.files[0]) {
                alert('Please type a message or attach a file');
                return;
            }
            
            const file = fileInput.files[0];
            sendMessage(message, file);
        });

        // File upload
        fileInput.addEventListener('change', function() {
            if (this.files[0]) {
                // Auto-send if there's no text message
                if (!input.value.trim()) {
                    sendMessage('', this.files[0]);
                } else {
                    input.focus();
                }
            }
        });

        // Load new messages
        async function loadNewMessages() {
            try {
                const response = await fetch(`chat_ajax.php?action=get_messages&last_id=${lastMessageId}`);
                const result = await response.json();
                
                if (result.success && result.messages.length > 0) {
                    result.messages.forEach(msg => {
                        if (msg.sender_type === 'admin') {
                            addMessage('admin', msg.message, msg.attachment_url, msg.id);
                        }
                        lastMessageId = msg.id;
                    });
                    
                    // Mark as read
                    if (result.messages.some(msg => msg.sender_type === 'admin')) {
                        await fetch('chat_ajax.php?action=mark_read', { method: 'POST' });
                    }
                }
            } catch (error) {
                console.error('Load messages error:', error);
            }
        }

        // Auto-focus on input when page loads
        window.addEventListener('load', function() {
            input.focus();
            scrollToBottom();
        });

        // Poll for new messages every 3 seconds
        setInterval(loadNewMessages, 3000);
        
        // Initial scroll to bottom
        scrollToBottom();
    </script>
</body>
</html>