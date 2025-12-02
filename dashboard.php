<?php
// dashboard.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$user_id = $user['id'] ?? null;
$total_earned = array_sum(array_column(getSigninHistory($user_id), 'amount'));

// Fetch user data
try {
    $userResult = $supabase->from('users')->eq('id', $user_id);
    if (isset($userResult['data'][0])) {
        $user_data = $userResult['data'][0];
    }
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afri Earn</title>
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
            --gold: #d4ac0d;
            --blue: #3498db;
        }
        
        * { 
            margin:0; 
            padding:0; 
            box-sizing:border-box; 
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--dim-black);
            color: var(--text-light);
            padding-bottom: 70px;
            position: relative;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white;
            padding: 1rem;
            text-align: center;
            position: relative;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .header h1 { 
            font-size: 1.25rem; 
        }
        
        .close-btn {
            position: absolute;
            left: 1rem; 
            top: 50%; 
            transform: translateY(-50%);
            font-size: 1.4rem; 
            color: white; 
            text-decoration: none;
            transition: opacity 0.3s;
        }
        
        .close-btn:hover {
            opacity: 0.8;
        }

        /* FREE PLATFORM NOTICE BANNER */
        .free-notice {
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white;
            text-align: center;
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            position: relative;
            margin: 0 1rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            animation: slideDown 0.6s ease-out;
            margin-top: -10px;
            margin-bottom: 1rem;
        }
        
        .free-notice i {
            font-size: 1.4rem;
            margin-right: 0.5rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes slideDown {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .free-notice .close-notice {
            position: absolute;
            top: 8px;
            right: 12px;
            background: none;
            border: none;
            color: white;
            font-size: 1.4rem;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        
        .free-notice .close-notice:hover {
            opacity: 1;
        }

        /* Wallet Section */
        .wallet-section { 
            background: var(--dark-gray); 
            margin: 1rem; 
            border-radius: 16px; 
            padding: 1rem; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.2); 
            border: 1px solid var(--light-gray);
        }
        
        .wallet-title { 
            font-size: 1rem; 
            color: var(--text-light); 
            margin-bottom: 1rem; 
            padding-bottom: 0.5rem; 
            border-bottom: 1px solid var(--light-gray); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .wallet-title a { 
            color: var(--text-muted); 
            font-size: 1.2rem; 
            transition: color 0.3s;
        }
        
        .wallet-title a:hover {
            color: var(--accent-green);
        }
        
        .balance-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 1rem; 
        }

        .balance-item:nth-child(3) {
            grid-column: 1 / span 2;
            justify-self: center;
            width: 50%;
        }
        
        .balance-item { 
            text-align: center; 
            padding: 0.8rem; 
            background: var(--dim-black);
            border-radius: 12px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .balance-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }
        
        .amount { 
            font-size: 1.1rem; 
            font-weight: 600; 
            color: var(--accent-green); 
            margin-bottom: 0.3rem; 
        }
        
        .label { 
            font-size: 0.85rem; 
            color: var(--text-muted); 
        }

        /* Tools */
        .tools { 
            display: flex; 
            justify-content: space-around; 
            background: var(--dark-gray); 
            margin: 1rem; 
            border-radius: 16px; 
            padding: 1rem 0; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            border: 1px solid var(--light-gray);
        }
        
        .tool { 
            text-align: center; 
            color: var(--text-light); 
            text-decoration: none; 
            font-size: 0.9rem; 
            transition: color 0.3s, transform 0.3s;
        }
        
        .tool:hover {
            color: var(--accent-green);
            transform: translateY(-3px);
        }
        
        .tool i { 
            display: block; 
            font-size: 1.6rem; 
            margin-bottom: 0.4rem; 
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            width: 44px; 
            height: 44px; 
            line-height: 44px; 
            border-radius: 50%; 
            margin: 0 auto 0.5rem; 
            transition: transform 0.3s;
        }
        
        .tool:hover i {
            transform: scale(1.1);
        }

        /* Functions */
        .functions { 
            margin: 0 1rem 1rem; 
        }
        
        .func-item { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            background: var(--dark-gray); 
            margin-bottom: 0.5rem; 
            padding: 1rem; 
            border-radius: 16px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.2); 
            text-decoration: none; 
            color: inherit; 
            border: 1px solid var(--light-gray);
            transition: transform 0.3s, background 0.3s;
        }
        
        .func-item:hover {
            background: var(--light-gray);
            transform: translateX(5px);
        }
        
        .func-item i:first-child { 
            color: var(--accent-green); 
            font-size: 1.3rem; 
            margin-right: 1rem; 
        }
        
        .func-item span { 
            flex: 1; 
            font-size: 1rem; 
        }
        
        .func-item i:last-child { 
            color: var(--text-muted); 
            transition: color 0.3s;
        }
        
        .func-item:hover i:last-child {
            color: var(--accent-green);
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
            box-shadow: 0 -4px 12px rgba(0,0,0,0.3); 
            border-top: 1px solid var(--light-gray); 
            z-index: 100;
        }
        
        .nav-item { 
            text-align: center; 
            color: var(--text-muted); 
            font-size: 0.75rem; 
            text-decoration: none; 
            flex: 1; 
            transition: color 0.3s;
        }
        
        .nav-item i { 
            display: block; 
            font-size: 1.3rem; 
            margin-bottom: 0.2rem; 
        }
        
        .nav-item.active { 
            color: var(--accent-green); 
            font-weight: 600; 
        }
        
        .nav-item:hover {
            color: var(--accent-green);
        }

        /* POPUP STYLES */
        .popup-overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background: rgba(0,0,0,0.8); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            z-index: 9999; 
            visibility: hidden; 
            opacity: 0; 
            transition: all 0.3s; 
        }
        
        .popup-overlay.show { 
            visibility: visible; 
            opacity: 1; 
        }
        
        .popup { 
            background: var(--dark-gray); 
            border-radius: 20px; 
            width: 90%; 
            max-width: 380px; 
            padding: 1.5rem; 
            text-align: center; 
            position: relative; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); 
            border: 1px solid var(--light-gray);
            animation: pop 0.4s ease-out; 
        }
        
        @keyframes pop { 
            0% { transform: scale(0.7); opacity: 0; } 
            100% { transform: scale(1); opacity: 1; } 
        }
        
        .popup-close { 
            position: absolute; 
            top: 10px; 
            right: 12px; 
            font-size: 1.5rem; 
            color: var(--text-muted); 
            cursor: pointer; 
            background:none; 
            border:none; 
            transition: color 0.3s;
        }
        
        .popup-close:hover {
            color: var(--accent-green);
        }
        
        .popup h3 { 
            font-size: 1.3rem; 
            color: var(--text-light); 
            margin: 0.5rem 0 1rem; 
            font-weight: 700; 
        }
        
        .popup p { 
            font-size: 0.95rem; 
            color: var(--text-muted); 
            margin-bottom: 1rem; 
            line-height: 1.5; 
        }
        
        .popup .btn-group { 
            display: flex; 
            flex-direction: column; 
            gap: 0.7rem; 
            margin-top: 1rem; 
        }
        
        .popup .btn { 
            padding: 0.9rem; 
            border-radius: 12px; 
            font-weight: 600; 
            text-decoration: none; 
            font-size: 0.95rem; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 0.5rem; 
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .popup .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
        }
        
        .popup .btn.telegram { 
            background: var(--blue); 
            color: white; 
        }
        
        .popup .btn.whatsapp { 
            background: #25d366; 
            color: white; 
        }
        
        .popup .badge { 
            background: var(--gold); 
            color: #333; 
            padding: 0.3rem 0.8rem; 
            border-radius: 20px; 
            font-size: 0.8rem; 
            font-weight: 700; 
            display: inline-block; 
            margin: 0.5rem 0; 
        }
        
        .popup .note { 
            font-size: 0.8rem; 
            color: #e74c3c; 
            font-weight: 600; 
            margin-top: 1rem; 
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <a href="logout.php" class="close-btn">X</a>
        <h1>Afri Earn</h1>
        <div class="user-info">
            <?php echo htmlspecialchars($user_data['username'] ?? 'User'); ?>
        </div>
    </div>

    <!-- NEW: 100% FREE NOTICE BANNER -->
    <div class="free-notice" id="freeNotice">
        <button class="close-notice" onclick="document.getElementById('freeNotice').style.display='none'">×</button>
        This platform is <strong>100% FREE!</strong><br>
        <strong>You NEVER pay any fee</strong> to withdraw your earnings. Enjoy!
    </div>
    
    <!-- Welcome Bonus Alert -->
    <?php if (isset($_SESSION['welcome_bonus'])): ?>
    <div class="bonus-alert">
        <i class="fas fa-gift"></i> 
        <strong>Welcome Bonus!</strong> You've received ₦300 for joining!
    </div>
    <?php unset($_SESSION['welcome_bonus']); endif; ?>

    <!-- Wallet -->
    <div class="wallet-section">
        <div class="wallet-title">
            My Wallet
            <a href="dashboard.php">></a>
        </div>
        <div class="balance-grid">
            <div class="balance-item">
                <div class="amount">3,600,000.00</div>
                <div class="label">Subventions</div>
            </div>
            <div class="balance-item">
                <div class="amount">₦<?php echo number_format($user_data['wallet_balance'] ?? 0, 2); ?></div>
                <div class="label">Team Rewards</div>
            </div>
            <div class="balance-item">
                <div class="amount">₦<?php echo number_format($total_earned,2); ?></div>
                <div class="label">Daily Cashback</div>
            </div>
        </div>
    </div>

    <!-- Tools -->
    <div class="tools">
        <a href="transfer.php" class="tool">
            <i class="fas fa-exchange-alt"></i>
            Transfer
        </a>
        <a href="signin.php" class="tool">
            <i class="fas fa-plus-circle"></i>
            Sign In
        </a>
        <a href="withdraw.php" class="tool">
            <i class="fas fa-comment-dollar"></i>
            Withdraw
        </a>
    </div>

    <!-- Other Functions -->
    <div class="functions">
        <a href="payee.php" class="func-item">
            <i class="fas fa-credit-card"></i>
            <span>Payee Method</span>
            <i class="fas fa-chevron-right"></i>
        </a>
        <a href="agent.php" class="func-item">
            <i class="fas fa-box"></i>
            <span> Refer for cash</span>
            <i class="fas fa-chevron-right"></i>
        </a>
        <a href="about.php" class="func-item">
            <i class="fas fa-truck"></i>
            <span>About</span>
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item active">
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
        <a href="chat.php" class="nav-item">
            <i class="fas fa-user"></i>
            Support 
        </a>
</div>

    <!-- POPUP 1: Join Channels & Group -->
    <div class="popup-overlay" id="popupJoin">
        <div class="popup">
            <button class="popup-close" onclick="closePopup('popupJoin')">×</button>
            <i class="fab fa-telegram-plane" style="font-size:3.5rem;color:#0088cc;margin-bottom:0.5rem;"></i>
            <h3>Join Now & Get ₦5,000 FREE!</h3>
            <p>You <strong>MUST join all 3 links</strong> below to activate your ₦5,000 bonus.</p>
            <div class="badge">₦5,000 Instant Credit</div>
            <div class="btn-group">
                <a href="https://t.me/AFRI_EARN" target="_blank" class="btn telegram">
                    <i class="fas fa-bullhorn"></i> Join Official Chan
                </a>
            </div>
        </div>
    </div>

    <!-- POPUP 2: Contact Support -->
    <div class="popup-overlay" id="popupSupport">
        <div class="popup">
            <button class="popup-close" onclick="closePopup('popupSupport')">×</button>
            <i class="fas fa-headset" style="font-size:3rem;color:var(--accent-green);"></i>
            <h3>Need Help?</h3>
            <p>Contact us to place ads, buy groups/channels or get support.</p>
            <div class="btn-group">
                <a href="https://t.me/Afriearn5" target="_blank" class="btn telegram">
                    <i class="fab fa-telegram-plane"></i> Telegram Admin
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide free notice after 10 seconds
        setTimeout(() => {
            const notice = document.getElementById('freeNotice');
            if (notice) notice.style.opacity = '0';
            setTimeout(() => notice.style.display = 'none', 600);
        }, 10000);

        // Show popups
        window.onload = function() {
            setTimeout(() => document.getElementById('popupJoin').classList.add('show'), 1000);
            setTimeout(() => document.getElementById('popupSupport').classList.add('show'), 9000);
        };

        function closePopup(id) {
            document.getElementById(id).classList.remove('show');
        }

        document.querySelectorAll('.popup-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('show');
            });
        });
    </script>

</body>
</html>