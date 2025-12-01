const TelegramBot = require('node-telegram-bot-api');
const axios = require('axios');

// Configuration
const TG_TOKEN = '8570551754:AAG57MCBZmk76LOPW0FRRjdBOsdEUZUlQak';
const SERVICE_ROLE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
const SUPABASE_URL = 'https://hqnjhoydaszzamuqvgiq.supabase.co';

const bot = new TelegramBot(TG_TOKEN, { polling: true });

// Supabase API client
const supabase = axios.create({
    baseURL: SUPABASE_URL,
    headers: {
        'apikey': SERVICE_ROLE_KEY,
        'Authorization': `Bearer ${SERVICE_ROLE_KEY}`,
        'Content-Type': 'application/json'
    }
});

// Store active chats
const activeChats = new Map();

// Helper function to mark messages as read
async function markMessagesAsRead(userId) {
    try {
        console.log(`📖 Marking messages as read for user: ${userId}`);
        
        // Mark all messages as read
        const response = await supabase.patch(`/rest/v1/chat_messages?user_id=eq.${userId}`, {
            is_read: true
        });
        
        // Reset unread count in session
        await supabase.patch(`/rest/v1/chat_sessions?user_id=eq.${userId}`, {
            unread_count: 0
        });
        
        console.log('✅ Messages marked as read and unread count reset');
        return true;
    } catch (error) {
        console.error('❌ Error marking messages as read:', error.response?.data || error.message);
        return false;
    }
}

// Debug function
async function debugChatSystem() {
    try {
        console.log("=== DEBUG CHAT SYSTEM ===");
        
        // Check if chat_messages table has data
        const messagesResponse = await supabase.get('/rest/v1/chat_messages?select=count');
        console.log("Total messages in database:", messagesResponse.data);
        
        // Check if chat_sessions table has data  
        const sessionsResponse = await supabase.get('/rest/v1/chat_sessions?select=count');
        console.log("Total sessions in database:", sessionsResponse.data);
        
        // Check pending chats specifically
        const pendingResponse = await supabase.get('/rest/v1/chat_sessions?unread_count=gt.0');
        console.log("Pending chats:", pendingResponse.data);
        
        // Check users table
        const usersResponse = await supabase.get('/rest/v1/users?select=count');
        console.log("Total users in database:", usersResponse.data);
        
        console.log("=== END DEBUG ===");
    } catch (error) {
        console.error("Debug error:", error);
    }
}

// Enhanced debug function with more details
async function detailedDebug() {
    try {
        console.log("=== DETAILED DEBUG ===");
        
        // Get sample data from each table
        const messages = await supabase.get('/rest/v1/chat_messages?limit=5&order=created_at.desc');
        console.log("Recent messages:", messages.data);
        
        const sessions = await supabase.get('/rest/v1/chat_sessions?limit=5&order=last_message_at.desc');
        console.log("Recent sessions:", sessions.data);
        
        const users = await supabase.get('/rest/v1/users?limit=3');
        console.log("Sample users:", users.data);
        
        console.log("=== END DETAILED DEBUG ===");
    } catch (error) {
        console.error("Detailed debug error:", error.response?.data || error.message);
    }
}

// Helper function to get user info
async function getUserInfo(userId) {
    try {
        console.log(`🔍 Fetching user info for ID: ${userId}`);
        const response = await supabase.get(`/rest/v1/users?id=eq.${userId}&select=name,username,email`);
        console.log("User info response:", response.data);
        return response.data[0] || null;
    } catch (error) {
        console.error('❌ Error getting user info:', error.response?.data || error.message);
        return null;
    }
}

// Face verification helper functions
async function getPendingVerifications() {
    try {
        console.log('🔍 Fetching pending face verifications...');
        const response = await supabase.get('/rest/v1/face_verifications?status=eq.pending&order=created_at.asc');
        console.log(`📸 Found ${response.data?.length || 0} pending verifications`);
        return response.data || [];
    } catch (error) {
        console.error('❌ Error getting pending verifications:', error.response?.data || error.message);
        return [];
    }
}

// Update the approveFaceVerification function in bot.js
async function approveFaceVerification(verificationId, adminNotes = '') {
    try {
        console.log(`✅ Approving face verification: ${verificationId}`);
        
        // Get verification details first to get storage path and user_id
        const verificationResponse = await supabase.get(`/rest/v1/face_verifications?id=eq.${verificationId}`);
        const verification = verificationResponse.data?.[0];
        
        if (!verification) {
            return { success: false, error: 'Verification not found' };
        }
        
        // Update verification status
        const response = await supabase.patch(`/rest/v1/face_verifications?id=eq.${verificationId}`, {
            status: 'approved',
            admin_notes: adminNotes,
            updated_at: new Date().toISOString()
        });
        
        if (response.status === 200) {
            // Update user as verified - use service role for this
            const userUpdateResponse = await supabase.patch(`/rest/v1/users?id=eq.${verification.user_id}`, {
                face_verified: true,
                verification_code: null
            });
            
            console.log('User update response:', userUpdateResponse.status);
            
            // Delete image from storage
            if (verification.storage_path) {
                await deleteImageFromStorage(verification.storage_path);
            }
            
            console.log('✅ Face verification approved and user status updated');
            return { success: true };
        } else {
            return { success: false, error: 'Failed to update verification' };
        }
    } catch (error) {
        console.error('❌ Error approving face verification:', error.response?.data || error.message);
        return { success: false, error: error.message };
    }
}

// Helper function to get multiple accounts count
async function getMultipleAccountsCount(userId) {
    try {
        const service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImhsbmhhdmdlaWJuZ3B3Y2hqenp2Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDIyODQ3OSwiZXhwIjoyMDc5ODA0NDc5fQ.7hUyGx3xu_gvL8JUe9QzMpIlKepF5Z1gHKA4Mq400Ao';
        
        // Get user's IP associations
        const userIpsResponse = await supabase.get(`/rest/v1/ip_associations?user_id=eq.${userId}&select=ip_address`);
        const userIps = userIpsResponse.data || [];
        
        if (userIps.length === 0) {
            return 0;
        }
        
        const uniqueUsers = new Set();
        
        for (const ipAssoc of userIps) {
            const ip = ipAssoc.ip_address;
            
            // Get all users from this IP
            const ipUsersResponse = await supabase.get(`/rest/v1/ip_associations?ip_address=eq.${ip}&select=user_id`);
            const ipUsers = ipUsersResponse.data || [];
            
            for (const user of ipUsers) {
                if (user.user_id !== userId) {
                    uniqueUsers.add(user.user_id);
                }
            }
        }
        
        return uniqueUsers.size;
    } catch (error) {
        console.error('Get multiple accounts count error:', error);
        return 0;
    }
}

async function rejectFaceVerification(verificationId, rejectionReason, adminNotes = '') {
    try {
        console.log(`❌ Rejecting face verification: ${verificationId}`);
        
        // Get verification details first to get storage path
        const verificationResponse = await supabase.get(`/rest/v1/face_verifications?id=eq.${verificationId}`);
        const verification = verificationResponse.data?.[0];
        
        if (!verification) {
            return { success: false, error: 'Verification not found' };
        }
        
        const response = await supabase.patch(`/rest/v1/face_verifications?id=eq.${verificationId}`, {
            status: 'rejected',
            rejection_reason: rejectionReason,
            admin_notes: adminNotes,
            updated_at: new Date().toISOString()
        });
        
        if (response.status === 200) {
            // Delete image from storage
            if (verification.storage_path) {
                await deleteImageFromStorage(verification.storage_path);
            }
            
            console.log('✅ Face verification rejected and image deleted');
            return { success: true };
        } else {
            return { success: false, error: 'Failed to update verification' };
        }
    } catch (error) {
        console.error('❌ Error rejecting face verification:', error.response?.data || error.message);
        return { success: false, error: error.message };
    }
}

async function deleteImageFromStorage(storagePath) {
    try {
        const response = await supabase.delete(`/storage/v1/object/${storagePath}`);
        console.log('🗑️ Image deleted from storage:', storagePath);
        return true;
    } catch (error) {
        console.error('❌ Error deleting image from storage:', error);
        return false;
    }
}

// Keep the approve/reject commands the same (they already call the updated functions)

// Helper function to get pending chats
async function getPendingChats() {
    try {
        console.log('🔍 Fetching pending chats...');
        const response = await supabase.get('/rest/v1/chat_sessions?unread_count=gt.0&order=last_message_at.desc');
        console.log(`📋 Found ${response.data?.length || 0} pending chats`);
        return response.data || [];
    } catch (error) {
        console.error('❌ Error getting pending chats:', error.response?.data || error.message);
        return [];
    }
}

// Helper function to get chat messages
async function getChatMessages(userId) {
    try {
        console.log(`🔍 Fetching messages for user: ${userId}`);
        const response = await supabase.get(`/rest/v1/chat_messages?user_id=eq.${userId}&order=created_at.asc`);
        console.log(`💬 Found ${response.data?.length || 0} messages for user ${userId}`);
        return response.data || [];
    } catch (error) {
        console.error('❌ Error getting chat messages:', error.response?.data || error.message);
        return [];
    }
}

// Helper function to send message as admin
async function sendAdminMessage(userId, message) {
    try {
        console.log(`📤 Sending admin message to user ${userId}:`, message);
        
        const response = await supabase.post('/rest/v1/chat_messages', {
            user_id: userId,
            sender_type: 'admin',
            message: message,
            is_read: false
        });
        
        console.log('✅ Message sent successfully:', response.data);
        
        // Get current session to increment unread_count
        const sessionResponse = await supabase.get(`/rest/v1/chat_sessions?user_id=eq.${userId}`);
        const currentSession = sessionResponse.data?.[0];
        const newUnreadCount = (currentSession?.unread_count || 0) + 1;
        
        // Update session
        const updateResponse = await supabase.patch(`/rest/v1/chat_sessions?user_id=eq.${userId}`, {
            last_message_at: new Date().toISOString(),
            unread_count: newUnreadCount,
            status: 'active'
        });
        
        console.log('✅ Session updated successfully. New unread count:', newUnreadCount);
        return true;
    } catch (error) {
        console.error('❌ Error sending admin message:', error.response?.data || error.message);
        return false;
    }
}

// Start command
bot.onText(/\/start/, async (msg) => {
    const chatId = msg.chat.id;
    console.log(`🚀 Start command from chat: ${chatId}`);
    
    const welcomeMessage = `<b>🤖 Ninja Hope Admin Bot</b>

<b>Available Commands:</b>
/pending - View pending chats
/chat &lt;user_id&gt; - Open chat with user
/reply &lt;user_id&gt; &lt;message&gt; - Reply to user
/debug - Run system diagnostics
/help - Show this help

<b>Quick Stats:</b>
Use /pending to see users waiting for support.`;

    bot.sendMessage(chatId, welcomeMessage, { parse_mode: 'HTML' });
});

// Debug command
bot.onText(/\/debug/, async (msg) => {
    const chatId = msg.chat.id;
    console.log(`🐛 Debug command from chat: ${chatId}`);
    
    try {
        await bot.sendMessage(chatId, '🔄 Running system diagnostics...');
        
        let debugMessage = '<b>🐛 System Debug Info</b>\n\n';
        
        // Check pending chats
        const pendingChats = await getPendingChats();
        debugMessage += `<b>📋 Pending Chats:</b> ${pendingChats.length}\n`;
        
        // Check active chats
        debugMessage += `<b>💬 Active Chats:</b> ${activeChats.size}\n`;
        
        // Get some stats
        const messagesResponse = await supabase.get('/rest/v1/chat_messages?select=count');
        const sessionsResponse = await supabase.get('/rest/v1/chat_sessions?select=count');
        const usersResponse = await supabase.get('/rest/v1/users?select=count');
        
        debugMessage += `<b>💾 Database Stats:</b>\n`;
        debugMessage += `  Messages: ${messagesResponse.data?.[0]?.count || 0}\n`;
        debugMessage += `  Sessions: ${sessionsResponse.data?.[0]?.count || 0}\n`;
        debugMessage += `  Users: ${usersResponse.data?.[0]?.count || 0}\n\n`;
        
        debugMessage += `<b>✅ System Status:</b> Operational\n`;
        debugMessage += `<b>🕒 Last Check:</b> ${new Date().toLocaleString()}`;
        
        await bot.sendMessage(chatId, debugMessage, { parse_mode: 'HTML' });
        
    } catch (error) {
        console.error('Debug command error:', error);
        await bot.sendMessage(chatId, '❌ Error running diagnostics.');
    }
});

// Pending chats command
bot.onText(/\/pending/, async (msg) => {
    const chatId = msg.chat.id;
    console.log(`📋 Pending command from chat: ${chatId}`);
    
    try {
        const pendingChats = await getPendingChats();
        
        if (pendingChats.length === 0) {
            await bot.sendMessage(chatId, '✅ No pending chats at the moment.');
            return;
        }
        
        let message = `<b>📋 Pending Chats (${pendingChats.length})</b>\n\n`;
        
        for (const chat of pendingChats) {
            const userInfo = await getUserInfo(chat.user_id);
            const username = userInfo?.username || 'Unknown User';
            const name = userInfo?.name || 'No Name';
            const timeAgo = new Date(chat.last_message_at).toLocaleString();
            
            message += `<b>👤 ${name}</b> (@${username})\n`;
            message += `<code>ID: ${chat.user_id}</code>\n`;
            message += `💬 ${chat.unread_count} unread\n`;
            message += `⏰ ${timeAgo}\n`;
            message += `<code>/chat_${chat.user_id}</code>\n`;
            message += `<code>/reply_${chat.user_id} your_message</code>\n\n`;
        }
        
        await bot.sendMessage(chatId, message, { 
            parse_mode: 'HTML',
            disable_web_page_preview: true 
        });
    } catch (error) {
        console.error('Error in pending command:', error);
        await bot.sendMessage(chatId, '❌ Error fetching pending chats.');
    }
});

// Chat command
bot.onText(/\/chat_(.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const userId = match[1];
    console.log(`💬 Chat command for user: ${userId} from admin: ${chatId}`);
    
    try {
        const userInfo = await getUserInfo(userId);
        if (!userInfo) {
            await bot.sendMessage(chatId, '❌ User not found.');
            return;
        }
        
        const messages = await getChatMessages(userId);
        
        let chatHistory = `<b>💬 Chat with ${userInfo.name}</b> (@${userInfo.username})\n\n`;
        
        if (messages.length === 0) {
            chatHistory += 'No messages yet.';
        } else {
            messages.forEach(msg => {
                const time = new Date(msg.created_at).toLocaleTimeString();
                const sender = msg.sender_type === 'user' ? '👤 User' : '🤖 Admin';
                const escapedMessage = msg.message.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                chatHistory += `<b>${sender}</b> (${time}):\n${escapedMessage}\n\n`;
            });
        }
        
        // Store active chat
        activeChats.set(chatId, userId);
        console.log(`✅ Active chat started: admin ${chatId} -> user ${userId}`);
        
        // MARK MESSAGES AS READ when admin opens chat
        await markMessagesAsRead(userId);
        
        await bot.sendMessage(chatId, chatHistory, { 
            parse_mode: 'HTML',
            disable_web_page_preview: true 
        });
        
        // Send instructions
        const instructions = `<b>💡 Now chatting with ${userInfo.name}</b>\n\nJust type your message to reply, or use:\n/end - End this chat\n/pending - Back to pending chats\n\nYour next messages will be sent to the user.`;
        
        await bot.sendMessage(chatId, instructions, { 
            parse_mode: 'HTML' 
        });
    } catch (error) {
        console.error('Error in chat command:', error);
        await bot.sendMessage(chatId, '❌ Error opening chat.');
    }
});

// Face verification commands
// Updated Face verification commands with image display
bot.onText(/\/face_pending/, async (msg) => {
    const chatId = msg.chat.id;
    console.log(`📸 Face pending command from chat: ${chatId}`);
    
    try {
        const pendingVerifications = await getPendingVerifications();
        
        if (pendingVerifications.length === 0) {
            await bot.sendMessage(chatId, '✅ No pending face verifications at the moment.');
            return;
        }
        
        for (const verification of pendingVerifications) {
            const userInfo = await getUserInfo(verification.user_id);
            const username = userInfo?.username || 'Unknown User';
            const name = userInfo?.name || 'No Name';
            const timeAgo = new Date(verification.created_at).toLocaleString();
            
            // Create message with image and details
            let message = `<b>📸 Face Verification Request</b>\n\n`;
            message += `<b>👤 User:</b> ${name} (@${username})\n`;
            message += `<b>🆔 Code:</b> <code>${verification.verification_code}</code>\n`;
            message += `<b>📅 Submitted:</b> ${timeAgo}\n\n`;
            message += `<b>⚡ Quick Actions:</b>\n`;
            message += `<code>/face_approve_${verification.id}</code>\n`;
            message += `<code>/face_reject_${verification.id} reason</code>\n\n`;
            message += `<i>Review the image below and use the commands above.</i>`;
            
            // Send the image first
            if (verification.image_url) {
                try {
                    await bot.sendPhoto(chatId, verification.image_url, {
                        caption: message,
                        parse_mode: 'HTML'
                    });
                } catch (photoError) {
                    // If photo fails, send message with link
                    await bot.sendMessage(chatId, 
                        `${message}\n\n📷 <a href="${verification.image_url}">View Face Image</a>`, 
                        { parse_mode: 'HTML' }
                    );
                }
            } else {
                await bot.sendMessage(chatId, message + '\n\n❌ No image available', { 
                    parse_mode: 'HTML' 
                });
            }
            
            // Add small delay between multiple verifications
            if (pendingVerifications.length > 1) {
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
        }
    } catch (error) {
        console.error('Error in face_pending command:', error);
        await bot.sendMessage(chatId, '❌ Error fetching pending verifications.');
    }
});

bot.onText(/\/face_approve_(.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const verificationId = match[1];
    
    console.log(`✅ Face approve command: ${verificationId}`);
    
    try {
        const success = await approveFaceVerification(verificationId, 'Approved via Telegram bot');
        
        if (success) {
            await bot.sendMessage(chatId, `✅ Face verification approved successfully!`);
        } else {
            await bot.sendMessage(chatId, '❌ Failed to approve verification.');
        }
    } catch (error) {
        console.error('Error in face_approve command:', error);
        await bot.sendMessage(chatId, '❌ Error approving verification.');
    }
});

bot.onText(/\/face_reject_(.+) (.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const verificationId = match[1];
    const rejectionReason = match[2];
    
    console.log(`❌ Face reject command: ${verificationId}`);
    
    try {
        const success = await rejectFaceVerification(verificationId, rejectionReason, 'Rejected via Telegram bot');
        
        if (success) {
            await bot.sendMessage(chatId, `✅ Face verification rejected successfully!`);
        } else {
            await bot.sendMessage(chatId, '❌ Failed to reject verification.');
        }
    } catch (error) {
        console.error('Error in face_reject command:', error);
        await bot.sendMessage(chatId, '❌ Error rejecting verification.');
    }
});

// Improved reply command with better message handling
bot.onText(/\/reply_(.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const fullMatch = match[1];
    
    console.log(`📤 Quick reply command: ${fullMatch}`);
    
    try {
        // Split the user ID from the message more carefully
        const firstSpaceIndex = fullMatch.indexOf(' ');
        
        if (firstSpaceIndex === -1) {
            await bot.sendMessage(chatId, '❌ Usage: /reply_<user_id> <message>');
            return;
        }
        
        const userId = fullMatch.substring(0, firstSpaceIndex);
        const message = fullMatch.substring(firstSpaceIndex + 1);
        
        if (!message.trim()) {
            await bot.sendMessage(chatId, '❌ Message cannot be empty.');
            return;
        }
        
        console.log(`📤 Quick reply to user: ${userId} from admin: ${chatId}`);
        
        const userInfo = await getUserInfo(userId);
        if (!userInfo) {
            await bot.sendMessage(chatId, '❌ User not found.');
            return;
        }
        
        const success = await sendAdminMessage(userId, message);
        
        if (success) {
            // Also mark messages as read when using quick reply
            await markMessagesAsRead(userId);
            await bot.sendMessage(chatId, `✅ Reply sent to ${userInfo.name} (@${userInfo.username})`);
        } else {
            await bot.sendMessage(chatId, '❌ Failed to send reply.');
        }
    } catch (error) {
        console.error('Error in reply command:', error);
        await bot.sendMessage(chatId, '❌ Error sending reply.');
    }
});

// End chat command
bot.onText(/\/end/, (msg) => {
    const chatId = msg.chat.id;
    const userId = activeChats.get(chatId);
    console.log(`🔚 Ending chat: admin ${chatId} -> user ${userId}`);
    
    activeChats.delete(chatId);
    bot.sendMessage(chatId, '✅ Chat ended. Use /pending to view other chats.');
});

// Handle regular messages (when in active chat)
bot.on('message', async (msg) => {
    const chatId = msg.chat.id;
    const text = msg.text;
    
    // Ignore commands
    if (text.startsWith('/')) return;
    
    // Check if in active chat
    const userId = activeChats.get(chatId);
    if (userId && text) {
        console.log(`💬 Admin ${chatId} sending message to user ${userId}:`, text);
        
        try {
            const userInfo = await getUserInfo(userId);
            if (!userInfo) {
                await bot.sendMessage(chatId, '❌ User not found.');
                activeChats.delete(chatId);
                return;
            }
            
            const success = await sendAdminMessage(userId, text);
            
            if (success) {
                await bot.sendMessage(chatId, `✅ Message sent to ${userInfo.name}`);
            } else {
                await bot.sendMessage(chatId, '❌ Failed to send message.');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            await bot.sendMessage(chatId, '❌ Error sending message.');
        }
    }
});

// Fixed Task management commands
bot.onText(/\/task_add/, async (msg) => {
    const chatId = msg.chat.id;
    console.log(`📝 Task add command from admin: ${chatId}`);
    
    const instructions = `<b>📝 Add New Task</b>

<b>Usage:</b>
<code>/task_create title | description | platform | task_url | reward | max_completions</code>

<b>Example:</b>
<code>/task_create Follow our Telegram | Join our main Telegram channel | telegram | https://t.me/affileteoin | 200 | 100</code>

<b>Parameters:</b>
• title - Task title
• description - Task description (optional)
• platform - telegram, whatsapp, facebook, etc.
• task_url - The URL users need to visit
• reward - Reward amount in Naira
• max_completions - Maximum number of completions (0 for unlimited)

<b>Platforms:</b>
telegram, whatsapp, facebook, tiktok, youtube, instagram, general`;

    await bot.sendMessage(chatId, instructions, { parse_mode: 'HTML' });
});

bot.onText(/\/task_create (.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const params = match[1].split('|').map(p => p.trim());
    
    console.log(`📝 Creating task with params:`, params);
    
    if (params.length < 5) {
        await bot.sendMessage(chatId, '❌ Invalid format. Use: title | description | platform | task_url | reward | [max_completions]');
        return;
    }
    
    try {
        const [title, description, platform, task_url, reward, max_completions = 0] = params;
        
        const taskData = {
            title: title,
            description: description,
            platform: platform,
            task_url: task_url,
            reward_amount: parseFloat(reward),
            max_completions: parseInt(max_completions) || null,
            status: 'active',
            created_by: 'admin'
        };
        
        const response = await supabase.post('/rest/v1/tasks', taskData);
        
        if (response.status === 201) {
            await bot.sendMessage(chatId, 
                `✅ Task created successfully!\n\n` +
                `<b>Title:</b> ${title}\n` +
                `<b>Platform:</b> ${platform}\n` +
                `<b>Reward:</b> ₦${reward}\n` +
                `<b>URL:</b> ${task_url}\n` +
                `<b>Max Completions:</b> ${max_completions || 'Unlimited'}`,
                { parse_mode: 'HTML' }
            );
        } else {
            await bot.sendMessage(chatId, '❌ Failed to create task');
        }
    } catch (error) {
        console.error('Task creation error:', error);
        await bot.sendMessage(chatId, '❌ Error creating task');
    }
});

bot.onText(/\/task_list/, async (msg) => {
    const chatId = msg.chat.id;
    console.log(`📋 Task list command from admin: ${chatId}`);
    
    try {
        const response = await supabase.get('/rest/v1/tasks?order=created_at.desc&limit=10');
        const tasks = response.data || [];
        
        if (tasks.length === 0) {
            await bot.sendMessage(chatId, '📭 No tasks found');
            return;
        }
        
        let message = `<b>📋 Recent Tasks (${tasks.length})</b>\n\n`;
        
        tasks.forEach((task, index) => {
            message += `<b>${index + 1}. ${task.title}</b>\n`;
            message += `<code>ID: ${task.id}</code>\n`;
            message += `🪙 ₦${task.reward_amount} • 📱 ${task.platform}\n`;
            message += `✅ ${task.current_completions || 0}/${task.max_completions || '∞'} completions\n`;
            message += `📊 Status: ${task.status}\n`;
            message += `<code>/task_view_${task.id}</code>\n\n`;
        });
        
        await bot.sendMessage(chatId, message, { 
            parse_mode: 'HTML',
            disable_web_page_preview: true 
        });
    } catch (error) {
        console.error('Task list error:', error);
        await bot.sendMessage(chatId, '❌ Error fetching tasks');
    }
});

bot.onText(/\/task_view_(.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const taskId = match[1];
    
    console.log(`👀 Viewing task: ${taskId}`);
    
    try {
        const taskResponse = await supabase.get(`/rest/v1/tasks?id=eq.${taskId}`);
        const task = taskResponse.data?.[0];
        
        if (!task) {
            await bot.sendMessage(chatId, '❌ Task not found');
            return;
        }
        
        // Get submissions for this task
        const subsResponse = await supabase.get(`/rest/v1/task_submissions?task_id=eq.${taskId}&order=submitted_at.desc`);
        const submissions = subsResponse.data || [];
        
        const pendingSubs = submissions.filter(s => s.status === 'pending');
        const approvedSubs = submissions.filter(s => s.status === 'approved');
        const rejectedSubs = submissions.filter(s => s.status === 'rejected');
        
        let message = `<b>📝 Task Details</b>\n\n`;
        message += `<b>Title:</b> ${task.title}\n`;
        message += `<b>Description:</b> ${task.description || 'N/A'}\n`;
        message += `<b>Platform:</b> ${task.platform}\n`;
        message += `<b>Reward:</b> ₦${task.reward_amount}\n`;
        message += `<b>URL:</b> ${task.task_url}\n`;
        message += `<b>Max Completions:</b> ${task.max_completions || 'Unlimited'}\n`;
        message += `<b>Current Completions:</b> ${task.current_completions || 0}\n`;
        message += `<b>Status:</b> ${task.status}\n`;
        
        if (task.archived_at) {
            message += `<b>Archived:</b> ${new Date(task.archived_at).toLocaleString()}\n`;
        }
        
        message += `\n<b>📊 Submissions:</b>\n`;
        message += `⏳ Pending: ${pendingSubs.length}\n`;
        message += `✅ Approved: ${approvedSubs.length}\n`;
        message += `❌ Rejected: ${rejectedSubs.length}\n\n`;
        
        message += `<b>⚡ Quick Actions:</b>\n`;
        
        if (task.status === 'archived') {
            message += `<code>/task_restore_${task.id}</code> - Restore task\n`;
            message += `<code>/task_pending_${task.id}</code> - View pending\n`;
        } else {
            message += `<code>/task_pending_${task.id}</code> - View pending\n`;
            message += `<code>/task_disable_${task.id}</code> - Disable task\n`;
            message += `<code>/task_archive_${task.id}</code> - Archive task\n`;
        }
        
        await bot.sendMessage(chatId, message, { 
            parse_mode: 'HTML',
            disable_web_page_preview: true 
        });
    } catch (error) {
        console.error('Task view error:', error);
        await bot.sendMessage(chatId, '❌ Error fetching task details');
    }
});

bot.onText(/\/task_pending_(.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const taskId = match[1];
    
    console.log(`⏳ Viewing pending submissions for task: ${taskId}`);
    
    try {
        const response = await supabase.get(`/rest/v1/task_submissions?task_id=eq.${taskId}&status=eq.pending&order=submitted_at.asc`);
        const submissions = response.data || [];
        
        if (submissions.length === 0) {
            await bot.sendMessage(chatId, '✅ No pending submissions for this task');
            return;
        }
        
        for (const submission of submissions) {
            // Get user info
            const userResponse = await supabase.get(`/rest/v1/users?id=eq.${submission.user_id}`);
            const user = userResponse.data?.[0];
            
            let message = `<b>⏳ Pending Submission</b>\n\n`;
            message += `<b>User:</b> ${user?.name || 'Unknown'} (@${user?.username || 'N/A'})\n`;
            message += `<b>Task:</b> ${submission.task_id}\n`;
            message += `<b>Submitted:</b> ${new Date(submission.submitted_at).toLocaleString()}\n\n`;
            
            message += `<b>⚡ Quick Actions:</b>\n`;
            message += `<code>/task_approve_${submission.id}</code>\n`;
            message += `<code>/task_reject_${submission.id} reason</code>\n\n`;
            
            message += `<i>Review the proof image below</i>`;
            
            // Send proof image
            if (submission.proof_url) {
                try {
                    await bot.sendPhoto(chatId, submission.proof_url, {
                        caption: message,
                        parse_mode: 'HTML'
                    });
                } catch (photoError) {
                    await bot.sendMessage(chatId, 
                        `${message}\n\n📷 <a href="${submission.proof_url}">View Proof Image</a>`, 
                        { parse_mode: 'HTML' }
                    );
                }
            } else {
                await bot.sendMessage(chatId, message + '\n\n❌ No proof image available', { 
                    parse_mode: 'HTML' 
                });
            }
            
            // Add delay between multiple submissions
            if (submissions.length > 1) {
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
        }
    } catch (error) {
        console.error('Task pending error:', error);
        await bot.sendMessage(chatId, '❌ Error fetching pending submissions');
    }
});

// FIXED: Task approval function
bot.onText(/\/task_approve_(.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const submissionId = match[1];
    
    console.log(`✅ Approving task submission: ${submissionId}`);
    
    try {
        // Get submission details
        const subResponse = await supabase.get(`/rest/v1/task_submissions?id=eq.${submissionId}`);
        const submission = subResponse.data?.[0];
        
        if (!submission) {
            await bot.sendMessage(chatId, '❌ Submission not found');
            return;
        }
        
        // Get task details
        const taskResponse = await supabase.get(`/rest/v1/tasks?id=eq.${submission.task_id}`);
        const task = taskResponse.data?.[0];
        
        if (!task) {
            await bot.sendMessage(chatId, '❌ Task not found');
            return;
        }
        
        // Check if task has reached max completions
        if (task.max_completions && task.current_completions >= task.max_completions) {
            await bot.sendMessage(chatId, '❌ Task has reached maximum completions');
            return;
        }
        
        // Update submission status - FIXED: Use proper PATCH request
        const updateData = {
            status: 'approved',
            reviewed_at: new Date().toISOString()
        };
        
        const updateResponse = await supabase.patch(`/rest/v1/task_submissions?id=eq.${submissionId}`, updateData);
        
        console.log('Update response status:', updateResponse.status);
        console.log('Update response data:', updateResponse.data);
        
        if (updateResponse.status === 200 || updateResponse.status === 204) {
            // Update task completion count - FIXED: Use proper PATCH request
            const newCompletions = (parseInt(task.current_completions) || 0) + 1;
            const taskUpdateData = {
                current_completions: newCompletions
            };
            
            await supabase.patch(`/rest/v1/tasks?id=eq.${task.id}`, taskUpdateData);
            
            // Update user's cashback balance - FIXED: Use proper PATCH request
            const userResponse = await supabase.get(`/rest/v1/users?id=eq.${submission.user_id}`);
            const user = userResponse.data?.[0];
            
            if (user) {
                const currentCashback = parseFloat(user.cashback_balance) || 0;
                const taskReward = parseFloat(task.reward_amount) || 0;
                const newCashback = currentCashback + taskReward;
                
                const userUpdateData = {
                    cashback_balance: newCashback
                };
                
                await supabase.patch(`/rest/v1/users?id=eq.${submission.user_id}`, userUpdateData);
            }
            
            await bot.sendMessage(chatId, 
                `✅ Submission approved successfully!\n\n` +
                `💰 User received ₦${task.reward_amount} cashback\n` +
                `📊 Task completions: ${newCompletions}/${task.max_completions || '∞'}`
            );
        } else {
            console.error('Update failed:', updateResponse);
            await bot.sendMessage(chatId, '❌ Failed to approve submission - update failed');
        }
    } catch (error) {
        console.error('Approve submission error:', error);
        await bot.sendMessage(chatId, '❌ Error approving submission: ' + error.message);
    }
});

// FIXED: Task rejection function
bot.onText(/\/task_reject_(.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const fullMatch = match[1];
    
    console.log(`❌ Rejecting task submission: ${fullMatch}`);
    
    try {
        // Parse submission ID and reason
        const parts = fullMatch.split(' ');
        const submissionId = parts[0];
        const reason = parts.slice(1).join(' ') || 'No reason provided';
        
        if (!submissionId) {
            await bot.sendMessage(chatId, '❌ Usage: /task_reject_<submission_id> <reason>');
            return;
        }
        
        // Update submission status - FIXED: Use proper PATCH request
        const updateData = {
            status: 'rejected',
            admin_notes: reason,
            reviewed_at: new Date().toISOString()
        };
        
        const updateResponse = await supabase.patch(`/rest/v1/task_submissions?id=eq.${submissionId}`, updateData);
        
        console.log('Reject response status:', updateResponse.status);
        console.log('Reject response data:', updateResponse.data);
        
        if (updateResponse.status === 200 || updateResponse.status === 204) {
            await bot.sendMessage(chatId, `✅ Submission rejected!\n\nReason: ${reason}`);
        } else {
            console.error('Reject failed:', updateResponse);
            await bot.sendMessage(chatId, '❌ Failed to reject submission - update failed');
        }
    } catch (error) {
        console.error('Reject submission error:', error);
        await bot.sendMessage(chatId, '❌ Error rejecting submission: ' + error.message);
    }
});

// ARCHIVE: Archive task instead of deleting it (preserves all data)
bot.onText(/\/task_archive_(.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const taskId = match[1];
    
    console.log(`🗑️ Archiving task: ${taskId}`);
    
    try {
        // First check if task exists
        const taskResponse = await supabase.get(`/rest/v1/tasks?id=eq.${taskId}`);
        const task = taskResponse.data?.[0];
        
        if (!task) {
            await bot.sendMessage(chatId, '❌ Task not found');
            return;
        }
        
        // Check if task is already archived
        if (task.status === 'archived' || task.archived_at) {
            await bot.sendMessage(chatId, '❌ Task is already archived');
            return;
        }
        
        // Archive the task instead of deleting it
        const archiveData = {
            status: 'archived',
            archived_at: new Date().toISOString(),
            archived_reason: 'Archived by admin via bot'
        };
        
        const archiveResponse = await supabase.patch(`/rest/v1/tasks?id=eq.${taskId}`, archiveData);
        
        console.log('Archive response status:', archiveResponse.status);
        
        if (archiveResponse.status === 200 || archiveResponse.status === 204) {
            // Get submission stats for the message
            const subsResponse = await supabase.get(`/rest/v1/task_submissions?task_id=eq.${taskId}`);
            const submissions = subsResponse.data || [];
            const approvedSubs = submissions.filter(s => s.status === 'approved');
            
            let message = `✅ Task archived successfully!\n\n` +
                         `"${task.title}" has been archived.\n\n` +
                         `📊 Task Statistics:\n` +
                         `• Total submissions: ${submissions.length}\n` +
                         `• Approved: ${approvedSubs.length}\n` +
                         `• Pending: ${submissions.filter(s => s.status === 'pending').length}\n` +
                         `• Rejected: ${submissions.filter(s => s.status === 'rejected').length}\n\n`;
            
            if (approvedSubs.length > 0) {
                const totalEarned = task.reward_amount * approvedSubs.length;
                message += `💰 Users earned: ₦${totalEarned}\n`;
            }
            
            message += `\n✅ All user data preserved\n` +
                      `📱 Task hidden from active list\n` +
                      `💾 Can be restored if needed`;
            
            await bot.sendMessage(chatId, message);
        } else {
            console.error('Archive failed:', archiveResponse);
            await bot.sendMessage(chatId, '❌ Failed to archive task');
        }
    } catch (error) {
        console.error('Archive task error:', error);
        await bot.sendMessage(chatId, '❌ Error archiving task: ' + error.message);
    }
});

// NEW: Restore archived task
bot.onText(/\/task_restore_(.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const taskId = match[1];
    
    console.log(`🔄 Restoring task: ${taskId}`);
    
    try {
        // First check if task exists
        const taskResponse = await supabase.get(`/rest/v1/tasks?id=eq.${taskId}`);
        const task = taskResponse.data?.[0];
        
        if (!task) {
            await bot.sendMessage(chatId, '❌ Task not found');
            return;
        }
        
        // Check if task is actually archived
        if (task.status !== 'archived' && !task.archived_at) {
            await bot.sendMessage(chatId, '❌ Task is not archived');
            return;
        }
        
        // Restore the task
        const restoreData = {
            status: 'active',
            archived_at: null,
            archived_reason: null
        };
        
        const restoreResponse = await supabase.patch(`/rest/v1/tasks?id=eq.${taskId}`, restoreData);
        
        console.log('Restore response status:', restoreResponse.status);
        
        if (restoreResponse.status === 200 || restoreResponse.status === 204) {
            await bot.sendMessage(chatId, 
                `✅ Task restored successfully!\n\n` +
                `"${task.title}" is now active again.\n` +
                `📱 Task visible in active tasks list\n` +
                `👥 Users can submit proofs again`
            );
        } else {
            console.error('Restore failed:', restoreResponse);
            await bot.sendMessage(chatId, '❌ Failed to restore task');
        }
    } catch (error) {
        console.error('Restore task error:', error);
        await bot.sendMessage(chatId, '❌ Error restoring task: ' + error.message);
    }
});

// NEW: List archived tasks
bot.onText(/\/task_archived/, async (msg) => {
    const chatId = msg.chat.id;
    console.log(`📁 Listing archived tasks from admin: ${chatId}`);
    
    try {
        const response = await supabase.get('/rest/v1/tasks?status=eq.archived&order=archived_at.desc&limit=10');
        const tasks = response.data || [];
        
        if (tasks.length === 0) {
            await bot.sendMessage(chatId, '📭 No archived tasks found');
            return;
        }
        
        let message = `<b>📁 Archived Tasks (${tasks.length})</b>\n\n`;
        
        tasks.forEach((task, index) => {
            const archivedDate = new Date(task.archived_at).toLocaleDateString();
            message += `<b>${index + 1}. ${task.title}</b>\n`;
            message += `<code>ID: ${task.id}</code>\n`;
            message += `🪙 ₦${task.reward_amount} • 📱 ${task.platform}\n`;
            message += `✅ ${task.current_completions || 0}/${task.max_completions || '∞'} completions\n`;
            message += `📅 Archived: ${archivedDate}\n`;
            message += `<code>/task_restore_${task.id}</code>\n\n`;
        });
        
        await bot.sendMessage(chatId, message, { 
            parse_mode: 'HTML',
            disable_web_page_preview: true 
        });
    } catch (error) {
        console.error('Archived tasks list error:', error);
        await bot.sendMessage(chatId, '❌ Error fetching archived tasks');
    }
});

// Bank management commands for admin
bot.onText(/\/banks_list/, async (msg) => {
    const chatId = msg.chat.id;
    console.log(`🏦 Bank list command from admin: ${chatId}`);
    
    try {
        const response = await supabase.get('/rest/v1/bank_details?order=created_at.desc&limit=20');
        const banks = response.data || [];
        
        if (banks.length === 0) {
            await bot.sendMessage(chatId, '🏦 No bank accounts found');
            return;
        }
        
        let message = `<b>🏦 Recent Bank Accounts (${banks.length})</b>\n\n`;
        
        for (const bank of banks) {
            // Get user info
            const userResponse = await supabase.get(`/rest/v1/users?id=eq.${bank.user_id}`);
            const user = userResponse.data?.[0];
            
            message += `<b>🏦 ${bank.bank_name}</b>\n`;
            message += `<b>User:</b> ${user?.name || 'Unknown'} (@${user?.username || 'N/A'})\n`;
            message += `<b>Account:</b> ${bank.account_name}\n`;
            message += `<b>Number:</b> ${bank.account_number}\n`;
            message += `<b>Default:</b> ${bank.is_default ? '✅ Yes' : '❌ No'}\n`;
            message += `<b>Added:</b> ${new Date(bank.created_at).toLocaleDateString()}\n\n`;
        }
        
        await bot.sendMessage(chatId, message, { 
            parse_mode: 'HTML',
            disable_web_page_preview: true 
        });
    } catch (error) {
        console.error('Bank list error:', error);
        await bot.sendMessage(chatId, '❌ Error fetching bank accounts');
    }
});

// Enhanced version that accepts both user ID and username
bot.onText(/\/user_banks (.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const identifier = match[1]; // This could be user ID or username
    
    console.log(`🏦 User banks command for: ${identifier}`);
    
    try {
        let userId = identifier;
        
        // Check if identifier is a username (starts with @)
        if (identifier.startsWith('@')) {
            const username = identifier.substring(1);
            const userResponse = await supabase.get(`/rest/v1/users?username=eq.${username}`);
            const user = userResponse.data?.[0];
            
            if (!user) {
                await bot.sendMessage(chatId, '❌ User not found with that username');
                return;
            }
            userId = user.id;
        }
        
        const response = await supabase.get(`/rest/v1/bank_details?user_id=eq.${userId}&order=is_default.desc,created_at.desc`);
        const banks = response.data || [];
        
        // Get user info
        const userResponse = await supabase.get(`/rest/v1/users?id=eq.${userId}`);
        const user = userResponse.data?.[0];
        
        if (!user) {
            await bot.sendMessage(chatId, '❌ User not found');
            return;
        }
        
        if (banks.length === 0) {
            await bot.sendMessage(chatId, `🏦 ${user.name} (@${user.username}) has no bank accounts saved`);
            return;
        }
        
        let message = `<b>🏦 Bank Accounts for ${user.name} (@${user.username})</b>\n\n`;
        
        banks.forEach((bank, index) => {
            message += `<b>${index + 1}. ${bank.bank_name}</b>\n`;
            message += `<b>Account:</b> ${bank.account_name}\n`;
            message += `<b>Number:</b> ${bank.account_number}\n`;
            message += `<b>Default:</b> ${bank.is_default ? '✅ Yes' : '❌ No'}\n`;
            message += `<b>Verified:</b> ${bank.is_verified ? '✅ Yes' : '❌ No'}\n`;
            message += `<b>Added:</b> ${new Date(bank.created_at).toLocaleDateString()}\n\n`;
        });
        
        await bot.sendMessage(chatId, message, { 
            parse_mode: 'HTML',
            disable_web_page_preview: true 
        });
    } catch (error) {
        console.error('User banks error:', error);
        await bot.sendMessage(chatId, '❌ Error fetching user bank accounts');
    }
});

bot.onText(/\/task_disable_(.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const taskId = match[1];
    
    try {
        const updateData = {
            status: 'inactive'
        };
        
        const response = await supabase.patch(`/rest/v1/tasks?id=eq.${taskId}`, updateData);
        
        if (response.status === 200 || response.status === 204) {
            await bot.sendMessage(chatId, '✅ Task disabled');
        } else {
            await bot.sendMessage(chatId, '❌ Failed to disable task');
        }
    } catch (error) {
        console.error('Disable task error:', error);
        await bot.sendMessage(chatId, '❌ Error disabling task');
    }
});

bot.onText(/\/task_enable_(.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const taskId = match[1];
    
    try {
        const updateData = {
            status: 'active'
        };
        
        const response = await supabase.patch(`/rest/v1/tasks?id=eq.${taskId}`, updateData);
        
        if (response.status === 200 || response.status === 204) {
            await bot.sendMessage(chatId, '✅ Task enabled');
        } else {
            await bot.sendMessage(chatId, '❌ Failed to enable task');
        }
    } catch (error) {
        console.error('Enable task error:', error);
        await bot.sendMessage(chatId, '❌ Error enabling task');
    }
});

// Add these commands to bot.js

// Withdrawal management commands
bot.onText(/\/withdrawal_settings/, async (msg) => {
    const chatId = msg.chat.id;
    
    try {
        const response = await supabase.get('/rest/v1/withdrawal_settings?id=eq.default-settings');
        const settings = response.data?.[0] || {
            is_live: false,
            min_amount: 10000,
            max_amount: 10000,
            required_approved_tasks: 5,
            require_face_verification: true,
            once_per_day: true
        };
        
        let message = `<b>💰 Withdrawal Settings</b>\n\n`;
        message += `<b>Status:</b> ${settings.is_live ? '🟢 LIVE' : '🔴 CLOSED'}\n`;
        
        if (settings.opens_at) {
            message += `<b>Opens:</b> ${new Date(settings.opens_at).toLocaleString()}\n`;
        }
        if (settings.closes_at) {
            message += `<b>Closes:</b> ${new Date(settings.closes_at).toLocaleString()}\n`;
        }
        
        message += `<b>Min Amount:</b> ₦${settings.min_amount}\n`;
        message += `<b>Max Amount:</b> ₦${settings.max_amount}\n`;
        message += `<b>Required Tasks:</b> ${settings.required_approved_tasks}\n`;
        message += `<b>Face Verify:</b> ${settings.require_face_verification ? '✅ Yes' : '❌ No'}\n`;
        message += `<b>Once per Day:</b> ${settings.once_per_day ? '✅ Yes' : '❌ No'}\n\n`;
        
        message += `<b>⚡ Quick Actions:</b>\n`;
        message += `<code>/withdrawal_live</code> - Enable withdrawals\n`;
        message += `<code>/withdrawal_close</code> - Disable withdrawals\n`;
        message += `<code>/withdrawal_open 2h</code> - Open for 2 hours\n`;
        message += `<code>/withdrawal_tasks 3</code> - Set required tasks\n`;
        message += `<code>/withdrawal_min 5000</code> - Set min amount\n`;
        message += `<code>/withdrawal_max 20000</code> - Set max amount\n`;
        
        await bot.sendMessage(chatId, message, { parse_mode: 'HTML' });
    } catch (error) {
        console.error('Withdrawal settings error:', error);
        await bot.sendMessage(chatId, '❌ Error fetching withdrawal settings');
    }
});

bot.onText(/\/withdrawal_live/, async (msg) => {
    const chatId = msg.chat.id;
    
    try {
        const updateData = {
            is_live: true,
            opens_at: null,
            closes_at: null,
            updated_at: new Date().toISOString()
        };
        
        const response = await supabase.patch('/rest/v1/withdrawal_settings?id=eq.default-settings', updateData);
        
        if (response.status === 200 || response.status === 204) {
            await bot.sendMessage(chatId, '✅ Withdrawals are now LIVE!');
        } else {
            await bot.sendMessage(chatId, '❌ Failed to enable withdrawals');
        }
    } catch (error) {
        console.error('Withdrawal live error:', error);
        await bot.sendMessage(chatId, '❌ Error enabling withdrawals');
    }
});

bot.onText(/\/withdrawal_close/, async (msg) => {
    const chatId = msg.chat.id;
    
    try {
        const updateData = {
            is_live: false,
            opens_at: null,
            closes_at: null,
            updated_at: new Date().toISOString()
        };
        
        const response = await supabase.patch('/rest/v1/withdrawal_settings?id=eq.default-settings', updateData);
        
        if (response.status === 200 || response.status === 204) {
            await bot.sendMessage(chatId, '✅ Withdrawals are now CLOSED!');
        } else {
            await bot.sendMessage(chatId, '❌ Failed to disable withdrawals');
        }
    } catch (error) {
        console.error('Withdrawal close error:', error);
        await bot.sendMessage(chatId, '❌ Error disabling withdrawals');
    }
});

bot.onText(/\/withdrawal_open (.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const duration = match[1];
    
    try {
        let hours = 1;
        
        if (duration.endsWith('h')) {
            hours = parseInt(duration);
        } else if (duration.endsWith('m')) {
            hours = parseInt(duration) / 60;
        }
        
        const opensAt = new Date();
        const closesAt = new Date(opensAt.getTime() + (hours * 60 * 60 * 1000));
        
        const updateData = {
            is_live: true,
            opens_at: opensAt.toISOString(),
            closes_at: closesAt.toISOString(),
            updated_at: new Date().toISOString()
        };
        
        const response = await supabase.patch('/rest/v1/withdrawal_settings?id=eq.default-settings', updateData);
        
        if (response.status === 200 || response.status === 204) {
            await bot.sendMessage(chatId, 
                `✅ Withdrawals opened for ${hours} hour(s)!\n\n` +
                `Opens: ${opensAt.toLocaleString()}\n` +
                `Closes: ${closesAt.toLocaleString()}`
            );
        } else {
            await bot.sendMessage(chatId, '❌ Failed to open withdrawals');
        }
    } catch (error) {
        console.error('Withdrawal open error:', error);
        await bot.sendMessage(chatId, '❌ Error opening withdrawals');
    }
});

bot.onText(/\/withdrawal_tasks (\d+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const tasks = parseInt(match[1]);
    
    try {
        const updateData = {
            required_approved_tasks: tasks,
            updated_at: new Date().toISOString()
        };
        
        const response = await supabase.patch('/rest/v1/withdrawal_settings?id=eq.default-settings', updateData);
        
        if (response.status === 200 || response.status === 204) {
            await bot.sendMessage(chatId, `✅ Required tasks set to ${tasks}`);
        } else {
            await bot.sendMessage(chatId, '❌ Failed to update required tasks');
        }
    } catch (error) {
        console.error('Withdrawal tasks error:', error);
        await bot.sendMessage(chatId, '❌ Error updating required tasks');
    }
});

bot.onText(/\/withdrawal_min (\d+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const amount = parseInt(match[1]);
    
    try {
        const updateData = {
            min_amount: amount,
            updated_at: new Date().toISOString()
        };
        
        const response = await supabase.patch('/rest/v1/withdrawal_settings?id=eq.default-settings', updateData);
        
        if (response.status === 200 || response.status === 204) {
            await bot.sendMessage(chatId, `✅ Minimum amount set to ₦${amount}`);
        } else {
            await bot.sendMessage(chatId, '❌ Failed to update minimum amount');
        }
    } catch (error) {
        console.error('Withdrawal min error:', error);
        await bot.sendMessage(chatId, '❌ Error updating minimum amount');
    }
});

bot.onText(/\/withdrawal_max (\d+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const amount = parseInt(match[1]);
    
    try {
        const updateData = {
            max_amount: amount,
            updated_at: new Date().toISOString()
        };
        
        const response = await supabase.patch('/rest/v1/withdrawal_settings?id=eq.default-settings', updateData);
        
        if (response.status === 200 || response.status === 204) {
            await bot.sendMessage(chatId, `✅ Maximum amount set to ₦${amount}`);
        } else {
            await bot.sendMessage(chatId, '❌ Failed to update maximum amount');
        }
    } catch (error) {
        console.error('Withdrawal max error:', error);
        await bot.sendMessage(chatId, '❌ Error updating maximum amount');
    }
});

// Withdrawal approval commands
bot.onText(/\/withdrawal_pending/, async (msg) => {
    const chatId = msg.chat.id;
    
    try {
        const response = await supabase.get('/rest/v1/withdrawals?status=eq.pending&order=created_at.asc');
        const withdrawals = response.data || [];
        
        if (withdrawals.length === 0) {
            await bot.sendMessage(chatId, '✅ No pending withdrawals');
            return;
        }
        
        let message = `<b>⏳ Pending Withdrawals (${withdrawals.length})</b>\n\n`;
        
        for (const withdrawal of withdrawals) {
            // Get user info
            const userResponse = await supabase.get(`/rest/v1/users?id=eq.${withdrawal.user_id}`);
            const user = userResponse.data?.[0];
            
            // Get bank info
            const bankResponse = await supabase.get(`/rest/v1/bank_details?id=eq.${withdrawal.bank_details_id}`);
            const bank = bankResponse.data?.[0];
            
            // Get multiple accounts count
            const multipleAccountsCount = await getMultipleAccountsCount(withdrawal.user_id);
            
            message += `<b>💰 ₦${withdrawal.amount}</b>\n`;
            message += `<b>User:</b> ${user?.name || 'Unknown'} (@${user?.username || 'N/A'})`;
            
            // Add multiple accounts warning if found
            if (multipleAccountsCount > 0) {
                message += ` <b>⚠️ (${multipleAccountsCount} multiple accounts associated)</b>`;
            }
            message += `\n`;
            
            if (bank) {
                message += `<b>Bank:</b> ${bank.bank_name} - ${bank.account_number}\n`;
                message += `<b>Account:</b> ${bank.account_name}\n`;
            }
            message += `<b>Date:</b> ${new Date(withdrawal.created_at).toLocaleString()}\n`;
            message += `<code>/withdrawal_approve_${withdrawal.id}</code>\n`;
            message += `<code>/withdrawal_reject_${withdrawal.id} reason</code>\n\n`;
        }
        
        await bot.sendMessage(chatId, message, { parse_mode: 'HTML' });
    } catch (error) {
        console.error('Withdrawal pending error:', error);
        await bot.sendMessage(chatId, '❌ Error fetching pending withdrawals');
    }
});

// Command to check multiple accounts for a specific user
bot.onText(/\/check_multiple (.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const identifier = match[1]; // Can be user ID or username
    
    try {
        let userId = identifier;
        
        // Check if identifier is a username
        if (identifier.startsWith('@')) {
            const username = identifier.substring(1);
            const userResponse = await supabase.get(`/rest/v1/users?username=eq.${username}`);
            const user = userResponse.data?.[0];
            
            if (!user) {
                await bot.sendMessage(chatId, '❌ User not found with that username');
                return;
            }
            userId = user.id;
        }
        
        const multipleAccountsCount = await getMultipleAccountsCount(userId);
        
        // Get user info
        const userResponse = await supabase.get(`/rest/v1/users?id=eq.${userId}`);
        const user = userResponse.data?.[0];
        
        if (!user) {
            await bot.sendMessage(chatId, '❌ User not found');
            return;
        }
        
        let message = `<b>🔍 Multiple Accounts Check</b>\n\n`;
        message += `<b>User:</b> ${user.name} (@${user.username})\n`;
        message += `<b>Multiple Accounts Found:</b> ${multipleAccountsCount}\n`;
        
        if (multipleAccountsCount > 0) {
            message += `\n⚠️ <b>Warning:</b> This user shares IP addresses with ${multipleAccountsCount} other account(s)`;
        } else {
            message += `\n✅ <b>Clean:</b> No multiple accounts detected`;
        }
        
        await bot.sendMessage(chatId, message, { parse_mode: 'HTML' });
    } catch (error) {
        console.error('Check multiple error:', error);
        await bot.sendMessage(chatId, '❌ Error checking multiple accounts');
    }
});

bot.onText(/\/withdrawal_approve_(.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const withdrawalId = match[1];
    
    try {
        const updateData = {
            status: 'approved',
            updated_at: new Date().toISOString()
        };
        
        const response = await supabase.patch(`/rest/v1/withdrawals?id=eq.${withdrawalId}`, updateData);
        
        if (response.status === 200 || response.status === 204) {
            await bot.sendMessage(chatId, '✅ Withdrawal approved successfully!');
        } else {
            await bot.sendMessage(chatId, '❌ Failed to approve withdrawal');
        }
    } catch (error) {
        console.error('Withdrawal approve error:', error);
        await bot.sendMessage(chatId, '❌ Error approving withdrawal');
    }
});

bot.onText(/\/withdrawal_reject_(.+)/, async (msg, match) => {
    const chatId = msg.chat.id;
    const fullMatch = match[1];
    
    try {
        const parts = fullMatch.split(' ');
        const withdrawalId = parts[0];
        const reason = parts.slice(1).join(' ') || 'No reason provided';
        
        if (!withdrawalId) {
            await bot.sendMessage(chatId, '❌ Usage: /withdrawal_reject_<id> <reason>');
            return;
        }
        
        // First get withdrawal to refund user
        const withdrawalResponse = await supabase.get(`/rest/v1/withdrawals?id=eq.${withdrawalId}`);
        const withdrawal = withdrawalResponse.data?.[0];
        
        if (withdrawal) {
            // Refund the amount to user (you might want to implement proper refund logic)
            const userResponse = await supabase.get(`/rest/v1/users?id=eq.${withdrawal.user_id}`);
            const user = userResponse.data?.[0];
            
            if (user) {
                // This is a simplified refund - you might want to track which balance to refund to
                const refundAmount = withdrawal.amount;
                const newCashback = (user.cashback_balance || 0) + refundAmount;
                
                await supabase.patch(`/rest/v1/users?id=eq.${withdrawal.user_id}`, {
                    cashback_balance: newCashback
                });
            }
        }
        
        const updateData = {
            status: 'rejected',
            updated_at: new Date().toISOString()
        };
        
        const response = await supabase.patch(`/rest/v1/withdrawals?id=eq.${withdrawalId}`, updateData);
        
        if (response.status === 200 || response.status === 204) {
            await bot.sendMessage(chatId, `✅ Withdrawal rejected! User has been refunded.\nReason: ${reason}`);
        } else {
            await bot.sendMessage(chatId, '❌ Failed to reject withdrawal');
        }
    } catch (error) {
        console.error('Withdrawal reject error:', error);
        await bot.sendMessage(chatId, '❌ Error rejecting withdrawal');
    }
});

// Update help command to include archive management
bot.onText(/\/help/, (msg) => {
    const chatId = msg.chat.id;
    const helpMessage = `<b>🤖 Ninja Hope Admin Bot Help</b>

<b>Chat Commands:</b>
/pending - View pending chats
/chat &lt;user_id&gt; - Open chat with user
/reply &lt;user_id&gt; &lt;message&gt; - Quick reply

<b>Face Verification Commands:</b>
/face_pending - View pending face verifications
/face_approve_&lt;id&gt; - Approve face verification
/face_reject_&lt;id&gt; &lt;reason&gt; - Reject face verification

<b>Task Management Commands:</b>
/task_add - Show task creation instructions
/task_create - Create new task
/task_list - List recent tasks
/task_view_&lt;id&gt; - View task details
/task_pending_&lt;id&gt; - View pending submissions
/task_approve_&lt;id&gt; - Approve submission
/task_reject_&lt;id&gt; &lt;reason&gt; - Reject submission
/task_disable_&lt;id&gt; - Disable task
/task_enable_&lt;id&gt; - Enable task
/task_archive_&lt;id&gt; - Archive task (safe delete)
/task_restore_&lt;id&gt; - Restore archived task
/task_archived - List archived tasks
/banks_list - Recent bank details 
/user_banks @omoooo  - for users bank 


/withdrawal_settings
/withdrawal_pending
/check_multiple @hhh

<b>System Commands:</b>
/debug - Run system diagnostics
/end - End current active chat
/help - Show this help`;

    bot.sendMessage(chatId, helpMessage, { parse_mode: 'HTML' });
});

// Error handling
bot.on('error', (error) => {
    console.error('❌ Telegram Bot Error:', error);
});

bot.on('polling_error', (error) => {
    console.error('❌ Telegram Polling Error:', error);
});

console.log('🤖 Ninja Hope Admin Bot is running...');

// Run debug on startup
debugChatSystem();
detailedDebug();

// Periodic debug every 30 minutes
setInterval(() => {
    console.log('🕒 Periodic system check...');
    debugChatSystem();
}, 30 * 60 * 1000);
