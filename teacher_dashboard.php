<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

$page_title = "Teacher Dashboard";
require_once 'header.php';

$total_students = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(role) = 'student'");
    $total_students = $stmt->fetchColumn();
} catch (Exception $e) {
    $total_students = 0;
}

// Latest real submissions
$recent = [];
try {
    $recent = $pdo->query("
        SELECT s.submission_url, s.file_path, s.submitted_at, u.full_name AS student_name, a.title AS act_title
        FROM submissions s
        JOIN users u ON u.id = s.student_id
        JOIN activities a ON a.id = s.activity_id
        ORDER BY s.submitted_at DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; }
    th, td { padding: 14px; text-align: left; font-size: 0.9rem; }
    th { background: #faf5ff; color: #6b21a8; font-weight: 600; border-bottom: 1px solid rgba(233, 213, 255, 0.8); }
    td { border-bottom: 1px solid rgba(233, 213, 255, 0.8); color: var(--text-dark); }
</style>

<div class="welcome-banner">
    <h1>Teacher Dashboard 📊</h1>
    <p>Welcome back, Teacher! Here is an overview of your classes and students.</p>
</div>

<div class="portal-container">
    <!-- STATS CARD -->
    <div style="max-width: 320px; margin-bottom: 4px;">
        <div class="card" style="padding: 24px;">
            <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">Total Enrolled Students</div>
            <div style="font-size: 2rem; font-weight: 800; color: #581c87; margin-top: 8px;"><?= $total_students ?></div>
        </div>
    </div>

    <!-- SUGGESTED FEATURE: RECENT STUDENT SUBMISSIONS -->
    <div class="card">
        <h3 style="font-size: 1.2rem; font-weight: 700; color: #581c87; margin-top: 0; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-clock-rotate-left" style="color: #9333ea;"></i> Recent Student Submissions & Activity
        </h3>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Activity</th>
                        <th>Date Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No submissions yet.</td></tr>
                    <?php else: foreach ($recent as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['student_name']) ?></strong></td>
                            <td><?= htmlspecialchars($r['act_title']) ?></td>
                            <td style="color: var(--text-muted);"><?= date('M d, Y h:i A', strtotime($r['submitted_at'])) ?></td>
                            <td>
                                <?php if (!empty($r['file_path'])): ?>
                                    <a href="<?= htmlspecialchars($r['file_path']) ?>" download style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.8rem; text-decoration: none; margin-right: 6px;"><i class="fa-solid fa-download"></i> View File</a>
                                <?php endif; ?>
                                <?php if (!empty($r['submission_url']) && preg_match('#^https?://#i', $r['submission_url'])): ?>
                                    <a href="<?= htmlspecialchars($r['submission_url']) ?>" target="_blank" rel="noopener noreferrer" style="background: #f3e8ff; color: #6b21a8; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.8rem; text-decoration: none;"><i class="fa-solid fa-link"></i> Check Link</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php echo "</div>"; ?>