<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

$page_title = "Account Settings";
require_once 'header.php';

// Fetch current user details
$user_id = $_SESSION['user_id'];
$user_name = '';
$user_email = '';

try {
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $user_name = $user['full_name'] ?? '';
        $user_email = $user['email'] ?? '';
    }
} catch (Exception $e) {}
?>

<style>
    .portal-container {
        display: flex;
        flex-direction: column;
        align-items: center; /* Gitna horizontally */
        width: 100%;
    }
    .form-container {
        max-width: 650px;
        width: 100%;
        margin: 0 auto; /* Naka-center ang card */
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        font-weight: 700;
        color: #581c87;
        margin-bottom: 8px;
        font-size: 0.95rem;
    }
    .form-control {
        width: 100%;
        padding: 12px 16px;
        border-radius: 12px;
        border: 1px solid rgba(233, 213, 255, 0.9);
        background: #ffffff;
        font-size: 0.95rem;
        color: var(--text-dark);
        box-sizing: border-box;
        font-family: inherit;
        transition: all 0.3s ease;
    }
    .form-control:focus {
        outline: none;
        border-color: #9333ea;
        box-shadow: 0 0 0 4px rgba(147, 51, 234, 0.12);
    }
    .form-control[readonly] {
        background: #faf5ff;
        color: var(--text-muted);
        cursor: not-allowed;
    }
</style>

<div class="welcome-banner" style="text-align: center;">
    <h1>Account Settings ⚙️</h1>
    <p>Manage your profile info, email address, and account security passwords.</p>
</div>

<div class="portal-container">
    <div class="card form-container">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: #581c87; margin-top: 0; margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-user-gear" style="color: #9333ea;"></i> Update Profile & Password
        </h3>

        <form method="POST" action="teacher_settings.php">
            <div class="form-group">
                <label for="account_name">Account Name</label>
                <input type="text" id="account_name" name="account_name" class="form-control" value="<?= htmlspecialchars($user_name) ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user_email) ?>" readonly>
            </div>

            <hr style="border: 0; border-top: 1px solid rgba(233, 213, 255, 0.8); margin: 24px 0;">

            <div class="form-group">
                <label for="new_password">New Password (Optional)</label>
                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Leave blank to keep current password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-type new password">
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 28px;">
                <button type="submit" name="update_settings" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-floppy-disk"></i> Update Profile
                </button>
            </div>
        </form>
    </div>
</div>

<?php echo "</div>"; ?>