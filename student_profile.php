<?php
session_start();
require_once 'db.php';

// Siguraduhing naka-login ang estudyante
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_role']) !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Kunin ang kasalukuyang impormasyon ng estudyante mula sa database
try {
    $stmt = $pdo->prepare("SELECT full_name, email, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $student_name = $user['full_name'] ?? 'Student';
    $student_email = $user['email'] ?? '';
    $student_role = ucfirst($user['role'] ?? 'Student');
    $student_joined = isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'Oct 14, 2026';

} catch (PDOException $e) {
    $student_name = $_SESSION['user_name'] ?? 'Student';
    $student_email = '';
    $student_role = 'Student';
    $student_joined = 'Oct 14, 2026';
}

$page_title = "My Profile";
$current_page = 'student_profile.php'; // Sinisigurong naka-highlight ang Profile sa sidebar
require_once 'student_header.php'; // Ginamit ang tamang student sidebar header
?>

<style>
    /* Modern Profile Card Styling */
    .profile-container {
        max-width: 600px;
        margin: 0 auto;
    }

    .profile-card {
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.5);
        box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.1);
        padding: 40px;
        position: relative;
        overflow: hidden;
    }

    .profile-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .profile-avatar {
        width: 90px;
        height: 90px;
        background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        color: #ffffff;
        font-size: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin: 0 auto 16px auto;
        box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
    }

    .profile-header h2 {
        font-size: 1.5rem;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.5px;
    }

    .profile-header p {
        color: #64748b;
        font-size: 0.9rem;
        font-weight: 500;
        margin-top: 4px;
    }

    .profile-details {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .profile-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        transition: all 0.2s ease;
    }

    .profile-item:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .profile-label {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.88rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .profile-label i {
        color: #6366f1;
        font-size: 1rem;
        width: 20px;
        text-align: center;
    }

    .profile-value {
        font-size: 0.95rem;
        font-weight: 700;
        color: #0f172a;
    }

    .badge-role {
        background: #eef2ff;
        color: #4f46e5;
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 700;
        border: 1px solid rgba(99, 102, 241, 0.2);
    }
</style>

<div class="profile-container">
    <div class="profile-card">
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fa-solid fa-user"></i>
            </div>
            <h2><?= htmlspecialchars($student_name) ?></h2>
            <p>Student Account</p>
        </div>

        <div class="profile-details">
            <div class="profile-item">
                <div class="profile-label">
                    <i class="fa-solid fa-signature"></i> Full Name
                </div>
                <div class="profile-value">
                    <?= htmlspecialchars($student_name) ?>
                </div>
            </div>

            <div class="profile-item">
                <div class="profile-label">
                    <i class="fa-solid fa-envelope"></i> Email
                </div>
                <div class="profile-value" style="font-weight: 600; color: #475569;">
                    <?= htmlspecialchars($student_email) ?>
                </div>
            </div>

            <div class="profile-item">
                <div class="profile-label">
                    <i class="fa-solid fa-shield-halved"></i> Role
                </div>
                <div class="profile-value">
                    <span class="badge-role"><?= htmlspecialchars($student_role) ?></span>
                </div>
            </div>

            <div class="profile-item">
                <div class="profile-label">
                    <i class="fa-solid fa-calendar-check"></i> Joined Date
                </div>
                <div class="profile-value">
                    <?= htmlspecialchars($student_joined) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
if (file_exists('footer.php')) {
    require_once 'footer.php';
}
?>