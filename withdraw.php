<?php
// withdraw.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$user_id = $user['id'];

$success = '';
$error = '';

// Get withdrawal settings and user eligibility
$withdrawal_settings = getWithdrawalSettings();
$user_eligibility = checkWithdrawalEligibility($user_id, $withdrawal_settings);
$user_banks = getUserBankDetails($user_id);

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'withdraw') {
    $amount = floatval($_POST['amount'] ?? 0);
    $bank_id = $_POST['bank_id'] ?? '';
    
    if (empty($amount) || empty($bank_id)) {
        $error = 'Please fill all required fields';
    } elseif (!$user_eligibility['can_withdraw']) {
        $error = $user_eligibility['message'];
    } elseif ($amount < $withdrawal_settings['min_amount'] || $amount > $withdrawal_settings['max_amount']) {
        $error = "Amount must be between ₦" . number_format($withdrawal_settings['min_amount']) . " and ₦" . number_format($withdrawal_settings['max_amount']);
    } else {
        $result = processWithdrawal($user_id, $amount, $bank_id);
        if ($result['success']) {
            $success = 'Withdrawal request submitted successfully!';
            // Refresh eligibility
            $user_eligibility = checkWithdrawalEligibility($user_id, $withdrawal_settings);
        } else {
            $error = $result['error'] ?? 'Failed to process withdrawal';
        }
    }
}

// Get withdrawal history
$withdrawal_history = getWithdrawalHistory($user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw • Afri Earn</title>
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
            --error-bg: #2c0b07;
            --error-text: #f87171;
            --success-bg: #0d3019;
            --success-text: #4ade80;
            --live-bg: #0d3019;
            --live-text: #4ade80;
        }
        
        * { 
            margin:0; 
            padding:0; 
            box-sizing:border-box; 
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--dim-black);
            min-height: 100vh;
            color: var(--text-light);
            position: relative;
            overflow-x: hidden;
            padding-bottom: 80px; /* Space for bottom nav */
        }

        .header {
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            padding: 1rem;
            text-align: center;
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
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
            font-size: 1.3rem;
            padding: 0 2rem;
        }
        
        .container { 
            padding: 1rem; 
            max-width: 500px;
            margin: 0 auto;
        }

        .card {
            background: var(--dark-gray);
            border-radius: 16px;
            padding: 1.2rem;
            margin-bottom: 1.2rem;
            border: 1px solid var(--light-gray);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .status-banner {
            padding: 0.8rem;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            font-size: 0.9rem;
        }
        
        .status-live {
            background: var(--live-bg);
            color: var(--live-text);
            border: 1px solid var(--live-text);
        }
        
        .status-closed {
            background: var(--warning-bg);
            color: var(--warning-text);
            border: 1px solid var(--warning-text);
        }

        .balance-box {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.6rem;
            margin: 1.2rem 0;
        }
        
        .bal-item {
            background: var(--dim-black);
            padding: 0.8rem 0.5rem;
            border-radius: 10px;
            text-align: center;
            border: 1px solid var(--light-gray);
            transition: all 0.3s ease;
            min-height: 70px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .bal-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        
        .bal-label { 
            font-size: 0.75rem; 
            color: var(--text-muted);
            margin-bottom: 0.4rem;
            line-height: 1.2;
        }
        
        .bal-amount { 
            font-size: 0.9rem; 
            font-weight: 700; 
            color: var(--accent-green);
            line-height: 1.2;
        }

        .form-group { 
            margin: 1rem 0; 
        }
        
        label { 
            display: block; 
            margin-bottom: 0.5rem; 
            font-weight: 600; 
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        select, input {
            width: 100%; 
            padding: 0.8rem;
            background: var(--dim-black);
            border: 1px solid var(--light-gray);
            border-radius: 10px;
            color: var(--text-light); 
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        select:focus, input:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 0 2px rgba(30, 132, 73, 0.2);
        }
        
        select option { 
            background: var(--dark-gray); 
            color: var(--text-light);
            padding: 0.5rem;
        }

        .btn {
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white;
            padding: 0.9rem;
            width: 100%;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.8rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        
        .btn:disabled {
            background: var(--light-gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .success { 
            background: var(--success-bg); 
            border: 1px solid var(--success-text); 
            padding: 0.8rem; 
            border-radius: 10px; 
            text-align: center; 
            color: var(--success-text);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .error { 
            background: var(--error-bg); 
            border: 1px solid var(--error-text); 
            padding: 0.8rem; 
            border-radius: 10px; 
            text-align: center; 
            color: var(--error-text);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .history-item {
            background: var(--dim-black);
            padding: 0.8rem;
            border-radius: 10px;
            margin-bottom: 0.6rem;
            border: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .history-info {
            flex: 1;
        }
        
        .history-amount {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 0.2rem;
        }
        
        .history-date {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        .history-status {
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending { 
            background: var(--warning-bg); 
            color: var(--warning-text); 
        }
        
        .status-approved { 
            background: var(--success-bg); 
            color: var(--success-text); 
        }
        
        .status-rejected { 
            background: var(--error-bg); 
            color: var(--error-text); 
        }

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
        }
        
        .nav-item {
            text-align: center;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.75rem;
            flex: 1;
            padding: 0.4rem 0;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .nav-item i { 
            font-size: 1.2rem; 
            display: block; 
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

        .requirements {
            background: var(--dim-black);
            padding: 1.2rem;
            border-radius: 10px;
            margin: 1.2rem 0;
            border: 1px solid var(--light-gray);
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.6rem;
            padding: 0.4rem 0;
            font-size: 0.9rem;
        }
        
        .requirement-item i {
            font-size: 1rem;
            width: 20px;
        }
        
        .requirement-complete {
            color: var(--success-text);
        }
        
        .requirement-incomplete {
            color: var(--text-muted);
        }
        
        .countdown {
            text-align: center;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--accent-green);
            margin: 0.8rem 0;
            padding: 0.8rem;
            background: var(--dim-black);
            border-radius: 10px;
            border: 1px solid var(--accent-green);
        }

        h3 {
            font-size: 1.1rem;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Responsive adjustments for very small screens */
        @media (max-width: 360px) {
            .container {
                padding: 0.8rem;
            }
            
            .card {
                padding: 1rem;
            }
            
            .balance-box {
                gap: 0.4rem;
            }
            
            .bal-item {
                padding: 0.6rem 0.3rem;
                min-height: 65px;
            }
            
            .bal-label {
                font-size: 0.7rem;
            }
            
            .bal-amount {
                font-size: 0.8rem;
            }
            
            .header h1 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>

    <div class="header">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1><i class="fas fa-wallet"></i> Withdraw Funds</h1>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Withdrawal Status Banner -->
        <div class="status-banner <?php echo $withdrawal_settings['is_live'] ? 'status-live' : 'status-closed'; ?>">
            <i class="fas fa-<?php echo $withdrawal_settings['is_live'] ? 'check-circle' : 'clock'; ?>"></i>
            <?php if ($withdrawal_settings['is_live']): ?>
                Withdrawal is LIVE! 
                <?php if ($withdrawal_settings['closes_at']): ?>
                    Closes in: <span id="countdown"></span>
                <?php endif; ?>
            <?php else: ?>
                Withdrawal Closed
                <?php if ($withdrawal_settings['opens_at']): ?>
                    - Opens: <?php echo date('M j, Y g:i A', strtotime($withdrawal_settings['opens_at'])); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Balance Overview -->
        <div class="card">
            <h3>
                <i class="fas fa-chart-bar"></i> Balance Overview
            </h3>
            <div class="balance-box">
                <div class="bal-item">
                    <div class="bal-label">Cashback Balance</div>
                    <div class="bal-amount">₦<?php echo number_format($user_eligibility['cashback_balance'], 2); ?></div>
                </div>
                <div class="bal-item">
                    <div class="bal-label">Referral Balance</div>
                    <div class="bal-amount">₦<?php echo number_format($user_eligibility['referral_balance'], 2); ?></div>
                </div>
                <div class="bal-item">
                    <div class="bal-label">Total Available</div>
                    <div class="bal-amount">₦<?php echo number_format($user_eligibility['total_balance'], 2); ?></div>
                </div>
            </div>
        </div>

        <?php if ($user_eligibility['can_withdraw']): ?>
            <!-- Withdrawal Form -->
            <div class="card">
                <h3>
                    <i class="fas fa-money-bill-wave"></i> Make Withdrawal
                </h3>
                
                <form method="post">
                    <input type="hidden" name="action" value="withdraw">
                    
                    <div class="form-group">
                        <label><i class="fas fa-university"></i> Select Bank</label>
                        <select name="bank_id" required>
                            <option value="">Choose your bank</option>
                            <?php foreach ($user_banks as $bank): ?>
                                <option value="<?php echo htmlspecialchars($bank['id']); ?>">
                                    <?php echo htmlspecialchars($bank['bank_name']); ?> - <?php echo htmlspecialchars($bank['account_number']); ?>
                                    <?php if ($bank['is_default']): ?> (Default)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-money-bill"></i> Amount (₦<?php echo number_format($withdrawal_settings['min_amount']); ?> - ₦<?php echo number_format($withdrawal_settings['max_amount']); ?>)</label>
                        <input type="number" name="amount" 
                               min="<?php echo $withdrawal_settings['min_amount']; ?>" 
                               max="<?php echo $withdrawal_settings['max_amount']; ?>" 
                               step="100" 
                               placeholder="Enter amount" 
                               required>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Submit Withdrawal
                    </button>
                </form>
                
                <div style="margin-top: 0.8rem; padding: 0.8rem; background: var(--dim-black); border-radius: 8px;">
                    <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center;">
                        <i class="fas fa-info-circle"></i> 
                        Funds will be combined from your Cashback and Referral balances
                    </p>
                </div>
            </div>
        <?php else: ?>
            <!-- Requirements -->
            <div class="card">
                <div style="text-align: center; padding: 0.8rem;">
                    <i class="fas fa-lock" style="font-size: 2.5rem; color: var(--light-gray); margin-bottom: 0.8rem;"></i>
                    <h3 style="margin-bottom: 0.8rem;">Withdrawal Requirements</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">Complete these requirements to unlock withdrawals</p>
                </div>
                
                <div class="requirements">
                    <div class="requirement-item">
                        <i class="fas fa-user-check <?php echo $user_eligibility['face_verified'] ? 'requirement-complete' : 'requirement-incomplete'; ?>"></i>
                        <span>Face Verification: 
                            <strong style="color: <?php echo $user_eligibility['face_verified'] ? 'var(--success-text)' : 'var(--warning-text)'; ?>;">
                                <?php echo $user_eligibility['face_verified'] ? 'Completed' : 'Pending'; ?>
                            </strong>
                        </span>
                    </div>
                    <div class="requirement-item">
                        <i class="fas fa-tasks <?php echo $user_eligibility['approved_tasks'] >= $withdrawal_settings['required_approved_tasks'] ? 'requirement-complete' : 'requirement-incomplete'; ?>"></i>
                        <span>Approved Tasks: 
                            <strong style="color: <?php echo $user_eligibility['approved_tasks'] >= $withdrawal_settings['required_approved_tasks'] ? 'var(--success-text)' : 'var(--warning-text)'; ?>;">
                                <?php echo $user_eligibility['approved_tasks'] . '/' . $withdrawal_settings['required_approved_tasks']; ?> completed
                            </strong>
                        </span>
                    </div>
                    <div class="requirement-item">
                        <i class="fas fa-university <?php echo !empty($user_banks) ? 'requirement-complete' : 'requirement-incomplete'; ?>"></i>
                        <span>Bank Account: 
                            <strong style="color: <?php echo !empty($user_banks) ? 'var(--success-text)' : 'var(--warning-text)'; ?>;">
                                <?php echo !empty($user_banks) ? 'Added' : 'Not Added'; ?>
                            </strong>
                        </span>
                    </div>
                    <div class="requirement-item">
                        <i class="fas fa-wallet <?php echo $user_eligibility['total_balance'] >= $withdrawal_settings['min_amount'] ? 'requirement-complete' : 'requirement-incomplete'; ?>"></i>
                        <span>Minimum Balance: 
                            <strong style="color: <?php echo $user_eligibility['total_balance'] >= $withdrawal_settings['min_amount'] ? 'var(--success-text)' : 'var(--warning-text)'; ?>;">
                                ₦<?php echo number_format($user_eligibility['total_balance'], 2); ?> / ₦<?php echo number_format($withdrawal_settings['min_amount']); ?>
                            </strong>
                        </span>
                    </div>
                    <div class="requirement-item">
                        <i class="fas fa-calendar <?php echo $withdrawal_settings['is_live'] ? 'requirement-complete' : 'requirement-incomplete'; ?>"></i>
                        <span>Withdrawal Window: 
                            <strong style="color: <?php echo $withdrawal_settings['is_live'] ? 'var(--success-text)' : 'var(--warning-text)'; ?>;">
                                <?php echo $withdrawal_settings['is_live'] ? 'OPEN' : 'CLOSED'; ?>
                            </strong>
                        </span>
                    </div>
                </div>
                
                <?php if (!$user_eligibility['face_verified']): ?>
                    <button class="btn" onclick="location.href='auth.php'">
                        <i class="fas fa-user-check"></i> Complete Face Verification
                    </button>
                <?php elseif ($user_eligibility['approved_tasks'] < $withdrawal_settings['required_approved_tasks']): ?>
                    <button class="btn" onclick="location.href='tasks.php'">
                        <i class="fas fa-tasks"></i> Complete More Tasks
                    </button>
                <?php elseif (empty($user_banks)): ?>
                    <button class="btn" onclick="location.href='payee.php'">
                        <i class="fas fa-university"></i> Add Bank Account
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Withdrawal History -->
        <div class="card">
            <h3>
                <i class="fas fa-history"></i> Withdrawal History
            </h3>
            
            <?php if (empty($withdrawal_history)): ?>
                <div style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
                    <i class="fas fa-receipt" style="font-size: 2.5rem; margin-bottom: 0.8rem;"></i>
                    <p>No withdrawal history yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($withdrawal_history as $withdrawal): ?>
                    <div class="history-item">
                        <div class="history-info">
                            <div class="history-amount">₦<?php echo number_format($withdrawal['amount'], 2); ?></div>
                            <div class="history-date">
                                <?php echo date('M j, Y g:i A', strtotime($withdrawal['created_at'])); ?>
                            </div>
                        </div>
                        <div class="history-status status-<?php echo $withdrawal['status']; ?>">
                            <?php echo ucfirst($withdrawal['status']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="auth.php" class="nav-item">
            <i class="fas fa-user-shield"></i>
            <span>Auth</span>
        </a>
        <a href="tasks.php" class="nav-item">
            <i class="fas fa-tasks"></i>
            <span>Tasks</span>
        </a>
        <a href="payee.php" class="nav-item">
            <i class="fas fa-university"></i>
            <span>Bank</span>
        </a>
        <a href="withdraw.php" class="nav-item active">
            <i class="fas fa-wallet"></i>
            <span>Withdraw</span>
        </a>
    </div>

    <?php if ($withdrawal_settings['is_live'] && $withdrawal_settings['closes_at']): ?>
    <script>
        function updateCountdown() {
            const closesAt = new Date('<?php echo $withdrawal_settings['closes_at']; ?>').getTime();
            const now = new Date().getTime();
            const distance = closesAt - now;
            
            if (distance < 0) {
                document.getElementById('countdown').innerHTML = "EXPIRED";
                return;
            }
            
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById('countdown').innerHTML = 
                hours + "h " + minutes + "m " + seconds + "s";
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
    </script>
    <?php endif; ?>

</body>
</html>