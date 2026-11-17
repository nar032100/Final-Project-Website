<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_role']) !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Ligtas na pagkuha ng user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user = [];
}

// Ligtas na pag-handle ng form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    try {
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $error_msg = "Ilagay ang iyong kasalukuyang password para makapagpalit ng bago.";
            } else {
                $password_matches = password_verify($current_password, $user['password'] ?? '') || ($current_password === ($user['password'] ?? ''));
                
                if (!$password_matches) {
                    $error_msg = "Mali ang iyong kasalukuyang password.";
                } elseif ($new_password !== $confirm_password) {
                    $error_msg = "Hindi magkatugma ang bagong password at confirmation.";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_pass = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?");
                    $update_pass->execute([$full_name, $email, $hashed_password, $user_id]);
                    $success_msg = "Matagumpay na na-update ang account at password!";
                }
            }
        } else {
            $update_stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            $update_stmt->execute([$full_name, $email, $user_id]);
            $success_msg = "Matagumpay na na-update ang mga detalye ng account!";
        }
        
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error_msg = "May naganap na error sa pag-update.";
    }
}

$page_title = "Account Settings";
$current_page = 'student_settings.php'; // Sinisigurong naka-highlight ang Settings sa sidebar
require_once 'student_header.php'; // Ginamit ang tamang student sidebar header
?>

<style>
    .portal-container { 
        display: flex; 
        flex-direction: column; 
        gap: 20px; 
        margin-top: 10px; 
    }
    .card {
        background: rgba(255, 255, 255, 0.90);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        padding: 32px; 
        border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.4);
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        max-width: 650px;
        width: 100%;
    }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-weight: 600; color: #1e293b; margin-bottom: 8px; font-size: 0.9rem; }
    .form-control {
        width: 100%; padding: 12px 16px; border-radius: 10px;
        border: 1px solid rgba(203, 213, 225, 0.8); background: rgba(255, 255, 255, 0.9);
        font-size: 0.95rem; color: #1e293b;
    }
    .form-control:focus { border-color: #6366f1; outline: none; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
    
    /* Istilo para sa Password Input na may Eye Icon */
    .password-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }
    .password-wrapper input {
        padding-right: 45px;
    }
    .toggle-password {
        position: absolute;
        right: 15px;
        cursor: pointer;
        color: #64748b;
        font-size: 1rem;
        transition: color 0.2s;
    }
    .toggle-password:hover {
        color: #6366f1;
    }

    .divider { height: 1px; background: rgba(203, 213, 225, 0.5); margin: 24px 0; }
    .alert-success { background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 500; }
    .alert-error { background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 500; }
    .btn-primary {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: #ffffff; border: none; padding: 12px 24px; border-radius: 12px;
        font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
    }
</style>

<div class="portal-container">
    <div class="card">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 24px;">
            <i class="fa-solid fa-gear" style="font-size: 1.4rem; color: #6366f1;"></i>
            <h2 style="font-size: 1.3rem; font-weight: 700; color: #1e293b; margin: 0;">Account Settings</h2>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Account Name:</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
            </div>

            <div class="divider"></div>

            <h3 style="font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 16px;">
                <i class="fa-solid fa-lock" style="color: #6366f1;"></i> Change Password
            </h3>

            <div class="form-group">
                <label>Current Password :</label>
                <div class="password-wrapper">
                    <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Enter Current Password">
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('current_password', this)"></i>
                </div>
            </div>

            <div class="form-group">
                <label>New Password:</label>
                <div class="password-wrapper">
                    <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Enter New password">
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('new_password', this)"></i>
                </div>
            </div>

            <div class="form-group">
                <label>Confirm New Password:</label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Enter New Password">
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                </div>
            </div>

            <div style="margin-top: 28px;">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Update New Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePassword(fieldId, iconElement) {
    const passwordField = document.getElementById(fieldId);
    if (passwordField.type === "password") {
        passwordField.type = "text";
        iconElement.classList.remove("fa-eye");
        iconElement.classList.add("fa-eye-slash");
    } else {
        passwordField.type = "password";
        iconElement.classList.remove("fa-eye-slash");
        iconElement.classList.add("fa-eye");
    }
}
</script>

<?php 
if (file_exists('footer.php')) {
    require_once 'footer.php';
}
?>