<?php
// login.php
require_once 'config.php';

$error = '';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // Validation
    if (empty($identifier) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        try {
            // Determine if identifier is email or username
            if (strpos($identifier, '@') !== false) {
                // It's an email - sign in directly
                $result = $supabase->signIn($identifier, $password);
            } else {
                // It's a username - look up email first
                $userLookup = $supabase->from('users')->eq('username', $identifier);
                
                if (isset($userLookup['data']) && is_array($userLookup['data']) && count($userLookup['data']) > 0) {
                    $email = $userLookup['data'][0]['email'];
                    $result = $supabase->signIn($email, $password);
                } else {
                    $error = 'Invalid email or password';
                }
            }
            
            // Check if login was successful
            // After successful login, update IP tracking
            if (!$error && isset($result['data']['access_token'])) {
                // Set session variables
                $_SESSION['user'] = $result['data']['user'];
                $_SESSION['access_token'] = $result['data']['access_token'];
                $_SESSION['last_activity'] = time();
                $_SESSION['remember_me'] = $remember_me;
    
                // Track login IP
                $user_id = $result['data']['user']['id'];
                $ip_address = getClientIP();
                $device_fingerprint = getDeviceFingerprint();
    
                // Update user's last login IP
                $updateData = ['last_login_ip' => $ip_address];
                $supabase->from('users')->update($updateData, 'id', $user_id);
    
                // Track this login IP association
                trackUserIP($user_id, $ip_address, $device_fingerprint, 'login');

                // If "Remember Me" is checked, update session cookie
                if ($remember_me) {
                    // Update session cookie to last 6 hours
                    setcookie(session_name(), session_id(), time() + 6 * 60 * 60, '/');
                } else {
                    // Session expires when browser closes
                    setcookie(session_name(), session_id(), 0, '/');
                }
                
                // Login successful - redirect to dashboard
                redirect('dashboard.php');
            } else {
                if (!$error) {
                    $error = 'Invalid email or password';
                }
            }
            
        } catch (Exception $e) {
            $error = 'Login failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ninja Hope</title>
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
            --error-bg: #2c0b07;
            --error-text: #f87171;
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
        
        .login-container {
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
        
        .login-container::before {
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
            margin-bottom: 2rem;
        }
        
        .logo-icon {
            font-size: 3rem;
            color: var(--accent-green);
            margin-bottom: 0.5rem;
            display: block;
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
            margin: 10px 0;
            font-size: 0.9rem;
            background: var(--error-bg);
            padding: 0.8rem;
            border-radius: 8px;
            border-left: 4px solid var(--error-text);
            text-align: center;
        }
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .register-link a {
            color: var(--accent-green);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .register-link a:hover {
            color: var(--accent-purple);
            text-decoration: underline;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin: 2rem 0 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--light-gray);
        }
        
        .feature-item {
            text-align: center;
            padding: 1rem 0.5rem;
            background: var(--dim-black);
            border-radius: 12px;
            border: 1px solid var(--light-gray);
            transition: all 0.3s ease;
        }
        
        .feature-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .feature-item i {
            font-size: 1.5rem;
            color: var(--accent-green);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .feature-item span {
            font-size: 0.8rem;
            color: var(--text-light);
            font-weight: 500;
        }

        /* Remember Me checkbox */
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .remember-me input[type="checkbox"] {
            width: auto;
            transform: scale(1.2);
        }
        
        .remember-me label {
            cursor: pointer;
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .login-container {
                padding: 2rem 1.5rem;
            }
            
            h2 {
                font-size: 1.6rem;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-header">
            <i class="fas fa-user-ninja logo-icon"></i>
            <h2>Welcome Back</h2>
            <p style="color: var(--text-muted); text-align: center;">Sign in to your Ninja Hope account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group input-icon">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="Email or Username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group input-icon">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <div class="remember-me">
                <input type="checkbox" id="remember_me" name="remember_me" value="1">
                <label for="remember_me">Remember me for 6 hours</label>
            </div>
            
            <button type="submit">
                <i class="fas fa-sign-in-alt"></i> Login to Account
            </button>
        </form>
        
        <div class="register-link">
            No account? <a href="register.php">Create one here</a>
        </div>
        
        <div class="features">
            <div class="feature-item">
                <i class="fas fa-shield-alt"></i>
                <span>Secure Login</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-bolt"></i>
                <span>Fast Access</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-wallet"></i>
                <span>Earn Money</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-users"></i>
                <span>Build Team</span>
            </div>
        </div>
    </div>

    <script>
        // Add subtle animation to form elements
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input');
            inputs.forEach((input, index) => {
                input.style.opacity = '0';
                input.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    input.style.transition = 'all 0.5s ease';
                    input.style.opacity = '1';
                    input.style.transform = 'translateY(0)';
                }, 100 * index);
            });
        });
    </script>
</body>
</html>