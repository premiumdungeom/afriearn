<?php
// config.php

// Start session FIRST before any output
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters BEFORE starting session
    session_set_cookie_params([
        'lifetime' => 6 * 60 * 60, // 6 hours default
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'secure' => isset($_SERVER['HTTPS']), // Only send over HTTPS if available
        'httponly' => true, // Prevent JavaScript access
        'samesite' => 'Lax' // CSRF protection
    ]);
    
    session_start();
}

// Now include supabase_client
require_once 'supabase_client.php';

// Auto-logout if browser is closed (no "Remember Me")
if (!isset($_SESSION['remember_me']) || !$_SESSION['remember_me']) {
    // Update session cookie to expire when browser closes
    if (session_status() === PHP_SESSION_ACTIVE) {
        setcookie(session_name(), session_id(), 0, '/');
    }
}

// Helper functions
function isLoggedIn() {
    if (!isset($_SESSION['user']) || !isset($_SESSION['last_activity'])) {
        return false;
    }
    
    // Check if session is still valid (6 hours max for remember me)
    if (time() - $_SESSION['last_activity'] > 6 * 60 * 60) {
        // Session expired
        session_unset();
        session_destroy();
        return false;
    }
    
    return true;
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return $_SESSION['user'] ?? null;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function generateReferralCode() {
    return substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', 8)), 0, 8);
}

// Database helper functions
function getUserById($user_id) {
    global $supabase;
    try {
        $result = $supabase->from('users')->eq('id', $user_id);
        return $result['data'][0] ?? null;
    } catch (Exception $e) {
        error_log("Get user error: " . $e->getMessage());
        return null;
    }
}

function updateUserBalance($user_id, $amount) {
    global $supabase;
    try {
        $user = getUserById($user_id);
        if ($user) {
            $new_balance = ($user['balance'] ?? 0) + $amount;
            $result = $supabase->from('users')->update(['balance' => $new_balance])->eq('id', $user_id);
            return $result['status'] === 200;
        }
    } catch (Exception $e) {
        error_log("Update balance error: " . $e->getMessage());
    }
    return false;
}

function getReferralHistory($user_id) {
    global $supabase;
    try {
        $result = $supabase->from('referral_history')
            ->eq('referrer_id', $user_id)
            ->select('*');
        return $result['data'] ?? [];
    } catch (Exception $e) {
        error_log("Get referral history error: " . $e->getMessage());
        return [];
    }
}

function getUserCashback($user_id) {
    global $supabase;
    try {
        $result = $supabase->from('users')->eq('id', $user_id);
        return $result['data'][0]['cashback_balance'] ?? 0;
    } catch (Exception $e) {
        error_log("Get cashback error: " . $e->getMessage());
        return 0;
    }
}

function getSigninHistory($user_id) {
    global $supabase;
    try {
        $result = $supabase->from('signin_history')->eq('user_id', $user_id);
        return $result['data'] ?? [];
    } catch (Exception $e) {
        error_log("Signin history error: " . $e->getMessage());
        return [];
    }
}

function hasClaimedToday($user_id) {
    global $supabase;
    $today = date('Y-m-d');
    try {
        $result = $supabase->from('signin_history')->eq('user_id', $user_id);
        if (!empty($result['data'])) {
            foreach ($result['data'] as $row) {
                if (isset($row['claimed_on']) && $row['claimed_on'] === $today) {
                    return true;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Signin check error: " . $e->getMessage());
    }
    return false;
}

function claimSigninReward($user_id, $amount) {
    global $supabase;
    $today = date('Y-m-d');
    // Only allow one claim per day
    if (hasClaimedToday($user_id)) {
        return false;
    }
    try {
        // Insert into signin_history
        $result = $supabase->from('signin_history')->insert([
            [
                'user_id' => $user_id,
                'amount' => $amount,
                'claimed_on' => $today
            ]
        ]);
        if ($result['status'] === 201 || $result['status'] === 200) {
            // Update user cashback_balance atomically (no main balance!)
            $user = getUserById($user_id);
            $cb = (float)($user['cashback_balance'] ?? 0);
            $new_cb = $cb + $amount;
            $update = $supabase->from('users')->update(['cashback_balance' => $new_cb], 'id', $user_id);
            return ($update['status'] === 200);
        }
    } catch (Exception $e) {
        error_log("Claim signin reward error: " . $e->getMessage());
    }
    return false;
}

// Chat helper functions
// Chat helper functions
function getUserChatMessages($user_id) {
    global $supabase;
    try {
        error_log("Getting chat messages for user: " . $user_id);
        $result = $supabase->from('chat_messages')->eq('user_id', $user_id);
        
        if (!isset($result['data'])) {
            error_log("No data returned from chat_messages query");
            return [];
        }
        
        error_log("Found " . count($result['data']) . " messages for user " . $user_id);
        
        // Manual sorting since we can't chain order()
        $messages = $result['data'] ?? [];
        usort($messages, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });
        return $messages;
    } catch (Exception $e) {
        error_log("Get chat messages error: " . $e->getMessage());
        return [];
    }
}

function sendChatMessage($user_id, $sender_type, $message, $attachment_url = null) {
    global $supabase;
    try {
        error_log("Sending chat message - User: $user_id, Type: $sender_type, Message: $message");
        
        $message_data = [
            'user_id' => $user_id,
            'sender_type' => $sender_type,
            'message' => $message,
            'attachment_url' => $attachment_url,
            'is_read' => false
        ];
        
        $result = $supabase->from('chat_messages')->insert([$message_data]);
        
        error_log("Insert result status: " . $result['status']);
        
        if ($result['status'] === 201) {
            // Update chat session
            updateChatSession($user_id);
            error_log("Message sent successfully");
            return true;
        } else {
            error_log("Failed to send message: " . json_encode($result));
            return false;
        }
    } catch (Exception $e) {
        error_log("Send chat message error: " . $e->getMessage());
    }
    return false;
}

function updateChatSession($user_id) {
    global $supabase;
    try {
        // Use service role key directly for this operation to bypass RLS
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/chat_sessions';
        
        // First check if session exists using service role
        $check_url = $url . '?user_id=eq.' . $user_id;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $check_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $session = null;
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $session = $data[0] ?? null;
        }
        
        if ($session) {
            // Update existing session
            $update_data = [
                'last_message_at' => date('c'),
                'unread_count' => ($session['unread_count'] ?? 0) + 1
            ];
            
            $update_url = $url . '?user_id=eq.' . $user_id;
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $update_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_POSTFIELDS => json_encode($update_data),
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $service_key,
                    'Authorization: Bearer ' . $service_key,
                    'Content-Type: ' . 'application/json',
                    'Prefer: return=representation'
                ]
            ]);
            
            curl_exec($ch);
            curl_close($ch);
        } else {
            // Create new session
            $session_data = [
                'user_id' => $user_id,
                'last_message_at' => date('c'),
                'unread_count' => 1,
                'status' => 'active'
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($session_data),
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $service_key,
                    'Authorization: Bearer ' . $service_key,
                    'Content-Type: application/json',
                    'Prefer: return=representation'
                ]
            ]);
            
            curl_exec($ch);
            curl_close($ch);
        }
        
        error_log("Chat session updated for user: " . $user_id);
    } catch (Exception $e) {
        error_log("Update chat session error: " . $e->getMessage());
    }
}

function markMessagesAsRead($user_id) {
    global $supabase;
    try {
        $update_data = ['is_read' => true];
        $supabase->from('chat_messages')->update($update_data, 'user_id', $user_id);
        
        // Reset unread count in session
        $session_data = ['unread_count' => 0];
        $supabase->from('chat_sessions')->update($session_data, 'user_id', $user_id);
    } catch (Exception $e) {
        error_log("Mark messages read error: " . $e->getMessage());
    }
}

function getUnreadCount($user_id) {
    global $supabase;
    try {
        $session_result = $supabase->from('chat_sessions')->eq('user_id', $user_id);
        return $session_result['data'][0]['unread_count'] ?? 0;
    } catch (Exception $e) {
        error_log("Get unread count error: " . $e->getMessage());
        return 0;
    }
}

// Face verification helper functions
function generateVerificationCode() {
    return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}

function uploadFaceImageToStorage($file, $user_id) {
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        // Validate file
        $max_size = 5 * 1024 * 1024; // 5MB
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        
        if ($file['size'] > $max_size) {
            return ['success' => false, 'error' => 'File size too large. Max 5MB allowed.'];
        }
        
        if (!in_array($file['type'], $allowed_types)) {
            return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, WEBP allowed.'];
        }
        
        // Generate unique filename
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = $user_id . '_' . time() . '.' . $file_ext;
        $storage_path = 'face-verifications/' . $file_name;
        
        // Upload to Supabase Storage
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/storage/v1/object/' . $storage_path;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => file_get_contents($file['tmp_name']),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $service_key,
                'Content-Type: ' . $file['type'],
                'Cache-Control: no-cache'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $public_url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/storage/v1/object/public/face-verifications/' . $file_name;
            return [
                'success' => true, 
                'public_url' => $public_url,
                'storage_path' => $storage_path
            ];
        } else {
            error_log("Storage upload failed: " . $response);
            return ['success' => false, 'error' => 'Failed to upload to storage.'];
        }
    } catch (Exception $e) {
        error_log("Upload face image error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()];
    }
}

function submitFaceVerification($user_id, $public_url, $storage_path) {
    global $supabase;
    try {
        $verification_code = generateVerificationCode();
        
        $verification_data = [
            'user_id' => $user_id,
            'image_url' => $public_url,
            'storage_path' => $storage_path,
            'verification_code' => $verification_code,
            'status' => 'pending'
        ];
        
        $result = $supabase->from('face_verifications')->insert([$verification_data]);
        
        if ($result['status'] === 201) {
            // Update user with verification code
            $supabase->from('users')->update(['verification_code' => $verification_code], 'id', $user_id);
            return ['success' => true, 'code' => $verification_code];
        } else {
            return ['success' => false, 'error' => 'Failed to submit verification.'];
        }
    } catch (Exception $e) {
        error_log("Submit face verification error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Submission failed.'];
    }
}

function deleteFaceImageFromStorage($storage_path) {
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/storage/v1/object/' . $storage_path;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $service_key
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            error_log("✅ Image deleted from storage: " . $storage_path);
            return true;
        } else {
            error_log("❌ Failed to delete image from storage: " . $response);
            return false;
        }
    } catch (Exception $e) {
        error_log("Delete image error: " . $e->getMessage());
        return false;
    }
}

function approveVerification($verification_id, $admin_notes = '') {
    global $supabase;
    try {
        // Get verification details first
        $result = $supabase->from('face_verifications')->eq('id', $verification_id);
        $verification = $result['data'][0] ?? null;
        
        if (!$verification) {
            return ['success' => false, 'error' => 'Verification not found.'];
        }
        
        // Update verification status
        $update_data = [
            'status' => 'approved',
            'admin_notes' => $admin_notes,
            'updated_at' => date('c')
        ];
        
        $update_result = $supabase->from('face_verifications')->update($update_data, 'id', $verification_id);
        
        if ($update_result['status'] === 200) {
            // Update user as verified
            $user_update = $supabase->from('users')->update([
                'face_verified' => true,
                'verification_code' => null
            ], 'id', $verification['user_id']);
            
            // Delete the image from storage
            if (!empty($verification['storage_path'])) {
                deleteFaceImageFromStorage($verification['storage_path']);
            }
            
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to approve verification.'];
        }
    } catch (Exception $e) {
        error_log("Approve verification error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Approval failed.'];
    }
}

function rejectVerification($verification_id, $rejection_reason, $admin_notes = '') {
    global $supabase;
    try {
        // Get verification details first
        $result = $supabase->from('face_verifications')->eq('id', $verification_id);
        $verification = $result['data'][0] ?? null;
        
        if (!$verification) {
            return ['success' => false, 'error' => 'Verification not found.'];
        }
        
        // Update verification status
        $update_data = [
            'status' => 'rejected',
            'rejection_reason' => $rejection_reason,
            'admin_notes' => $admin_notes,
            'updated_at' => date('c')
        ];
        
        $update_result = $supabase->from('face_verifications')->update($update_data, 'id', $verification_id);
        
        if ($update_result['status'] === 200) {
            // Delete the image from storage
            if (!empty($verification['storage_path'])) {
                deleteFaceImageFromStorage($verification['storage_path']);
            }
            
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to reject verification.'];
        }
    } catch (Exception $e) {
        error_log("Reject verification error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Rejection failed.'];
    }
}

// In config.php - Fix the getUserVerificationStatus function
// Add this function to config.php to sync verification status
function syncFaceVerificationStatus($user_id) {
    global $supabase;
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        // Get latest verification
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/face_verifications?user_id=eq.' . $user_id . '&order=created_at.desc&limit=1';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $latest = $data[0] ?? null;
            
            if ($latest) {
                $is_approved = ($latest['status'] === 'approved');
                
                // Update user's face_verified status
                $update_url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/users?id=eq.' . $user_id;
                $update_data = ['face_verified' => $is_approved];
                
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $update_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => 'PATCH',
                    CURLOPT_POSTFIELDS => json_encode($update_data),
                    CURLOPT_HTTPHEADER => [
                        'apikey: ' . $service_key,
                        'Authorization: Bearer ' . $service_key,
                        'Content-Type: application/json',
                        'Prefer: return=representation'
                    ]
                ]);
                
                curl_exec($ch);
                curl_close($ch);
                
                return $is_approved;
            }
        }
        return false;
    } catch (Exception $e) {
        error_log("Sync face verification error: " . $e->getMessage());
        return false;
    }
}

// Update this function in config.php
function getUserVerificationStatus($user_id) {
    global $supabase;
    try {
        $result = $supabase->from('face_verifications')->eq('user_id', $user_id);
        $verifications = $result['data'] ?? [];
        
        if (empty($verifications)) {
            return ['status' => 'none', 'code' => null];
        }
        
        // Get the most recent verification
        usort($verifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        $latest = $verifications[0];
        
        // If verification is approved, make sure user's face_verified is true
        if ($latest['status'] === 'approved') {
            // Double-check that user's face_verified is true
            $userResult = $supabase->from('users')->eq('id', $user_id);
            $user = $userResult['data'][0] ?? null;
            
            if ($user && !$user['face_verified']) {
                // Update user's face_verified status
                $supabase->from('users')->update(['face_verified' => true], 'id', $user_id);
            }
        }
        
        return [
            'status' => $latest['status'],
            'code' => $latest['verification_code'],
            'image_url' => $latest['image_url'],
            'rejection_reason' => $latest['rejection_reason'] ?? null,
            'created_at' => $latest['created_at']
        ];
    } catch (Exception $e) {
        error_log("Get verification status error: " . $e->getMessage());
        return ['status' => 'error', 'code' => null];
    }
}

function getPendingVerifications() {
    global $supabase;
    try {
        $result = $supabase->from('face_verifications')->eq('status', 'pending');
        return $result['data'] ?? [];
    } catch (Exception $e) {
        error_log("Get pending verifications error: " . $e->getMessage());
        return [];
    }
}

// FIXED: Get only active tasks (not archived)
function getActiveTasks() {
    global $supabase;
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/tasks?status=eq.active&archived_at=is.null&order=created_at.desc';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data ?: [];
        }
        return [];
    } catch (Exception $e) {
        error_log("Get active tasks error: " . $e->getMessage());
        return [];
    }
}

function getUserTaskSubmissions($user_id) {
    global $supabase;
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/task_submissions?user_id=eq.' . $user_id;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: ' . 'application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data ?: [];
        }
        return [];
    } catch (Exception $e) {
        error_log("Get user submissions error: " . $e->getMessage());
        return [];
    }
}

function submitTaskProof($user_id, $task_id, $proof_file) {
    try {
        // Upload proof image
        $upload_result = uploadTaskProofToStorage($proof_file, $user_id, $task_id);
        if (!$upload_result['success']) {
            return ['success' => false, 'error' => $upload_result['error']];
        }
        
        // Create submission record
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $submission_data = [
            'task_id' => $task_id,
            'user_id' => $user_id,
            'proof_url' => $upload_result['public_url'],
            'status' => 'pending'
        ];
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/task_submissions';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($submission_data),
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: ' . 'application/json',
                'Prefer: return=representation'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            return ['success' => true];
        } else {
            // Delete uploaded file if submission failed
            deleteTaskProofFromStorage($upload_result['storage_path']);
            return ['success' => false, 'error' => 'Failed to submit proof'];
        }
    } catch (Exception $e) {
        error_log("Submit task proof error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Submission failed'];
    }
}

function uploadTaskProofToStorage($file, $user_id, $task_id) {
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $max_size = 5 * 1024 * 1024;
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        
        if ($file['size'] > $max_size) {
            return ['success' => false, 'error' => 'File size too large. Max 5MB allowed.'];
        }
        
        if (!in_array($file['type'], $allowed_types)) {
            return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, WEBP allowed.'];
        }
        
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = $user_id . '_' . $task_id . '_' . time() . '.' . $file_ext;
        $storage_path = 'task-proofs/' . $file_name;
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/storage/v1/object/' . $storage_path;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => file_get_contents($file['tmp_name']),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $service_key,
                'Content-Type: ' . $file['type'],
                'Cache-Control: no-cache'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $public_url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/storage/v1/object/public/task-proofs/' . $file_name;
            return [
                'success' => true, 
                'public_url' => $public_url,
                'storage_path' => $storage_path
            ];
        } else {
            error_log("Task proof upload failed: " . $response);
            return ['success' => false, 'error' => 'Failed to upload proof'];
        }
    } catch (Exception $e) {
        error_log("Upload task proof error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Upload failed'];
    }
}

function deleteTaskProofFromStorage($storage_path) {
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/storage/v1/object/' . $storage_path;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $service_key
            ]
        ]);
        
        curl_exec($ch);
        curl_close($ch);
        return true;
    } catch (Exception $e) {
        error_log("Delete task proof error: " . $e->getMessage());
        return false;
    }
}

// FIXED: Check if user can submit task (allows resubmission for rejected tasks)
function hasUserSubmittedTask($user_id, $task_id) {
    $submissions = getUserTaskSubmissions($user_id);
    foreach ($submissions as $submission) {
        if ($submission['task_id'] === $task_id) {
            // Only return if the submission is pending or approved
            // Allow resubmission for rejected tasks
            if ($submission['status'] === 'pending' || $submission['status'] === 'approved') {
                return $submission;
            }
        }
    }
    return false;
}

// NEW: Function to get user submissions with task info even for deleted tasks
function getUserTaskSubmissionsWithInfo($user_id) {
    global $supabase;
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/task_submissions?user_id=eq.' . $user_id;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: ' . 'application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data ?: [];
        }
        return [];
    } catch (Exception $e) {
        error_log("Get user submissions error: " . $e->getMessage());
        return [];
    }
}

function getTaskStats($user_id) {
    $submissions = getUserTaskSubmissions($user_id);
    $completed = 0;
    $pending = 0;
    $total_earned = 0;
    
    foreach ($submissions as $submission) {
        if ($submission['status'] === 'approved') {
            $completed++;
            // Get task reward amount
            $task = getTaskById($submission['task_id']);
            if ($task) {
                $total_earned += $task['reward_amount'];
            }
        } elseif ($submission['status'] === 'pending') {
            $pending++;
        }
    }
    
    return [
        'completed' => $completed,
        'pending' => $pending,
        'total_earned' => $total_earned
    ];
}

function getTaskById($task_id) {
    global $supabase;
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/tasks?id=eq.' . $task_id;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: ' . 'application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data[0] ?? null;
        }
        return null;
    } catch (Exception $e) {
        error_log("Get task by ID error: " . $e->getMessage());
        return null;
    }
}

// Bank details helper functions
function getUserBankDetails($user_id) {
    global $supabase;
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/bank_details?user_id=eq.' . $user_id . '&order=is_default.desc,created_at.desc';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data ?: [];
        }
        return [];
    } catch (Exception $e) {
        error_log("Get user bank details error: " . $e->getMessage());
        return [];
    }
}

function addBankAccount($user_id, $bank_name, $account_name, $account_number) {
    global $supabase;
    try {
        // Validate account number (10 digits)
        if (!preg_match('/^\d{10}$/', $account_number)) {
            return ['success' => false, 'error' => 'Account number must be exactly 10 digits'];
        }
        
        // Check if user already has 3 banks (limit)
        $existing_banks = getUserBankDetails($user_id);
        if (count($existing_banks) >= 3) {
            return ['success' => false, 'error' => 'You can only add up to 3 bank accounts'];
        }
        
        // Check if account number already exists for this user
        foreach ($existing_banks as $bank) {
            if ($bank['account_number'] === $account_number) {
                return ['success' => false, 'error' => 'This account number is already saved'];
            }
        }
        
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        // If this is the first bank, set it as default
        $is_default = empty($existing_banks);
        
        $bank_data = [
            'user_id' => $user_id,
            'bank_name' => $bank_name,
            'account_name' => $account_name,
            'account_number' => $account_number,
            'is_default' => $is_default,
            'is_verified' => false // Auto-verify for simplicity
        ];
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/bank_details';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($bank_data),
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            return ['success' => true];
        } else {
            error_log("Add bank account failed: " . $response);
            return ['success' => false, 'error' => 'Failed to add bank account'];
        }
    } catch (Exception $e) {
        error_log("Add bank account error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to add bank account'];
    }
}

function updateBankAccount($bank_id, $user_id, $bank_name, $account_name, $account_number) {
    global $supabase;
    try {
        // Validate account number (10 digits)
        if (!preg_match('/^\d{10}$/', $account_number)) {
            return ['success' => false, 'error' => 'Account number must be exactly 10 digits'];
        }
        
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $update_data = [
            'bank_name' => $bank_name,
            'account_name' => $account_name,
            'account_number' => $account_number,
            'updated_at' => date('c')
        ];
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/bank_details?id=eq.' . $bank_id . '&user_id=eq.' . $user_id;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode($update_data),
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return ['success' => true];
        } else {
            error_log("Update bank account failed: " . $response);
            return ['success' => false, 'error' => 'Failed to update bank account'];
        }
    } catch (Exception $e) {
        error_log("Update bank account error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to update bank account'];
    }
}

function deleteBankAccount($bank_id, $user_id) {
    global $supabase;
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/bank_details?id=eq.' . $bank_id . '&user_id=eq.' . $user_id;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 204) {
            return ['success' => true];
        } else {
            error_log("Delete bank account failed: " . $response);
            return ['success' => false, 'error' => 'Failed to delete bank account'];
        }
    } catch (Exception $e) {
        error_log("Delete bank account error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to delete bank account'];
    }
}

function setDefaultBankAccount($bank_id, $user_id) {
    global $supabase;
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        // First, set all user's banks to not default
        $reset_data = ['is_default' => false];
        $reset_url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/bank_details?user_id=eq.' . $user_id;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $reset_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode($reset_data),
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json'
            ]
        ]);
        
        curl_exec($ch);
        curl_close($ch);
        
        // Now set the selected bank as default
        $update_data = ['is_default' => true];
        $update_url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/bank_details?id=eq.' . $bank_id . '&user_id=eq.' . $user_id;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $update_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode($update_data),
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return ['success' => true];
        } else {
            error_log("Set default bank failed: " . $response);
            return ['success' => false, 'error' => 'Failed to set default bank'];
        }
    } catch (Exception $e) {
        error_log("Set default bank error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to set default bank'];
    }
}

// Add these functions to config.php

// Withdrawal settings functions
function getWithdrawalSettings() {
    global $supabase;
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/withdrawal_settings?id=eq.default-settings';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data[0] ?? [
                'is_live' => false,
                'min_amount' => 10000,
                'max_amount' => 10000,
                'required_approved_tasks' => 5,
                'require_face_verification' => true,
                'once_per_day' => true
            ];
        }
        return [
            'is_live' => false,
            'min_amount' => 10000,
            'max_amount' => 10000,
            'required_approved_tasks' => 5,
            'require_face_verification' => true,
            'once_per_day' => true
        ];
    } catch (Exception $e) {
        error_log("Get withdrawal settings error: " . $e->getMessage());
        return [
            'is_live' => false,
            'min_amount' => 10000,
            'max_amount' => 10000,
            'required_approved_tasks' => 5,
            'require_face_verification' => true,
            'once_per_day' => true
        ];
    }
}

function checkWithdrawalEligibility($user_id, $settings) {
    global $supabase;
    
    try {
        // Get user data
        $userResult = $supabase->from('users')->eq('id', $user_id);
        $user = $userResult['data'][0] ?? null;
        
        if (!$user) {
            return ['can_withdraw' => false, 'message' => 'User not found'];
        }
        
        // Get approved tasks count
        $submissions = getUserTaskSubmissions($user_id);
        $approved_tasks = 0;
        foreach ($submissions as $submission) {
            if ($submission['status'] === 'approved') {
                $approved_tasks++;
            }
        }
        
        // Check if already withdrew today
        $today_withdrawal = false;
        if ($settings['once_per_day']) {
            $today = date('Y-m-d');
            $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
            
            $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/withdrawals?user_id=eq.' . $user_id . '&withdrawal_date=eq.' . $today;
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $service_key,
                    'Authorization: Bearer ' . $service_key,
                    'Content-Type: application/json'
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                $today_withdrawal = !empty($data);
            }
        }
        
        // Calculate balances
        $cashback_balance = $user['cashback_balance'] ?? 0;
        $referral_balance = $user['wallet_balance'] ?? 0;
        $total_balance = $cashback_balance + $referral_balance;
        
        // Check requirements
        $requirements = [];
        
        if (!$settings['is_live']) {
            $requirements[] = 'Withdrawal is currently closed';
        }
        
        if ($settings['require_face_verification'] && !$user['face_verified']) {
            $requirements[] = 'Face verification required';
        }
        
        if ($approved_tasks < $settings['required_approved_tasks']) {
            $requirements[] = 'Need ' . $settings['required_approved_tasks'] . ' approved tasks (you have ' . $approved_tasks . ')';
        }
        
        if ($total_balance < $settings['min_amount']) {
            $requirements[] = 'Minimum balance of ₦' . number_format($settings['min_amount']) . ' required';
        }
        
        if ($today_withdrawal) {
            $requirements[] = 'You can only withdraw once per day';
        }
        
        $can_withdraw = empty($requirements) && $settings['is_live'];
        
        return [
            'can_withdraw' => $can_withdraw,
            'message' => $can_withdraw ? 'Eligible for withdrawal' : implode(', ', $requirements),
            'cashback_balance' => $cashback_balance,
            'referral_balance' => $referral_balance,
            'total_balance' => $total_balance,
            'approved_tasks' => $approved_tasks,
            'face_verified' => $user['face_verified'] ?? false,
            'today_withdrawal' => $today_withdrawal
        ];
        
    } catch (Exception $e) {
        error_log("Check withdrawal eligibility error: " . $e->getMessage());
        return ['can_withdraw' => false, 'message' => 'System error'];
    }
}

function processWithdrawal($user_id, $amount, $bank_id) {
    global $supabase;
    
    try {
        // Get user data and eligibility
        $settings = getWithdrawalSettings();
        $eligibility = checkWithdrawalEligibility($user_id, $settings);
        
        if (!$eligibility['can_withdraw']) {
            return ['success' => false, 'error' => $eligibility['message']];
        }
        
        if ($amount < $settings['min_amount'] || $amount > $settings['max_amount']) {
            return ['success' => false, 'error' => 'Invalid amount'];
        }
        
        if ($amount > $eligibility['total_balance']) {
            return ['success' => false, 'error' => 'Insufficient balance'];
        }
        
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        // Calculate how to split the amount between cashback and referral
        $cashback_used = min($amount, $eligibility['cashback_balance']);
        $referral_used = $amount - $cashback_used;
        
        // Create withdrawal record
        $withdrawal_data = [
            'user_id' => $user_id,
            'amount' => $amount,
            'status' => 'pending',
            'bank_details_id' => $bank_id,
            'withdrawal_date' => date('Y-m-d')
        ];
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/withdrawals';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($withdrawal_data),
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            // Update user balances
            $userResult = $supabase->from('users')->eq('id', $user_id);
            $user = $userResult['data'][0] ?? null;
            
            if ($user) {
                $new_cashback = max(0, ($user['cashback_balance'] ?? 0) - $cashback_used);
                $new_referral = max(0, ($user['wallet_balance'] ?? 0) - $referral_used);
                
                $update_data = [
                    'cashback_balance' => $new_cashback,
                    'wallet_balance' => $new_referral
                ];
                
                $supabase->from('users')->update($update_data, 'id', $user_id);
            }
            
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to create withdrawal record'];
        }
        
    } catch (Exception $e) {
        error_log("Process withdrawal error: " . $e->getMessage());
        return ['success' => false, 'error' => 'System error'];
    }
}

function getWithdrawalHistory($user_id) {
    global $supabase;
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/withdrawals?user_id=eq.' . $user_id . '&order=created_at.desc&limit=10';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data ?: [];
        }
        return [];
    } catch (Exception $e) {
        error_log("Get withdrawal history error: " . $e->getMessage());
        return [];
    }
}

// IP and device tracking functions
function getClientIP() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    // Handle multiple IPs in X_FORWARDED_FOR
    if (strpos($ip, ',') !== false) {
        $ips = explode(',', $ip);
        $ip = trim($ips[0]);
    }
    
    return $ip;
}

function getDeviceFingerprint() {
    $components = [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
    ];
    
    return md5(implode('|', $components));
}

function trackUserIP($user_id, $ip_address, $device_fingerprint, $type = 'registration') {
    global $supabase;
    try {
        // Use service role key
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/ip_associations';
        
        $data = [
            'ip_address' => $ip_address,
            'user_id' => $user_id,
            'association_type' => $type
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]
        ]);
        
        curl_exec($ch);
        curl_close($ch);
        
        return true;
    } catch (Exception $e) {
        error_log("Track IP error: " . $e->getMessage());
        return false;
    }
}

function getMultipleAccountsCount($user_id) {
    global $supabase;
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        // First get user's IPs
        $user_ips_url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/ip_associations?user_id=eq.' . $user_id . '&select=ip_address';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $user_ips_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $user_ips_data = json_decode($response, true);
            $user_ips = array_column($user_ips_data, 'ip_address');
            
            if (empty($user_ips)) {
                return 0;
            }
            
            // Count unique users from these IPs (excluding current user)
            $unique_users = [];
            
            foreach ($user_ips as $ip) {
                $ip_users_url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/ip_associations?ip_address=eq.' . $ip . '&select=user_id';
                
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $ip_users_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'apikey: ' . $service_key,
                        'Authorization: Bearer ' . $service_key,
                        'Content-Type: application/json'
                    ]
                ]);
                
                $ip_response = curl_exec($ch);
                $ip_httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($ip_httpCode === 200) {
                    $ip_users_data = json_decode($ip_response, true);
                    $ip_users = array_column($ip_users_data, 'user_id');
                    
                    foreach ($ip_users as $user_id_from_ip) {
                        if ($user_id_from_ip !== $user_id && !in_array($user_id_from_ip, $unique_users)) {
                            $unique_users[] = $user_id_from_ip;
                        }
                    }
                }
            }
            
            return count($unique_users);
        }
        
        return 0;
    } catch (Exception $e) {
        error_log("Get multiple accounts count error: " . $e->getMessage());
        return 0;
    }
}

// Add this debug function to config.php
function debugVerificationSession($user_id) {
    global $supabase;
    try {
        $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
        
        $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/telegram_verification_sessions?user_id=eq.' . $user_id . '&order=created_at.desc&limit=5';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $service_key,
                'Authorization: Bearer ' . $service_key,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("Debug sessions - HTTP: $httpCode, Response: " . $response);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data;
        }
        return [];
    } catch (Exception $e) {
        error_log("Debug sessions error: " . $e->getMessage());
        return [];
    }
}
?>