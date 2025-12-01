<?php
// about.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$user_id = $user['id'] ?? null;

// Fetch referral data
$referral_data = [];
$referral_history = [];
$total_earnings = 0;

try {
    // Get user's referral code and stats
    $userResult = $supabase->from('users')->eq('id', $user_id);
    if (isset($userResult['data'][0])) {
        $referral_data = $userResult['data'][0];
        $user_data = $userResult['data'][0]; // Keep this for wallet balance
    }
    
    // Get referral history
    $historyResult = $supabase->from('referral_history')->eq('referrer_id', $user_id);
    if (isset($historyResult['data'])) {
        $referral_history = $historyResult['data'];
        $total_earnings = array_sum(array_column($referral_history, 'amount'));
    }
} catch (Exception $e) {
    error_log("About page error: " . $e->getMessage());
}

// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Ninja Hope</title>
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
            --warning-bg: #332701;
            --warning-text: #fbbf24;
        }
        
        * { 
            margin:0; 
            padding:0; 
            box-sizing:border-box; 
        }
        
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: var(--dim-black); 
            padding-bottom: 80px; 
            color: var(--text-light);
        }
        
        .header { 
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white; 
            padding: 1.2rem; 
            text-align: center; 
            position: relative; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
        }
        
        .header h1 {
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .back-btn { 
            position: absolute; 
            left: 1rem; 
            top: 50%; 
            transform: translateY(-50%); 
            color: white; 
            font-size: 1.4rem; 
            text-decoration: none;
            width: 40px;
            height: 40px;
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
        
        .container { 
            padding: 1.5rem; 
        }
        
        .card { 
            background: var(--dark-gray); 
            border-radius: 16px; 
            padding: 1.5rem; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.2); 
            line-height: 1.7; 
            color: var(--text-light); 
            margin-bottom: 1.5rem;
            border: 1px solid var(--light-gray);
        }
        
        .card p {
            margin-bottom: 1rem;
        }
        
        .highlight { 
            color: var(--accent-green); 
            font-weight: 600; 
        }
        
        .warning { 
            background: var(--warning-bg); 
            color: var(--warning-text); 
            padding: 1rem; 
            border-radius: 12px; 
            margin: 1.5rem 0; 
            font-weight: 600; 
            text-align: center;
            border-left: 4px solid var(--warning-text);
        }
        
        .stats { 
            display: flex; 
            justify-content: space-around; 
            text-align: center; 
            margin: 1.5rem 0; 
            background: var(--dim-black);
            padding: 1.5rem 1rem;
            border-radius: 12px;
            border: 1px solid var(--light-gray);
        }
        
        .stat-value { 
            font-size: 1.5rem; 
            font-weight: 700; 
            color: var(--accent-green); 
            margin-bottom: 0.3rem;
        }
        
        .stat-label { 
            font-size: 0.9rem; 
            color: var(--text-muted); 
        }
        
        .bottom-nav { 
            position: fixed; 
            bottom: 0; 
            left: 0; 
            right: 0; 
            background: var(--dark-gray); 
            display: flex; 
            justify-content: space-around; 
            padding: 0.8rem 0; 
            box-shadow: 0 -5px 20px rgba(0,0,0,0.4); 
            border-top: 1px solid var(--light-gray);
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            overflow: hidden;
            z-index: 100;
        }
        
        .nav-item { 
            text-align: center; 
            color: var(--text-muted); 
            font-size: 0.75rem; 
            text-decoration: none; 
            flex: 1; 
            padding: 0.5rem 0;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .nav-item i { 
            display: block; 
            font-size: 1.4rem; 
            margin-bottom: 0.3rem; 
            transition: all 0.3s ease;
        }
        
        .nav-item.active { 
            color: var(--accent-green); 
            font-weight: 600; 
        }
        
        .nav-item.active i {
            transform: scale(1.2);
        }
        
        .nav-item::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: var(--accent-green);
            border-radius: 2px;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .nav-item.active::after {
            width: 30px;
        }
        
        /* Feature list styling */
        .feature-list {
            margin: 1.5rem 0;
            padding-left: 1rem;
        }
        
        .feature-item {
            margin-bottom: 0.8rem;
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
        }
        
        .feature-item i {
            color: var(--accent-green);
            margin-top: 0.2rem;
            flex-shrink: 0;
        }
        
        /* Mission section */
        .mission {
            background: linear-gradient(135deg, rgba(10, 92, 54, 0.2), rgba(74, 35, 90, 0.2));
            padding: 1.2rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            border-left: 4px solid var(--accent-green);
        }
        
        .mission-title {
            font-weight: 600;
            color: var(--accent-green);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Logo styling */
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
    </style>
</head>
<body>

    <div class="header">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1><i class="fas fa-info-circle"></i> About Afri Earn</h1>
    </div>

    <div class="container">
        <div class="card">
            <div class="logo-header">
                <i class="fas fa-user-ninja logo-icon"></i>
                <h2 style="color: var(--accent-green); margin-bottom: 0.5rem;">Ninja Hope</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem;">The Hope of the Nation</p>
            </div>
            
            <p>
                <strong class="highlight">Ninja Hope</strong> is the <strong>hope of the nation</strong>. 
                Earn real money by <strong>referring friends</strong> and <strong>completing tasks</strong>.
            </p>
            
            <div class="mission">
                <div class="mission-title">
                    <i class="fas fa-bullseye"></i>
                    Our Mission
                </div>
                <p>Empowering individuals to achieve financial independence through legitimate referral marketing and task completion.</p>
            </div>

            <p>
                Share your <strong>referral link</strong> and build your team. Every active member earns you <strong class="highlight">₦100–₦500</strong> per referral!
            </p>

            <div class="stats">
                <div>
                    <div class="stat-value"><?php echo $referral_data['referrals_count'] ?? 0; ?></div>
                    <div class="stat-label">Referrals</div>
                </div>
                <div>
                    <div class="stat-value">₦<?php echo number_format($user_data['wallet_balance'] ?? 0, 2); ?></div>
                    <div class="stat-label">Earned</div>
                </div>
            </div>

            <div class="feature-list">
                <div class="feature-item">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <strong>Referral Rewards:</strong><br>
                        • <strong>Level 1:</strong> ₦500 per direct referral<br>
                    </div>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-gift"></i>
                    <div>
                        <strong>Daily Benefits:</strong><br>
                        • Complete tasks for extra earnings<br>
                        • Daily check-in bonuses<br>
                        • Special promotion rewards
                    </div>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-rocket"></i>
                    <div>
                        <strong>Growth Opportunities:</strong><br>
                        • Build your referral network<br>
                        • Unlock higher earning tiers<br>
                        • Access premium features
                    </div>
                </div>
            </div>

            <div class="warning">
                <i class="fas fa-exclamation-triangle"></i><br>
                No fake or multiple accounts — permanent ban!
            </div>

            <p style="text-align: center; font-style: italic; margin-top: 1.5rem;">
                Be honest. Grow smart. <strong class="highlight">Ninja Hope — Earn with Integrity!</strong>
            </p>
            
            <div style="text-align: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--light-gray);">
                <p style="font-size: 0.8rem; color: var(--text-muted);">
                    <i class="fas fa-clock"></i> 24/7 Support Available<br>
                    <i class="fas fa-lock"></i> Secure & Verified Platform
                </p>
            </div>
        </div>
    </div>

    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            Home
        </a>
        <a href="auth.php" class="nav-item <?php echo $current_page == 'auth.php' ? 'active' : ''; ?>">
            <i class="fas fa-shield-alt"></i>
            Auth
        </a>
        <a href="tasks.php" class="nav-item <?php echo $current_page == 'tasks.php' ? 'active' : ''; ?>">
            <i class="fas fa-gift"></i>
            Tasks
        </a>
        <a href="payee.php" class="nav-item <?php echo $current_page == 'payee.php' ? 'active' : ''; ?>">
            <i class="fas fa-university"></i>
            Bank
        </a>
        <a href="about.php" class="nav-item <?php echo $current_page == 'about.php' ? 'active' : ''; ?>">
            <i class="fas fa-info-circle"></i>
            About
        </a>
    </div>

</body>
</html>