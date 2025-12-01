<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$user_id = $user['id'];

// Sync verification status first
syncFaceVerificationStatus($user_id);
$verification_status = getUserVerificationStatus($user_id);

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['face'])) {
    $file = $_FILES['face'];
    
    // Check if user already has a pending verification
    if ($verification_status['status'] === 'pending') {
        $error = 'You already have a pending verification. Please wait for approval.';
    } elseif ($verification_status['status'] === 'approved') {
        $error = 'Your face verification has already been approved.';
    } else {
        // Upload image to Supabase Storage
        $upload_result = uploadFaceImageToStorage($file, $user_id);
        
        if ($upload_result['success']) {
            // Submit verification
            $submit_result = submitFaceVerification(
                $user_id, 
                $upload_result['public_url'], 
                $upload_result['storage_path']
            );
            
            if ($submit_result['success']) {
                $message = "Image uploaded! Your code: {$submit_result['code']} — Pending approval.";
                // Refresh status
                $verification_status = getUserVerificationStatus($user_id);
            } else {
                $error = $submit_result['error'];
                // Delete uploaded file if submission failed
                if (!empty($upload_result['storage_path'])) {
                    deleteFaceImageFromStorage($upload_result['storage_path']);
                }
            }
        } else {
            $error = $upload_result['error'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Verification - Ninja Hope</title>
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
            --success-bg: #0d3019;
            --success-text: #4ade80;
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
            padding-bottom: 80px; 
            min-height: 100vh;
            color: var(--text-light);
        }
        
        .header { 
            background: linear-gradient(135deg, var(--dark-green), var(--dark-purple));
            color: white; 
            padding: 1.2rem; 
            text-align: center; 
            position: relative;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
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
        
        .header h1 { 
            font-size: 1.3rem; 
            font-weight: 600; 
        }

        .container { 
            padding: 1.5rem; 
        }

        .card { 
            background: var(--dark-gray); 
            border-radius: 20px; 
            padding: 1.8rem; 
            text-align: center; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            margin-bottom: 1.5rem;
            border: 1px solid var(--light-gray);
        }

        .status-box {
            padding: 1rem;
            border-radius: 12px;
            margin: 1rem 0;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-left: 4px solid;
        }
        
        .pending { 
            background: var(--warning-bg); 
            color: var(--warning-text);
            border-left-color: var(--warning-text);
        }
        
        .approved { 
            background: var(--success-bg); 
            color: var(--success-text);
            border-left-color: var(--success-text);
        }
        
        .rejected {
            background: var(--error-bg);
            color: var(--error-text);
            border-left-color: var(--error-text);
        }

        .upload-box { 
            border: 3px dashed var(--accent-green); 
            border-radius: 20px; 
            padding: 2.5rem 1.5rem; 
            margin: 1.5rem 0;
            background: rgba(30, 132, 73, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .upload-box:hover {
            background: rgba(30, 132, 73, 0.15);
            border-color: var(--accent-green);
        }
        
        .upload-box.drag-over {
            background: rgba(30, 132, 73, 0.2);
            border-color: var(--accent-green);
            transform: scale(1.02);
        }
        
        .upload-icon {
            font-size: 3rem;
            color: var(--accent-green);
            margin-bottom: 1rem;
            display: block;
        }
        
        .upload-box input {
            display: block;
            margin: 1rem auto;
            font-size: 1rem;
            padding: 0.8rem;
            background: var(--dim-black);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            color: var(--text-light);
            width: 100%;
            max-width: 300px;
            cursor: pointer;
        }
        
        .upload-box p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 0.5rem;
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
            margin: 0.8rem 0;
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

        .success, .error { 
            padding: 1rem; 
            border-radius: 12px; 
            margin: 1rem 0; 
            font-weight: 600; 
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-left: 4px solid;
        }
        
        .success { 
            background: var(--success-bg); 
            color: var(--success-text);
            border-left-color: var(--success-text);
        }
        
        .error { 
            background: var(--error-bg); 
            color: var(--error-text);
            border-left-color: var(--error-text);
        }

        .code {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--accent-green);
            margin: 1rem 0;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
        }

        .face-img {
            max-width: 100%;
            border-radius: 12px;
            margin: 1rem 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 2px solid var(--light-gray);
        }

        .requirements {
            background: var(--dim-black);
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            border: 1px solid var(--light-gray);
            text-align: left;
        }
        
        .requirement-item {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            margin-bottom: 0.8rem;
            padding: 0.5rem 0;
        }
        
        .requirement-item i {
            color: var(--accent-green);
            margin-top: 0.2rem;
            flex-shrink: 0;
        }
        
        .requirement-text {
            color: var(--text-light);
            font-size: 0.9rem;
            line-height: 1.4;
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
        
        .preview-container {
            display: none;
            margin: 1.5rem 0;
            text-align: center;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 12px;
            border: 2px solid var(--accent-green);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .rejection-reason {
            background: var(--error-bg);
            color: var(--error-text);
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border-left: 4px solid var(--error-text);
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1><i class="fas fa-user-check"></i> Face Verification</h1>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <?php if ($verification_status['status'] === 'approved'): ?>
                <div class="status-box approved">
                    <i class="fas fa-check-circle"></i> Verification Approved!
                </div>
                <div style="margin: 1.5rem 0;">
                    <i class="fas fa-user-check" style="font-size: 4rem; color: var(--accent-green); margin-bottom: 1rem;"></i>
                    <h3 style="color: var(--success-text);">Your face verification has been approved!</h3>
                    <p style="color: var(--text-muted); margin-top: 0.5rem;">You can now access all platform features.</p>
                </div>
            <?php elseif ($verification_status['status'] === 'pending'): ?>
                <div class="status-box pending">
                    <i class="fas fa-clock"></i> Pending Approval
                </div>
                <h3 style="margin-bottom: 1rem;">Your verification is under review</h3>
                <p style="color: var(--text-muted); margin-bottom: 1rem;">We'll notify you once it's processed.</p>
                <div class="code"><?php echo htmlspecialchars($verification_status['code']); ?></div>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Your verification code</p>
            <?php elseif ($verification_status['status'] === 'rejected'): ?>
                <div class="status-box rejected">
                    <i class="fas fa-times-circle"></i> Verification Rejected
                </div>
                <h3 style="margin-bottom: 1rem;">Please try again</h3>
                <?php if ($verification_status['rejection_reason']): ?>
                    <div class="rejection-reason">
                        <strong>Reason:</strong> <?php echo htmlspecialchars($verification_status['rejection_reason']); ?>
                    </div>
                <?php endif; ?>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                    Please upload a new photo that meets our requirements.
                </p>
            <?php else: ?>
                <h3 style="margin-bottom:0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <i class="fas fa-camera"></i> Upload Your Selfie
                </h3>
                
                <p style="color:var(--text-muted);margin:1rem 0;font-size:0.95rem; text-align: center;">
                    Clear face photo required for account verification and security.
                </p>
            <?php endif; ?>
            
            <?php if ($verification_status['status'] === 'none' || $verification_status['status'] === 'rejected'): ?>
                <div class="requirements">
                    <div class="requirement-item">
                        <i class="fas fa-check-circle"></i>
                        <div class="requirement-text">
                            <strong>Good Lighting:</strong> Face should be clearly visible with no shadows
                        </div>
                    </div>
                    <div class="requirement-item">
                        <i class="fas fa-check-circle"></i>
                        <div class="requirement-text">
                            <strong>Front View:</strong> Face should be facing directly towards the camera
                        </div>
                    </div>
                    <div class="requirement-item">
                        <i class="fas fa-check-circle"></i>
                        <div class="requirement-text">
                            <strong>No Accessories:</strong> Remove sunglasses, hats, or face coverings
                        </div>
                    </div>
                    <div class="requirement-item">
                        <i class="fas fa-check-circle"></i>
                        <div class="requirement-text">
                            <strong>High Quality:</strong> Image should be clear and not blurry
                        </div>
                    </div>
                </div>
                
                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <div class="upload-box" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <h4 style="color: var(--text-light); margin-bottom: 0.5rem;">Drag & Drop or Click to Upload</h4>
                        <input type="file" name="face" accept="image/*" required id="fileInput">
                        <p>JPG, PNG, WEBP • Max 5MB</p>
                    </div>
                    
                    <div class="preview-container" id="previewContainer">
                        <h4 style="color: var(--text-light); margin-bottom: 1rem;">Preview</h4>
                        <img src="" alt="Preview" class="preview-image" id="previewImage">
                        <button type="button" class="btn" onclick="clearPreview()" style="margin-top: 1rem; background: var(--light-gray);">
                            <i class="fas fa-times"></i> Choose Different Photo
                        </button>
                    </div>
                    
                    <button type="submit" class="btn" id="submitBtn" disabled>
                        <i class="fas fa-paper-plane"></i> Upload & Submit for Verification
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h3 style="margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                <i class="fas fa-shield-alt"></i> Why We Verify
            </h3>
            <div style="color: var(--text-muted); line-height: 1.5; text-align: left;">
                <div style="display: flex; align-items: flex-start; gap: 0.8rem; margin-bottom: 0.8rem;">
                    <i class="fas fa-user-shield" style="color: var(--accent-green); margin-top: 0.2rem;"></i>
                    <span>Prevents fake accounts and ensures platform security</span>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 0.8rem; margin-bottom: 0.8rem;">
                    <i class="fas fa-lock" style="color: var(--accent-green); margin-top: 0.2rem;"></i>
                    <span>Required for withdrawals and higher earning limits</span>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 0.8rem;">
                    <i class="fas fa-check-circle" style="color: var(--accent-green); margin-top: 0.2rem;"></i>
                    <span>One-time verification for lifetime account access</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item">
            <i class="fas fa-home"></i>
            Home
        </a>
        <a href="auth.php" class="nav-item active">
            <i class="fas fa-user-check"></i>
            Authentication
        </a>
        <a href="tasks.php" class="nav-item">
            <i class="fas fa-tasks"></i>
            Tasks
        </a>
        <a href="agent.php" class="nav-item">
            <i class="fas fa-user-friends"></i>
            Agent
        </a>
        <a href="chat.php" class="nav-item">
            <i class="fas fa-comments"></i>
            Chat support
        </a>
    </div>

    <script>
        const fileInput = document.getElementById('fileInput');
        const uploadArea = document.getElementById('uploadArea');
        const previewContainer = document.getElementById('previewContainer');
        const previewImage = document.getElementById('previewImage');
        const submitBtn = document.getElementById('submitBtn');
        const uploadForm = document.getElementById('uploadForm');
        
        // Drag and drop functionality
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            uploadArea.classList.add('drag-over');
        }
        
        function unhighlight() {
            uploadArea.classList.remove('drag-over');
        }
        
        uploadArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            handleFiles(files);
        }
        
        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });
        
        function handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        previewContainer.style.display = 'block';
                        uploadArea.style.display = 'none';
                        submitBtn.disabled = false;
                    };
                    reader.readAsDataURL(file);
                }
            }
        }
        
        function clearPreview() {
            previewContainer.style.display = 'none';
            uploadArea.style.display = 'block';
            fileInput.value = '';
            submitBtn.disabled = true;
        }
        
        // Form submission loading state
        uploadForm.addEventListener('submit', function() {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>