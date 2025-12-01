<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$user_id = $user['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['proof_image'])) {
    $task_id = $_POST['task_id'] ?? '';
    $proof_file = $_FILES['proof_image'] ?? null;
    
    if (empty($task_id) || !$proof_file || $proof_file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Please select a valid proof image';
    } else {
        // Check if user already submitted this task
        $existing_submission = hasUserSubmittedTask($user_id, $task_id);
        if ($existing_submission) {
            $_SESSION['error'] = 'You have already submitted proof for this task';
        } else {
            $result = submitTaskProof($user_id, $task_id, $proof_file);
            if ($result['success']) {
                $_SESSION['success'] = 'Proof submitted successfully! Please wait for admin approval.';
            } else {
                $_SESSION['error'] = $result['error'] ?? 'Failed to submit proof';
            }
        }
    }
    
    redirect('tasks.php');
}

$tasks = getActiveTasks();
$user_submissions = getUserTaskSubmissions($user_id);
$stats = getTaskStats($user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - Ninja Hope</title>
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
            --pending-bg: #332701;
            --pending-text: #fbbf24;
            --approved-bg: #0d3019;
            --approved-text: #4ade80;
            --rejected-bg: #2c0b07;
            --rejected-text: #f87171;
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
            padding: 1rem; 
        }
        
        /* Stats Section */
        .task-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: var(--dark-gray);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--light-gray);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 0.3rem;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        /* Task Cards */
        .task-card { 
            background: var(--dark-gray); 
            margin-bottom: 1rem; 
            border-radius: 16px; 
            padding: 1.2rem; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            border: 1px solid var(--light-gray);
            transition: all 0.3s ease;
        }
        
        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.3);
        }
        
        .task-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 0.8rem;
        }
        
        .task-title { 
            font-weight: 600; 
            font-size: 1.1rem; 
            color: var(--text-light); 
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .task-title i {
            color: var(--accent-green);
        }
        
        .platform-badge {
            background: var(--accent-purple);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .task-desc { 
            color: var(--text-muted); 
            font-size: 0.95rem; 
            margin: 0.5rem 0; 
            line-height: 1.5;
        }
        
        .reward { 
            font-weight: 700; 
            color: var(--accent-green); 
            font-size: 1.2rem; 
            margin: 0.8rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .task-link { 
            color: #3498db; 
            font-size: 0.9rem; 
            word-break: break-all; 
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.8rem 0;
            transition: color 0.3s;
            padding: 0.8rem;
            background: var(--dim-black);
            border-radius: 8px;
            border: 1px solid var(--light-gray);
        }
        
        .task-link:hover {
            color: #2980b9;
            background: var(--light-gray);
        }
        
        .status { 
            padding: 0.8rem 1rem; 
            border-radius: 12px; 
            font-size: 0.85rem; 
            font-weight: 500; 
            margin: 1rem 0;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .pending { 
            background: var(--pending-bg); 
            color: var(--pending-text);
            border-left: 4px solid var(--pending-text);
        }
        
        .approved { 
            background: var(--approved-bg); 
            color: var(--approved-text);
            border-left: 4px solid var(--approved-text);
        }
        
        .rejected { 
            background: var(--rejected-bg); 
            color: var(--rejected-text);
            border-left: 4px solid var(--rejected-text);
        }
        
        .submit-form { 
            margin-top: 1rem; 
            padding-top: 1rem;
            border-top: 1px solid var(--light-gray);
        }
        
        .file-input {
            display: block;
            margin: 0.8rem 0;
            font-size: 0.9rem;
            padding: 0.8rem;
            background: var(--dim-black);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            color: var(--text-light);
            width: 100%;
        }
        
        .file-input:focus {
            outline: none;
            border-color: var(--accent-green);
        }
        
        .submit-btn { 
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white; 
            border: none; 
            padding: 0.8rem; 
            width: 100%; 
            border-radius: 12px; 
            font-size: 1rem; 
            cursor: pointer; 
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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
        
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin: 1rem;
            font-weight: 600;
            text-align: center;
        }
        
        .alert-success {
            background: var(--approved-bg);
            color: var(--approved-text);
            border-left: 4px solid var(--approved-text);
        }
        
        .alert-error {
            background: var(--rejected-bg);
            color: var(--rejected-text);
            border-left: 4px solid var(--rejected-text);
        }
        
        .completions-info {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
        }
        
        .no-tasks {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }
        
        .no-tasks i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
            opacity: 0.5;
        }
    </style>
</head>
<body>

    <div class="header">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1><i class="fas fa-tasks"></i> Daily Tasks</h1>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Task Statistics -->
        <div class="task-stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($tasks); ?></div>
                <div class="stat-label">Available Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₦<?php echo number_format($stats['total_earned'], 2); ?></div>
                <div class="stat-label">Total Earned</div>
            </div>
        </div>
        
        <!-- Task Cards -->
        <?php if (empty($tasks)): ?>
            <div class="no-tasks">
                <i class="fas fa-tasks"></i>
                <h3>No tasks available at the moment</h3>
                <p>Check back later for new tasks!</p>
            </div>
        <?php else: ?>
            <?php foreach ($tasks as $task): ?>
                <?php
                $submission = hasUserSubmittedTask($user_id, $task['id']);
                $can_submit = !$submission || ($submission && $submission['status'] === 'rejected');
                $is_pending = $submission && $submission['status'] === 'pending';
                $is_approved = $submission && $submission['status'] === 'approved';
                $is_rejected = $submission && $submission['status'] === 'rejected';
                ?>
    
                <div class="task-card">
                    <div class="task-header">
                        <div class="task-title">
                            <i class="fas fa-task"></i>
                            <?php echo htmlspecialchars($task['title']); ?>
                        </div>
                        <div class="platform-badge">
                            <?php echo htmlspecialchars($task['platform']); ?>
                        </div>
                    </div>
        
                    <?php if (!empty($task['description'])): ?>
                        <div class="task-desc"><?php echo htmlspecialchars($task['description']); ?></div>
                    <?php endif; ?>
        
                    <div class="reward">
                        <i class="fas fa-coins"></i>
                        Reward: ₦<?php echo number_format($task['reward_amount'], 2); ?>
                    </div>
        
                    <a href="<?php echo htmlspecialchars($task['task_url']); ?>" target="_blank" class="task-link">
                        <i class="fas fa-external-link-alt"></i> Open Task Link
                    </a>
        
                    <?php if ($task['max_completions']): ?>
                        <div class="completions-info">
                            <i class="fas fa-users"></i> 
                            <?php echo $task['current_completions'] . '/' . $task['max_completions']; ?> completions
                        </div>
                    <?php endif; ?>
        
                    <?php if ($is_approved): ?>
                        <div class="status approved">
                            <i class="fas fa-check-circle"></i> Approved +₦<?php echo number_format($task['reward_amount'], 2); ?>
                        </div>
                    <?php elseif ($is_pending): ?>
                        <div class="status pending">
                            <i class="fas fa-clock"></i> Under Review
                        </div>
                    <?php elseif ($is_rejected): ?>
                        <div class="status rejected">
                            <i class="fas fa-times-circle"></i> Rejected
                            <?php if (!empty($submission['admin_notes'])): ?>
                                <br><small>Note: <?php echo htmlspecialchars($submission['admin_notes']); ?></small>
                            <?php endif; ?>
                        </div>
            
                        <!-- Allow resubmission for rejected tasks -->
                        <form method="post" enctype="multipart/form-data" class="submit-form">
                            <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task['id']); ?>">
                            <input type="file" name="proof_image" accept="image/*" required class="file-input">
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-upload"></i> Resubmit Proof
                            </button>
                        </form>
                    <?php elseif ($can_submit): ?>
                        <form method="post" enctype="multipart/form-data" class="submit-form">
                            <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task['id']); ?>">
                            <input type="file" name="proof_image" accept="image/*" required class="file-input">
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-upload"></i> Submit Proof
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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
        <a href="tasks.php" class="nav-item active">
            <i class="fas fa-tasks"></i>
            Tasks
        </a>
        <a href="agent.php" class="nav-item">
            <i class="fas fa-user-friends"></i>
            Agent
        </a>
        <a href="chat.php" class="nav-item">
            <i class="fas fa-comments"></i>
            Support
        </a>
    </div>

</body>
</html>