<?php
// agent.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$user_id = $user['id'] ?? null;

// Fetch user referral data
$referral_data = [];
$referral_history = [];
$total_earnings = 0;

try {
    // Get user's referral code and stats
    $userResult = $supabase->from('users')->eq('id', $user_id);
    if (isset($userResult['data'][0])) {
        $referral_data = $userResult['data'][0];
    }
    
    // Get referral history
    $historyResult = $supabase->from('referral_history')->eq('referrer_id', $user_id);
    if (isset($historyResult['data'])) {
        $referral_history = $historyResult['data'];
        $total_earnings = array_sum(array_column($referral_history, 'amount'));
    }
} catch (Exception $e) {
    error_log("Agent page error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral - Ninja Hope</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            --gold: #d4ac0d;
            --shadow: 0 8px 25px rgba(0,0,0,0.3);
            --radius: 20px;
            --transition: all 0.3s ease;
        }
        
        * { 
            margin:0; 
            padding:0; 
            box-sizing:border-box; 
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--dim-black);
            color: var(--text-light);
            padding-bottom: 90px;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white;
            padding: 1.2rem;
            text-align: center;
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }
        
        .header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .back-btn {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-size: 1.5rem;
            text-decoration: none;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.2);
            border-radius: 50%;
            backdrop-filter: blur(10px);
            transition: var(--transition);
        }
        
        .back-btn:hover { 
            background: rgba(255,255,255,.3); 
            transform: translateY(-50%) scale(1.1); 
        }

        .container { 
            padding: 1.5rem; 
        }

        /* EARNINGS CARD */
        .earn-card {
            background: var(--dark-gray);
            margin: 1.5rem 1rem;
            border-radius: var(--radius);
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--light-gray);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .earn-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(30,132,73,.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate { 
            0% { transform: rotate(0deg); } 
            100% { transform: rotate(360deg); } 
        }

        .earn-label {
            font-size: 1rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .earn-amount {
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--accent-green);
            margin: 0.5rem 0;
            text-shadow: 0 2px 10px rgba(30,132,73,.3);
        }
        
        .earn-sub {
            font-size: 1rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        /* REFERRAL CODE & LINK */
        .ref-box {
            background: var(--dim-black);
            padding: 1rem;
            border-radius: 16px;
            margin: 1rem 0;
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 1.1rem;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--light-gray);
            color: var(--text-light);
        }
        
        .ref-box::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.1), transparent);
            transform: translateX(-100%);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .copy-group {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.8rem;
        }
        
        .copy-btn {
            flex: 1;
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .copy-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.4);
        }
        
        .copy-btn i { 
            font-size: 1.1rem; 
        }

        /* HISTORY */
        .history-card {
            background: var(--dark-gray);
            margin: 1rem;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--light-gray);
        }
        
        .history-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.8rem;
            border-bottom: 2px solid var(--light-gray);
        }
        
        .history-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-light);
        }
        
        .history-count {
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .ref-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px dashed var(--light-gray);
            transition: var(--transition);
        }
        
        .ref-item:last-child { 
            border-bottom: none; 
        }
        
        .ref-item:hover { 
            background: rgba(30,132,73,.1); 
            border-radius: 12px; 
            margin: 0 -1rem; 
            padding: 1rem; 
        }

        .ref-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .ref-info { 
            flex: 1; 
        }
        
        .ref-message {
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 0.3rem;
        }
        
        .ref-message .amount {
            color: var(--accent-green);
            font-weight: 700;
        }
        
        .ref-date {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--light-gray);
            margin-bottom: 1rem;
        }

        /* BOTTOM NAV */
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
            transition: var(--transition);
            position: relative;
        }
        
        .nav-item i { 
            display: block; 
            font-size: 1.4rem; 
            margin-bottom: 0.3rem; 
            transition: var(--transition);
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
            transition: var(--transition);
            transform: translateX(-50%);
        }
        
        .nav-item.active::after {
            width: 30px;
        }

        /* CONFETTI */
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: var(--accent-green);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9999;
            animation: fall 3s linear forwards;
        }
        
        @keyframes fall {
            to {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }

        /* Referral Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 1rem;
        }
        
        .stat-card {
            background: var(--dark-gray);
            border-radius: 16px;
            padding: 1.2rem;
            text-align: center;
            border: 1px solid var(--light-gray);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 0.3rem;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    <div class="header">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1>Referral Center</h1>
    </div>

    <div class="container">

        <!-- EARNINGS CARD -->
        <div class="earn-card">
            <div class="earn-label">Total Referral Earnings</div>
            <div class="earn-amount">₦<?php echo number_format($total_earnings, 2); ?></div>
            <div class="earn-sub">From <?php echo $referral_data['referrals_count'] ?? 0; ?> successful invites</div>
            <p style="margin-top:1rem;font-size:0.95rem;color:var(--text-muted);">
                Earn <strong style="color:var(--accent-green);">₦500</strong> instantly when someone registers with your link!
            </p>
        </div>

        <!-- Referral Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $referral_data['referrals_count'] ?? 0; ?></div>
                <div class="stat-label">Total Referrals</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₦500</div>
                <div class="stat-label">Per Referral</div>
            </div>
        </div>

        <!-- REFERRAL CODE -->
        <div style="margin:1rem;">
            <div class="ref-box" id="refCode"><?php echo htmlspecialchars($referral_data['referral_code'] ?? 'N/A'); ?></div>
            <div class="copy-group">
                <button class="copy-btn" onclick="copyText('refCode', 'Referral code')">
                    <i class="fas fa-copy"></i> Copy Code
                </button>
            </div>
        </div>

        <!-- REFERRAL LINK -->
        <div style="margin:1rem;">
            <div class="ref-box" id="refLink">https://ninjahope.nexo.com.ng/register.php?ref=<?php echo htmlspecialchars($referral_data['referral_code'] ?? ''); ?></div>
            <div class="copy-group">
                <button class="copy-btn" onclick="copyText('refLink', 'Referral link')">
                    <i class="fas fa-link"></i> Copy Link
                </button>
                <button class="copy-btn" onclick="shareLink()">
                    <i class="fas fa-share-alt"></i> Share
                </button>
            </div>
        </div>

        <!-- HISTORY -->
        <div class="history-card">
            <div class="history-header">
                <div class="history-title">Reward History</div>
                <div class="history-count"><?php echo count($referral_history); ?> Total</div>
            </div>

            <?php if (empty($referral_history)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>No referral rewards yet</p>
                    <p style="font-size:0.9rem;margin-top:0.5rem;">Share your link to start earning!</p>
                </div>
            <?php else: ?>
                <?php foreach ($referral_history as $referral): ?>
                    <?php
                    // Get referred user details - FIXED THIS PART
                    $referredUser = [];
                    try {
                        // Create a NEW query to get user details
                        $userQuery = $supabase->from('users')->eq('id', $referral['referred_user_id']);
                        if (isset($userQuery['data'][0])) {
                            $referredUser = $userQuery['data'][0];
                        }
                    } catch (Exception $e) {
                        error_log("Error fetching referred user: " . $e->getMessage());
                    }
                    
                    $username = $referredUser['username'] ?? 'User';
                    $initials = substr($referredUser['name'] ?? 'UU', 0, 2);
                    $date = date('M j, Y \a\t g:i A', strtotime($referral['created_at']));
                    ?>
                    
                    <div class="ref-item">
                        <div class="ref-avatar">
                            <?php echo strtoupper($initials); ?>
                        </div>
                        <div class="ref-info">
                            <div class="ref-message">
                                You received <span class="amount">₦<?php echo number_format($referral['amount'], 2); ?></span> for inviting <strong>@<?php echo htmlspecialchars($username); ?></strong>
                            </div>
                            <div class="ref-date">
                                <?php echo $date; ?>
                            </div>
                        </div>
                        <i class="fas fa-check-circle" style="color:var(--accent-green);font-size:1.3rem;"></i>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
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
        <a href="agent.php" class="nav-item active">
            <i class="fas fa-user-friends"></i>
            Agent
        </a>
        <a href="profile.php" class="nav-item">
            <i class="fas fa-user"></i>
            Profile
        </a>
    </div>

    <script>
        function copyText(id, label) {
            const text = document.getElementById(id).innerText;
            navigator.clipboard.writeText(text).then(() => {
                triggerConfetti();
                const btn = event.target.closest('button');
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                setTimeout(() => {
                    btn.innerHTML = original;
                }, 2000);
            });
        }

        function shareLink() {
            const refLink = document.getElementById('refLink').innerText;
            if (navigator.share) {
                navigator.share({
                    title: 'Join Ninja Hope!',
                    text: 'Earn ₦500 when you register with my link!',
                    url: refLink
                });
            } else {
                copyText('refLink', 'link');
            }
        }

        function triggerConfetti() {
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.background = ['#1e8449', '#0a5c36', '#4a235a', '#d4ac0d'][Math.floor(Math.random() * 4)];
                confetti.style.animationDelay = Math.random() * 3 + 's';
                document.body.appendChild(confetti);
                setTimeout(() => confetti.remove(), 3000);
            }
        }
    </script>
</body>
</html>