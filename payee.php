<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$user_id = $user['id'];

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'add_bank':
                $bank_name = trim($_POST['bank_name'] ?? '');
                $account_name = trim($_POST['account_name'] ?? '');
                $account_number = trim($_POST['account_number'] ?? '');
                
                if (empty($bank_name) || empty($account_name) || empty($account_number)) {
                    $error = 'All fields are required';
                } else {
                    $result = addBankAccount($user_id, $bank_name, $account_name, $account_number);
                    if ($result['success']) {
                        $success = 'Bank account added successfully!';
                    } else {
                        $error = $result['error'] ?? 'Failed to add bank account';
                    }
                }
                break;
                
            case 'update_bank':
                $bank_id = $_POST['bank_id'] ?? '';
                $bank_name = trim($_POST['bank_name'] ?? '');
                $account_name = trim($_POST['account_name'] ?? '');
                $account_number = trim($_POST['account_number'] ?? '');
                
                if (empty($bank_id) || empty($bank_name) || empty($account_name) || empty($account_number)) {
                    $error = 'All fields are required';
                } else {
                    $result = updateBankAccount($bank_id, $user_id, $bank_name, $account_name, $account_number);
                    if ($result['success']) {
                        $success = 'Bank account updated successfully!';
                    } else {
                        $error = $result['error'] ?? 'Failed to update bank account';
                    }
                }
                break;
                
            case 'delete_bank':
                $bank_id = $_POST['bank_id'] ?? '';
                
                if (empty($bank_id)) {
                    $error = 'Bank ID is required';
                } else {
                    $result = deleteBankAccount($bank_id, $user_id);
                    if ($result['success']) {
                        $success = 'Bank account deleted successfully!';
                    } else {
                        $error = $result['error'] ?? 'Failed to delete bank account';
                    }
                }
                break;
                
            case 'set_default':
                $bank_id = $_POST['bank_id'] ?? '';
                
                if (empty($bank_id)) {
                    $error = 'Bank ID is required';
                } else {
                    $result = setDefaultBankAccount($bank_id, $user_id);
                    if ($result['success']) {
                        $success = 'Default bank account updated successfully!';
                    } else {
                        $error = $result['error'] ?? 'Failed to set default bank account';
                    }
                }
                break;
        }
    }
    
    // Redirect to clear POST data
    redirect('payee.php');
}

// Get user's bank details
$bank_details = getUserBankDetails($user_id);
$bank_count = count($bank_details);
$max_banks = 3;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Details - Ninja Hope</title>
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
            --success-bg: #0d3019;
            --success-text: #4ade80;
            --error-bg: #2c0b07;
            --error-text: #f87171;
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
            border-radius: 20px; 
            padding: 1.5rem; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.2); 
            margin-bottom: 1.5rem;
            border: 1px solid var(--light-gray);
        }
        
        .card h3 {
            color: var(--text-light);
            margin-bottom: 1.2rem;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card h3 i {
            color: var(--accent-green);
        }
        
        .form-group { 
            margin-bottom: 1.2rem; 
        }
        
        label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: var(--text-light); 
            font-weight: 500; 
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        input, select { 
            width: 100%; 
            padding: 0.9rem 1rem; 
            border: 1px solid var(--light-gray); 
            border-radius: 12px; 
            font-size: 1rem; 
            background: var(--dim-black);
            color: var(--text-light);
            transition: all 0.3s ease;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 0 2px rgba(30, 132, 73, 0.2);
        }
        
        input::placeholder {
            color: var(--text-muted);
        }
        
        .btn { 
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white; 
            border: none; 
            padding: 1rem; 
            width: 100%; 
            border-radius: 12px; 
            font-size: 1rem; 
            cursor: pointer; 
            margin-top: 0.5rem; 
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: var(--light-gray);
            color: var(--text-light);
        }
        
        .btn-secondary:hover:not(:disabled) {
            background: var(--text-muted);
        }
        
        .btn-danger {
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-text);
        }
        
        .btn-danger:hover:not(:disabled) {
            background: var(--error-text);
            color: white;
        }
        
        .success { 
            background: var(--success-bg); 
            color: var(--success-text); 
            padding: 1rem; 
            border-radius: 12px; 
            margin: 1rem 0; 
            text-align: center; 
            font-weight: 600; 
            border-left: 4px solid var(--success-text);
        }
        
        .error { 
            background: var(--error-bg); 
            color: var(--error-text); 
            padding: 1rem; 
            border-radius: 12px; 
            margin: 1rem 0; 
            text-align: center; 
            font-weight: 600; 
            border-left: 4px solid var(--error-text);
        }
        
        .warning { 
            background: var(--warning-bg); 
            color: var(--warning-text); 
            padding: 1rem; 
            border-radius: 12px; 
            margin: 1rem 0; 
            text-align: center; 
            font-weight: 600; 
            border-left: 4px solid var(--warning-text);
        }
        
        .bank-card { 
            background: var(--dim-black); 
            border-left: 5px solid var(--accent-green); 
            padding: 1.2rem; 
            border-radius: 12px; 
            margin-bottom: 1rem; 
            transition: all 0.3s ease;
            border: 1px solid var(--light-gray);
            position: relative;
            overflow: hidden;
        }
        
        .bank-card.default {
            border-left-color: var(--accent-purple);
            background: linear-gradient(135deg, rgba(74, 35, 90, 0.1), rgba(10, 92, 54, 0.1));
        }
        
        .bank-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100%;
            background: linear-gradient(135deg, rgba(10, 92, 54, 0.1), rgba(74, 35, 90, 0.1));
            z-index: 0;
        }
        
        .bank-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .bank-card-content {
            position: relative;
            z-index: 1;
        }
        
        .bank-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.8rem;
        }
        
        .bank-name { 
            font-weight: 600; 
            color: var(--accent-green); 
            font-size: 1.1rem; 
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .default-badge {
            background: var(--accent-purple);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .bank-detail { 
            color: var(--text-muted); 
            font-size: 0.95rem; 
            margin: 0.3rem 0; 
        }
        
        .bank-detail strong {
            color: var(--text-light);
        }
        
        .bank-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .bank-action-btn {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid var(--light-gray);
            background: var(--dark-gray);
            color: var(--text-light);
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            min-width: 80px;
        }
        
        .bank-action-btn:hover {
            background: var(--light-gray);
        }
        
        .bank-action-btn.set-default:hover {
            background: var(--accent-purple);
            color: white;
            border-color: var(--accent-purple);
        }
        
        .bank-action-btn.delete:hover {
            background: var(--error-bg);
            color: var(--error-text);
            border-color: var(--error-text);
        }
        
        .limit { 
            text-align: center; 
            color: var(--text-muted); 
            font-size: 0.9rem; 
            margin-top: 1rem; 
            padding-top: 1rem;
            border-top: 1px solid var(--light-gray);
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
        
        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--light-gray);
            margin-bottom: 1rem;
            display: block;
        }
        
        /* Bank selection styling */
        .bank-select {
            position: relative;
        }
        
        .bank-select select {
            width: 100%;
            padding: 0.9rem 1rem;
            border: 1px solid var(--light-gray);
            border-radius: 12px;
            font-size: 1rem;
            background: var(--dim-black);
            color: var(--text-light);
            appearance: none;
            cursor: pointer;
        }
        
        .bank-select::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: var(--dark-gray);
            border-radius: 20px;
            padding: 1.5rem;
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--light-gray);
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.2rem;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .modal-close:hover {
            color: var(--error-text);
        }
        
        .modal-title {
            margin-bottom: 1.2rem;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal-actions {
            display: flex;
            gap: 0.8rem;
            margin-top: 1.5rem;
        }
        
        .modal-actions .btn {
            flex: 1;
            margin: 0;
        }
    </style>
</head>
<body>

    <div class="header">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1><i class="fas fa-university"></i> Bank Details</h1>
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
        
        <!-- Add Bank Form -->
        <?php if ($bank_count < $max_banks): ?>
        <div class="card">
            <h3><i class="fas fa-plus-circle"></i> Add Bank Account</h3>
            <form method="post">
                <input type="hidden" name="action" value="add_bank">
                <div class="form-group">
                    <label><i class="fas fa-building"></i> Bank Name</label>
                    <div class="bank-select">
                        <select name="bank_name" required>
                            <option value="">Select your bank</option>
                            <option value="GTBank">GTBank</option>
                            <option value="Opay">Opay</option>
                            <option value="Access Bank">Access Bank</option>
                            <option value="First Bank">First Bank</option>
                            <option value="Zenith Bank">Zenith Bank</option>
                            <option value="UBA">UBA</option>
                            <option value="Union Bank">Union Bank</option>
                            <option value="Fidelity Bank">Fidelity Bank</option>
                            <option value="Sterling Bank">Sterling Bank</option>
                            <option value="Polaris Bank">Polaris Bank</option>
                            <option value="other">Other Bank</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Account Name</label>
                    <input type="text" name="account_name" placeholder="Full name on account" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-credit-card"></i> Account Number</label>
                    <input type="text" name="account_number" placeholder="10-digit number" required maxlength="10" pattern="\d{10}" title="Please enter exactly 10 digits">
                </div>
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Save Bank Account
                </button>
            </form>
            <p class="limit">
                <i class="fas fa-info-circle"></i> You can add up to <?php echo $max_banks; ?> banks. (<?php echo $bank_count; ?>/<?php echo $max_banks; ?> used)
            </p>
        </div>
        <?php else: ?>
        <div class="warning">
            <i class="fas fa-exclamation-triangle"></i> You have reached the maximum limit of <?php echo $max_banks; ?> bank accounts.
        </div>
        <?php endif; ?>

        <!-- Saved Banks -->
        <div class="card">
            <h3><i class="fas fa-bookmark"></i> Your Saved Banks</h3>
            
            <?php if (empty($bank_details)): ?>
                <div class="empty-state">
                    <i class="fas fa-university"></i>
                    <p>No bank accounts added yet</p>
                    <p style="font-size: 0.8rem; margin-top: 0.5rem;">Add your first bank account to receive payments</p>
                </div>
            <?php else: ?>
                <?php foreach ($bank_details as $bank): ?>
                    <div class="bank-card <?php echo $bank['is_default'] ? 'default' : ''; ?>">
                        <div class="bank-card-content">
                            <div class="bank-header">
                                <div class="bank-name">
                                    <i class="fas fa-university"></i> <?php echo htmlspecialchars($bank['bank_name']); ?>
                                </div>
                                <?php if ($bank['is_default']): ?>
                                    <div class="default-badge">
                                        <i class="fas fa-star"></i> Default
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="bank-detail"><strong>Name:</strong> <?php echo htmlspecialchars($bank['account_name']); ?></div>
                            <div class="bank-detail"><strong>Account:</strong> <?php echo htmlspecialchars($bank['account_number']); ?></div>
                            <div class="bank-detail"><strong>Status:</strong> 
                                <span style="color: var(--accent-green);">
                                    <i class="fas fa-check-circle"></i> Verified
                                </span>
                            </div>
                            
                            <div class="bank-actions">
                                <?php if (!$bank['is_default']): ?>
                                    <form method="post" style="display: inline; flex: 1;">
                                        <input type="hidden" name="action" value="set_default">
                                        <input type="hidden" name="bank_id" value="<?php echo htmlspecialchars($bank['id']); ?>">
                                        <button type="submit" class="bank-action-btn set-default">
                                            <i class="fas fa-star"></i> Set Default
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <button class="bank-action-btn edit-bank" 
                                        data-bank-id="<?php echo htmlspecialchars($bank['id']); ?>"
                                        data-bank-name="<?php echo htmlspecialchars($bank['bank_name']); ?>"
                                        data-account-name="<?php echo htmlspecialchars($bank['account_name']); ?>"
                                        data-account-number="<?php echo htmlspecialchars($bank['account_number']); ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                
                                <button class="bank-action-btn delete delete-bank" 
                                        data-bank-id="<?php echo htmlspecialchars($bank['id']); ?>"
                                        data-bank-name="<?php echo htmlspecialchars($bank['bank_name']); ?>">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Security Notice -->
        <div class="card">
            <h3><i class="fas fa-shield-alt"></i> Security Notice</h3>
            <div style="color: var(--text-muted); line-height: 1.5; font-size: 0.9rem;">
                <div style="display: flex; align-items: flex-start; gap: 0.8rem; margin-bottom: 0.8rem;">
                    <i class="fas fa-lock" style="color: var(--accent-green); margin-top: 0.2rem;"></i>
                    <span>Your bank details are encrypted and stored securely</span>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 0.8rem; margin-bottom: 0.8rem;">
                    <i class="fas fa-user-shield" style="color: var(--accent-green); margin-top: 0.2rem;"></i>
                    <span>Only used for verified withdrawal requests</span>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 0.8rem;">
                    <i class="fas fa-exclamation-triangle" style="color: var(--warning-text); margin-top: 0.2rem;"></i>
                    <span>Never share your banking details with anyone</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Bank Modal -->
    <div class="modal" id="editBankModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('editBankModal')">&times;</button>
            <div class="modal-title">
                <i class="fas fa-edit"></i> Edit Bank Account
            </div>
            <form method="post" id="editBankForm">
                <input type="hidden" name="action" value="update_bank">
                <input type="hidden" name="bank_id" id="edit_bank_id">
                <div class="form-group">
                    <label><i class="fas fa-building"></i> Bank Name</label>
                    <div class="bank-select">
                        <select name="bank_name" id="edit_bank_name" required>
                            <option value="">Select your bank</option>
                            <option value="GTBank">GTBank</option>
                            <option value="Opay">Opay</option>
                            <option value="Access Bank">Access Bank</option>
                            <option value="First Bank">First Bank</option>
                            <option value="Zenith Bank">Zenith Bank</option>
                            <option value="UBA">UBA</option>
                            <option value="Union Bank">Union Bank</option>
                            <option value="Fidelity Bank">Fidelity Bank</option>
                            <option value="Sterling Bank">Sterling Bank</option>
                            <option value="Polaris Bank">Polaris Bank</option>
                            <option value="other">Other Bank</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Account Name</label>
                    <input type="text" name="account_name" id="edit_account_name" placeholder="Full name on account" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-credit-card"></i> Account Number</label>
                    <input type="text" name="account_number" id="edit_account_number" placeholder="10-digit number" required maxlength="10" pattern="\d{10}" title="Please enter exactly 10 digits">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editBankModal')">Cancel</button>
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Bank Modal -->
    <div class="modal" id="deleteBankModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('deleteBankModal')">&times;</button>
            <div class="modal-title">
                <i class="fas fa-trash"></i> Remove Bank Account
            </div>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                Are you sure you want to remove <strong id="delete_bank_name"></strong> from your saved banks?
            </p>
            <form method="post" id="deleteBankForm">
                <input type="hidden" name="action" value="delete_bank">
                <input type="hidden" name="bank_id" id="delete_bank_id">
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteBankModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </div>
            </form>
        </div>
    </div>

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
            <i class="fas fa-tasks"></i>
            Tasks
        </a>
        <a href="payee.php" class="nav-item active">
            <i class="fas fa-university"></i>
            Bank
        </a>
        <a href="chat.php" class="nav-item">
            <i class="fas fa-comments"></i>
            Support
        </a>
    </div>

    <script>
        // Modal functions
        function showModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        // Edit bank functionality
        document.querySelectorAll('.edit-bank').forEach(button => {
            button.addEventListener('click', function() {
                const bankId = this.getAttribute('data-bank-id');
                const bankName = this.getAttribute('data-bank-name');
                const accountName = this.getAttribute('data-account-name');
                const accountNumber = this.getAttribute('data-account-number');
                
                document.getElementById('edit_bank_id').value = bankId;
                document.getElementById('edit_bank_name').value = bankName;
                document.getElementById('edit_account_name').value = accountName;
                document.getElementById('edit_account_number').value = accountNumber;
                
                showModal('editBankModal');
            });
        });
        
        // Delete bank functionality
        document.querySelectorAll('.delete-bank').forEach(button => {
            button.addEventListener('click', function() {
                const bankId = this.getAttribute('data-bank-id');
                const bankName = this.getAttribute('data-bank-name');
                
                document.getElementById('delete_bank_id').value = bankId;
                document.getElementById('delete_bank_name').textContent = bankName;
                
                showModal('deleteBankModal');
            });
        });
        
        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
        
        // Account number validation
        document.querySelectorAll('input[name="account_number"]').forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 10);
            });
        });
    </script>
</body>
</html>