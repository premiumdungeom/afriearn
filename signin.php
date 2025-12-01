<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$user_id = $user['id'];
$cashback_balance = getUserCashback($user_id);
$signin_history = getSigninHistory($user_id);

// Check if claimed today
$has_claimed_today = hasClaimedToday($user_id);

// Calculate stats
$total_days = count($signin_history);
$total_earned = array_sum(array_column($signin_history, 'amount'));
$streak = 0;
if ($total_days) {
    // Calculate streak by checking contiguous claimed_on dates backwards from today
    $dates = array_column($signin_history, 'claimed_on');
    $dates = array_unique($dates);
    sort($dates);
    $today = date('Y-m-d');
    $streak = 0;
    for ($i = count($dates) - 1; $i >= 0; $i--) {
        if ($dates[$i] == date('Y-m-d', strtotime("-" . (count($dates)-1-$i) . " days"))) {
            $streak++;
        } else {
            break;
        }
    }
}

// Handle claim
$claimed_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$has_claimed_today) {
    // Random between 100 and 200
    $reward = rand(100, 200);
    if (claimSigninReward($user_id, $reward)) {
        $cashback_balance = getUserCashback($user_id); // update
        $has_claimed_today = true;
        $claimed_message = "Bonus claimed: ₦" . number_format($reward, 2);
        // Refresh history/stats
        $signin_history = getSigninHistory($user_id);
        $total_days = count($signin_history);
        $total_earned = array_sum(array_column($signin_history, 'amount'));
        // (streak calculation same as above)
        $dates = array_column($signin_history, 'claimed_on');
        $dates = array_unique($dates);
        sort($dates);
        $streak = 0;
        for ($i = count($dates) - 1; $i >= 0; $i--) {
            if ($dates[$i] == date('Y-m-d', strtotime("-" . (count($dates)-1-$i) . " days"))) {
                $streak++;
            } else {
                break;
            }
        }
    } else {
        $claimed_message = "Already claimed for today!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Sign In - Ninja Hope</title>
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
            border-radius: 20px; 
            padding: 1.5rem; 
            text-align: center; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.2); 
            margin-bottom: 1.5rem;
            border: 1px solid var(--light-gray);
        }
        
        .bonus-box { 
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white; 
            padding: 2rem; 
            border-radius: 16px; 
            margin: 1rem 0; 
            position: relative;
            overflow: hidden;
            border: 1px solid var(--light-gray);
        }
        
        .bonus-box::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 15s linear infinite;
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .bonus-content {
            position: relative;
            z-index: 1;
        }
        
        .bonus-amount { 
            font-size: 2.5rem; 
            font-weight: 700; 
            margin: 0.5rem 0; 
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .bonus-label { 
            font-size: 1rem; 
            opacity: 0.9; 
            margin-bottom: 0.5rem;
        }
        
        .btn { 
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white; 
            border: none; 
            padding: 1rem; 
            width: 100%; 
            border-radius: 12px; 
            font-size: 1.1rem; 
            cursor: pointer; 
            margin: 0.5rem 0; 
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
        }
        
        .btn:disabled { 
            background: var(--light-gray); 
            cursor: not-allowed; 
            transform: none;
            box-shadow: none;
        }
        
        .success { 
            background: var(--success-bg); 
            color: var(--success-text); 
            padding: 1rem; 
            border-radius: 12px; 
            margin: 1rem 0; 
            font-weight: 600;
            border-left: 4px solid var(--success-text);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .balance { 
            font-size: 1.2rem; 
            font-weight: 600; 
            color: var(--accent-green); 
            margin: 1rem 0;
            background: var(--dim-black);
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .streak-counter {
            display: flex;
            justify-content: space-around;
            margin: 1.5rem 0;
            background: var(--dim-black);
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid var(--light-gray);
        }
        
        .streak-item {
            text-align: center;
        }
        
        .streak-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 0.3rem;
        }
        
        .streak-label {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
            margin: 1.5rem 0;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            background: var(--dim-black);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid var(--light-gray);
            transition: all 0.3s ease;
        }
        
        .calendar-day.claimed {
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white;
            border-color: var(--accent-green);
        }
        
        .calendar-day.today {
            background: var(--accent-green);
            color: white;
            border-color: var(--accent-green);
            transform: scale(1.1);
        }
        
        .calendar-day.future {
            background: var(--light-gray);
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
        
        .reward-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1><i class="fas fa-calendar-check"></i> Daily Sign In</h1>
    </div>
    <div class="container">
        <div class="card">
            <div class="bonus-box">
                <div class="bonus-content">
                    <div class="bonus-label">
                        <i class="fas fa-gift"></i> Today's Login Reward
                    </div>
                    <div class="bonus-amount">
                        ₦100 - ₦200
                    </div>
                    <div class="bonus-label">
                        Claim once per day!
                    </div>
                </div>
            </div>
            <div class="streak-counter">
                <div class="streak-item">
                    <div class="streak-value"><?php echo $streak; ?></div>
                    <div class="streak-label">Day Streak</div>
                </div>
                <div class="streak-item">
                    <div class="streak-value">₦<?php echo number_format($total_earned,2); ?></div>
                    <div class="streak-label">Total Earned</div>
                </div>
                <div class="streak-item">
                    <div class="streak-value"><?php echo $total_days; ?></div>
                    <div class="streak-label">Total Days</div>
                </div>
            </div>
            <p class="balance">
                <i class="fas fa-wallet"></i> Cashback Balance: <strong>₦<?php echo number_format($cashback_balance,2); ?></strong>
            </p>
            <form method="post">
                <button type="submit" class="btn" <?php echo $has_claimed_today ? 'disabled' : ''; ?>>
                    <?php if ($has_claimed_today): ?>
                        <i class="fas fa-check-circle"></i> Already Claimed Today
                    <?php else: ?>
                        <i class="fas fa-gift"></i> Claim Today's Bonus
                    <?php endif; ?>
                </button>
            </form>
            <?php if ($claimed_message): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($claimed_message); ?>
            </div>
            <?php elseif ($has_claimed_today): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i> Bonus claimed successfully today!
            </div>
            <?php endif; ?>

            <div class="calendar">
                <?php
                // Show a calendar: last 14 days
                $days = [];
                for ($i = 0; $i < 14; $i++) {
                    $d = date('Y-m-d', strtotime('-' . (13-$i) . ' days'));
                    $claim = in_array($d, array_column($signin_history, 'claimed_on'));
                    $class = '';
                    if ($d == date('Y-m-d')) $class = 'today';
                    elseif ($claim) $class = 'claimed';
                    else $class = 'future';
                    echo '<div class="calendar-day '.$class.'">'.date('j', strtotime($d)).'</div>';
                }
                ?>
            </div>
            <p style="color:var(--text-muted);font-size:0.9rem;margin-top:1rem;">
                <i class="fas fa-clock"></i> Come back tomorrow for another bonus!
            </p>
            <div class="reward-info">
                <i class="fas fa-info-circle"></i>
                Higher streaks unlock bigger rewards!
            </div>
        </div>
    </div>
    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i> Home</a>
        <a href="auth.php" class="nav-item"><i class="fas fa-shield-alt"></i> Auth</a>
        <a href="tasks.php" class="nav-item"><i class="fas fa-tasks"></i> Tasks</a>
        <a href="payee.php" class="nav-item"><i class="fas fa-university"></i> Bank</a>
        <a href="signin.php" class="nav-item active"><i class="fas fa-calendar-check"></i> Sign In</a>
    </div>
</body>
</html>