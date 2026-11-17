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

// Fetch upcoming deadlines/activities for the student calendar
$schedules = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM activities ORDER BY (deadline IS NULL), deadline ASC");
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $schedules = [];
}

$page_title = "Student Calendar";
$current_page = 'student_calendar.php'; // Ensures the Calendar tab is highlighted in the sidebar
require_once 'student_header.php'; // Uses the correct student sidebar header
?>

<div class="card" style="max-width: 900px; margin: 0 auto; background: #ffffff; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
        <i class="fa-solid fa-calendar-days" style="color: #8b5cf6; font-size: 1.3rem;"></i>
        <h3 style="margin: 0; font-size: 1.2rem; color: #1e293b;">Upcoming Deadlines & Schedule</h3>
    </div>

    <?php if (empty($schedules)): ?>
        <p style="color: #64748b; text-align: center; padding: 30px 0;">No upcoming schedules or deadlines found.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                        <th style="padding: 12px; color: #64748b; font-size: 0.85rem; font-weight: 600;">Activity Title</th>
                        <th style="padding: 12px; color: #64748b; font-size: 0.85rem; font-weight: 600;">Description</th>
                        <th style="padding: 12px; color: #64748b; font-size: 0.85rem; font-weight: 600;">Due Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $sched): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 14px 12px; font-weight: 600; color: #1e293b;"><?= htmlspecialchars($sched['title'] ?? 'Untitled') ?></td>
                            <td style="padding: 14px 12px; color: #334155;"><?= htmlspecialchars($sched['description'] ?? 'No description') ?></td>
                            <td style="padding: 14px 12px; color: #ef4444; font-weight: 500;">
                                <i class="fa-regular fa-clock"></i> <?= !empty($sched['deadline']) ? date('M d, Y h:i A', strtotime($sched['deadline'])) : 'No deadline' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php 
if (file_exists('footer.php')) {
    require_once 'footer.php';
}
?>