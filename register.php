<?php
// register.php
require_once 'config.php';

$error = '';
$success = '';

// Get referral code from URL if present
$referral_code_from_url = $_GET['ref'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm'] ?? '';
    $referral_code = trim($_POST['referral_code'] ?? '');
    
    // Validation
    if (empty($name) || empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        try {
            // Check if username exists
            $usernameCheck = $supabase->from('users')->eq('username', $username);
            
            if (isset($usernameCheck['data']) && is_array($usernameCheck['data']) && count($usernameCheck['data']) > 0) {
                $error = 'Username already exists';
            } else {
                // Check if email exists
                $emailCheck = $supabase->from('users')->eq('email', $email);
                
                if (isset($emailCheck['data']) && is_array($emailCheck['data']) && count($emailCheck['data']) > 0) {
                    $error = 'Email already exists';
                } else {
                    if (!$error) {
                        // Use Admin API to create user without email confirmation
                        $userCreationResult = createUserWithAdminAPI($email, $password, $name, $username);
                        
                        if ($userCreationResult['success']) {
                            $user_id = $userCreationResult['user_id'];
                            
                            // Create user profile using service role key
                            $userData = [
                                'id' => $user_id,
                                'name' => $name,
                                'username' => $username,
                                'email' => $email,
                                'referral_code' => generateReferralCode(),
                                'referred_by' => !empty($referral_code) ? $referral_code : null,
                                'balance' => 0,
                                'wallet_balance' => 0,
                                'cashback_balance' => 0,
                                'tasks_completed' => 0,
                                'referrals_count' => 0,
                                'role' => 'user',
                                'status' => 'active'
                            ];
                            
                            // Create user profile
                            $profileResult = createUserProfile($userData);
                            
                            if ($profileResult['success']) {
                                // Track IP and device
                                $ip_address = getClientIP();
                                $device_fingerprint = getDeviceFingerprint();
        
                                // Update user with IP and device info
                                $updateData = [
                                    'registration_ip' => $ip_address,
                                    'last_login_ip' => $ip_address,
                                    'device_fingerprint' => $device_fingerprint
                                ];
        
                                // Use service role to update user
                                $update_url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/users?id=eq.' . $user_id;
                                $ch = curl_init();
                                curl_setopt_array($ch, [
                                    CURLOPT_URL => $update_url,
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_CUSTOMREQUEST => 'PATCH',
                                    CURLOPT_POSTFIELDS => json_encode($updateData),
                                    CURLOPT_HTTPHEADER => [
                                        'apikey: ' . $service_key,
                                        'Authorization: Bearer ' . $service_key,
                                        'Content-Type: application/json',
                                        'Prefer: return=representation'
                                    ]
                                ]);
                                curl_exec($ch);
                                curl_close($ch);
        
                                // Track IP association
                                trackUserIP($user_id, $ip_address, $device_fingerprint, 'registration');
        
                                $success = 'Registration successful! You can now login.';
                                
                                // Clear form
                                $name = $username = $email = $referral_code = '';
                            } else {
                                $error = $profileResult['error'];
                            }
                        } else {
                            $error = $userCreationResult['error'];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}

// Function to create user using Admin API (bypasses email confirmation)
function createUserWithAdminAPI($email, $password, $name, $username) {
    $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
    
    $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/auth/v1/admin/users';
    
    $user_data = [
        'email' => $email,
        'password' => $password,
        'email_confirm' => true, // Auto-confirm email
        'user_metadata' => [
            'name' => $name,
            'username' => $username
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($user_data),
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
        return [
            'success' => true,
            'user_id' => $data['id'] ?? null
        ];
    } else {
        error_log("Admin API user creation failed - HTTP $httpCode: " . $response);
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['msg'] ?? $errorData['error'] ?? 'Failed to create user account';
        return [
            'success' => false,
            'error' => $errorMsg
        ];
    }
}

// Function to create user profile using service role key (bypasses RLS)
function createUserProfile($userData) {
    $service_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhxbmpob3lkYXN6emFtdXF2Z2lxIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc2NDUxMzY0NCwiZXhwIjoyMDgwMDg5NjQ0fQ.O7oyX9-9SocPDlnf_Da-O79oH95u1-kr80BcAoIa4O8';
    
    $url = 'https://hqnjhoydaszzamuqvgiq.supabase.co/rest/v1/users';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($userData),
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
        // Parse the error response to provide user-friendly messages
        $errorData = json_decode($response, true);
        $cleanError = 'Failed to create profile';
        
        if (isset($errorData['message'])) {
            if (strpos($errorData['message'], 'username') !== false) {
                $cleanError = 'Username already exists';
            } elseif (strpos($errorData['message'], 'email') !== false) {
                $cleanError = 'Email already exists';
            } else {
                $cleanError = $errorData['message'];
            }
        }
        
        return ['success' => false, 'error' => $cleanError];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Ninja Hope</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Your existing CSS styles remain the same */
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
            --warning-bg: #332701;
            --warning-text: #fbbf24;
            --error-bg: #2c0b07;
            --error-text: #f87171;
            --success-bg: #0d3019;
            --success-text: #4ade80;
        }
        
        * { 
            margin:0; 
            padding:0; 
            box-sizing:border-box; 
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--dim-black);
            padding: 2rem 1rem;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
        }
        
        .register-container {
            max-width: 420px;
            width: 100%;
            background: var(--dark-gray);
            padding: 2.5rem 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            border: 1px solid var(--light-gray);
            position: relative;
            overflow: hidden;
        }
        
        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top: 2px solid var(--accent-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            opacity: 0;
        }
        
        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--dark-green), var(--dark-purple));
        }
        
        h2 {
            text-align: center;
            color: var(--text-light);
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            font-weight: 700;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--dark-green), var(--dark-purple));
            border-radius: 3px;
        }
        
        .logo-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .logo-icon {
            font-size: 3rem;
            color: var(--accent-green);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .warning {
            background: var(--warning-bg);
            color: var(--warning-text);
            padding: 1.2rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            font-size: 0.9rem;
            border-left: 4px solid var(--warning-text);
            line-height: 1.5;
        }
        
        .warning strong {
            color: var(--warning-text);
        }
        
        .form-group {
            margin-bottom: 1.2rem;
            position: relative;
        }
        
        input {
            display: block;
            width: 100%;
            margin: 0;
            padding: 14px 16px;
            font-size: 1rem;
            border: 1px solid var(--light-gray);
            border-radius: 12px;
            background: var(--dim-black);
            color: var(--text-light);
            transition: all 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 0 2px rgba(30, 132, 73, 0.2);
        }
        
        input::placeholder {
            color: var(--text-muted);
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        
        .input-icon input {
            padding-left: 45px;
        }
        
        button {
            padding: 14px;
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white;
            border: none;
            cursor: pointer;
            width: 100%;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
        }
        
        .error {
            color: var(--error-text);
            margin: 5px 0;
            font-size: 0.9rem;
            background: var(--error-bg);
            padding: 0.8rem;
            border-radius: 8px;
            border-left: 4px solid var(--error-text);
        }
        
        .success {
            color: var(--success-text);
            margin: 10px 0;
            font-size: 0.9rem;
            background: var(--success-bg);
            padding: 0.8rem;
            border-radius: 8px;
            border-left: 4px solid var(--success-text);
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .login-link a {
            color: var(--accent-green);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .login-link a:hover {
            color: var(--accent-purple);
            text-decoration: underline;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .strength-bar {
            height: 4px;
            background: var(--light-gray);
            border-radius: 2px;
            margin-top: 0.3rem;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #e74c3c; width: 33%; }
        .strength-medium { background: #f39c12; width: 66%; }
        .strength-strong { background: var(--accent-green); width: 100%; }
        
        /* Referral code info */
        .referral-info {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.3rem;
        }

        /* Auto-filled referral code style */
        .referral-auto-filled {
            background: var(--accent-green);
            color: white;
            padding: 0.5rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-align: center;
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .register-container {
                padding: 2rem 1.5rem;
            }
            
            h2 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo-header">
            <i class="fas fa-user-ninja logo-icon"></i>
            <h2>Create Account</h2>
        </div>
        
        <div class="warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Warning:</strong> Fake accounts or multiple accounts on the same device will be 
            <strong>banned</strong> and 
            <strong>will not be paid</strong>.
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- Show message if referral code was auto-filled -->
        <?php if (!empty($referral_code_from_url)): ?>
            <div class="referral-auto-filled">
                <i class="fas fa-gift"></i> Referral code auto-filled! Your friend will earn ₦500 when you join.
            </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <input type="text" name="name" placeholder="Full Name" required value="<?php echo htmlspecialchars($name ?? ''); ?>">
            </div>
            
            <div class="form-group input-icon">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="Username" required value="<?php echo htmlspecialchars($username ?? ''); ?>">
            </div>
            
            <div class="form-group input-icon">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>
            
            <div class="form-group input-icon">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required id="password">
            </div>
            
            <div class="password-strength">
                Password strength: <span id="strength-text">None</span>
                <div class="strength-bar">
                    <div class="strength-fill" id="strength-fill"></div>
                </div>
            </div>
            
            <div class="form-group input-icon">
                <i class="fas fa-lock"></i>
                <input type="password" name="confirm" placeholder="Confirm Password" required>
            </div>
            
            <div class="form-group">
                <input type="text" name="referral_code" placeholder="Referral Code (Optional)" 
                       value="<?php echo htmlspecialchars($referral_code_from_url ?: ($referral_code ?? '')); ?>"
                       <?php echo !empty($referral_code_from_url) ? 'readonly' : ''; ?>>
                <div class="referral-info">
                    <?php if (!empty($referral_code_from_url)): ?>
                        <i class="fas fa-link"></i> Referral code from your friend's link
                    <?php else: ?>
                        If you have a referral code, enter it here. Your friend will earn ₦500 when you join!
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="submit">
                <i class="fas fa-user-plus"></i> Register Now
            </button>
        </form>
        
        <div class="login-link">
            Have an account? <a href="login.php">Login here</a>
        </div>
    </div>

    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthText = document.getElementById('strength-text');
        const strengthFill = document.getElementById('strength-fill');
        const form = document.querySelector('form');
        const submitButton = form.querySelector('button[type="submit"]');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let text = 'None';
    
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
    
            // Remove all classes
            strengthFill.className = 'strength-fill';
    
            if (password.length === 0) {
                text = 'None';
            } else if (strength <= 1) {
                text = 'Weak';
                strengthFill.classList.add('strength-weak');
            } else if (strength <= 3) {
                text = 'Medium';
                strengthFill.classList.add('strength-medium');
            } else {
                text = 'Strong';
                strengthFill.classList.add('strength-strong');
            }
    
            strengthText.textContent = text;
        });

        // Form submission loading state
        form.addEventListener('submit', function() {
            submitButton.classList.add('loading');
            submitButton.querySelector('i').classList.remove('fa-user-plus');
            submitButton.querySelector('i').classList.add('fa-spinner', 'fa-spin');
            submitButton.disabled = true;
        });
    </script>
</body>
</html>