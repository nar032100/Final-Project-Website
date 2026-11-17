<?php
// 1. Database and Session logic
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') { 
    header("Location: login.php"); 
    exit; 
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['user_name'] ?? 'Student';

// Kunin ang statistics counts para sa dashboard
$activities_count = 0;
$submissions_count = 0;
$due_soon = [];

try {
    // Bilangin ang Activities / Submissions kung available ang tables
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activities");
    $stmt->execute();
    $activities_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $submissions_count = $stmt->fetchColumn();

    // Next 3 upcoming deadlines the student has not submitted yet
    $stmt = $pdo->prepare("SELECT a.id, a.title, a.deadline
                           FROM activities a
                           LEFT JOIN submissions s ON s.activity_id = a.id AND s.student_id = ?
                           WHERE a.deadline IS NOT NULL AND a.deadline >= NOW() AND s.id IS NULL
                           ORDER BY a.deadline ASC LIMIT 3");
    $stmt->execute([$student_id]);
    $due_soon = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback kung may table na hindi pa na-create
}

$page_title = "Student Dashboard";
$current_page = 'student_dashboard.php'; // Sinisigurong naka-highlight ang Dashboard sa sidebar
require_once 'student_header.php'; 
?>

<style>
    /* Dashboard Layout & Cards */
    .dashboard-container {
        display: flex;
        flex-direction: column;
        gap: 28px;
    }
    .welcome-section {
        margin-bottom: 8px;
    }
    .welcome-section h1 {
        font-size: 1.8rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 4px;
    }
    .welcome-section p {
        color: #64748b;
        font-size: 0.95rem;
    }

    /* Stats Grid Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
    }
    .stat-card {
        background: #ffffff;
        padding: 24px;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
    }
    .stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        background: #f3e8ff;
        color: #7c3aed;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }
    .stat-info .stat-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .stat-info .stat-value {
        font-size: 1.8rem;
        font-weight: 800;
        color: #1e293b;
    }

    /* Recent Projects Table Card */
    .content-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        padding: 24px;
    }
    .content-card h3 {
        font-size: 1.15rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        padding: 14px 16px;
        text-align: left;
        font-size: 0.9rem;
    }
    th {
        background: #f8fafc;
        color: #475569;
        font-weight: 600;
        border-bottom: 1px solid #e2e8f0;
    }
    td {
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
    }
    .due-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
    .due-item { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; background: #faf5ff; border: 1px solid #f3e8ff; }
    .due-item .due-title { font-weight: 600; color: #1e293b; }
    .due-item .due-when { font-size: 0.82rem; color: #7c3aed; font-weight: 600; white-space: nowrap; }
    .due-item .due-when.urgent { color: #ef4444; }
    .badge-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        font-size: 0.78rem;
        font-weight: 600;
        border-radius: 8px;
        text-decoration: none;
    }
    .btn-download {
        background: #dcfce7;
        color: #15803d;
    }
    .btn-link {
        background: #f3e8ff;
        color: #7c3aed;
    }
</style>

<div class="dashboard-container">
    <!-- Welcome Header -->
    <div class="welcome-section">
        <h1>Welcome back, <?= htmlspecialchars($student_name) ?>! 👋</h1>
        <p>Track your assigned activities, deadlines, and grades easily.</p>
    </div>

    <!-- Stats Cards Grid (Fixed overlapping numbers) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;">
                <i class="fa-solid fa-list-check"></i>
            </div>
            <div class="stat-info">
                <div class="stat-title">Activities</div>
                <div class="stat-value"><?= $activities_count ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                <i class="fa-solid fa-file-arrow-up"></i>
            </div>
            <div class="stat-info">
                <div class="stat-title">Submissions</div>
                <div class="stat-value"><?= $submissions_count ?></div>
            </div>
        </div>
    </div>

        <!-- Due Soon -->
    <div class="content-card">
        <h3><i class="fa-solid fa-hourglass-half" style="color:#8b5cf6;"></i> Due Soon</h3>
        <?php if (empty($due_soon)): ?>
            <p style="color:#64748b; margin:0;">Nothing due right now. Enjoy your day 🎉</p>
        <?php else: ?>
            <ul class="due-list">
                <?php foreach ($due_soon as $d):
                    $secs = strtotime($d['deadline']) - time();
                    $days = (int)floor($secs / 86400);
                    if ($secs < 86400 && date('Y-m-d', strtotime($d['deadline'])) === date('Y-m-d')) { $when = 'Due today'; }
                    elseif ($days <= 1) { $when = 'Due tomorrow'; }
                    else { $when = 'In ' . $days . ' days'; }
                ?>
                    <li class="due-item">
                        <span class="due-title"><?= htmlspecialchars($d['title']) ?></span>
                        <span class="due-when <?= $days <= 1 ? 'urgent' : '' ?>"><?= $when ?> &middot; <?= date('M d, h:i A', strtotime($d['deadline'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="student_activities.php" style="display:inline-block; margin-top:14px; color:#7c3aed; font-weight:600; font-size:0.88rem; text-decoration:none;">View all activities &rarr;</a>
        <?php endif; ?>
    </div>
</div>

<?php 
if (file_exists('footer.php')) {
    require_once 'footer.php';
}
?>