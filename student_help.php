<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$page_title = "Help & Guide";
$current_page = 'student_help.php';
require_once 'student_header.php';

$tips = [
    ['fa-list-check', '#8b5cf6', 'Activities & Deadlines', 'Open <strong>Activities</strong> to see tasks from your instructors. Click <strong>Submit</strong>, add a link and/or a file (ZIP, PDF, DOC, PPT, TXT or an image like JPG/PNG, max 5 MB), then confirm.'],
    ['fa-hourglass-half', '#f97316', 'Due Soon', 'Your <strong>Dashboard</strong> shows the next deadlines you have not submitted yet, so you always know what to work on first.'],
    ['fa-calendar-days', '#0ea5e9', 'Calendar', 'The <strong>Calendar</strong> lists every activity with its deadline in date order.'],
    ['fa-file-lines', '#16a34a', 'Grades', 'Check <strong>Academic Records</strong> for your grades. You can resubmit an activity until it has been graded.'],
    ['fa-bell', '#ef4444', 'Notifications', 'A red badge next to <strong>Notifications</strong> shows how many unread messages you have, such as new announcements.'],
];
?>

<div style="max-width: 900px; margin: 0 auto; background: #ffffff; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
        <i class="fa-solid fa-circle-question" style="color: #8b5cf6; font-size: 1.3rem;"></i>
        <h3 style="margin: 0; font-size: 1.2rem; color: #1e293b;">Student Portal Guide</h3>
    </div>
    <p style="color: #64748b; margin: 0 0 20px 0;">Welcome to <strong>StudentWorks</strong>! Here is a quick guide to using the portal.</p>

    <div style="display: flex; flex-direction: column; gap: 14px; color: #334155; line-height: 1.6; font-size: 0.95rem;">
        <?php foreach ($tips as [$icon, $color, $title, $text]): ?>
            <div style="background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <h4 style="margin: 0 0 6px 0; color: #1e293b;"><i class="fa-solid <?= $icon ?>" style="color: <?= $color ?>;"></i> <?= $title ?></h4>
                <p style="margin: 0;"><?= $text ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
if (file_exists('footer.php')) {
    require_once 'footer.php';
}
?>