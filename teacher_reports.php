<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
require_once 'assignment_delete.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

// Delete an assignment (runs before any HTML so the redirect works)
$del = process_assignment_delete($pdo);
if ($del !== null) {
    $_SESSION['asg_flash'] = $del;
    header("Location: teacher_reports.php");
    exit;
}
$flash = ['type' => '', 'msg' => ''];
if (isset($_SESSION['asg_flash'])) { $flash = $_SESSION['asg_flash']; unset($_SESSION['asg_flash']); }

$page_title = "Teacher Reports";

$total_students = $total_activities = $total_subs = $graded = 0;
$avg_grade = null;
$rows = [];

try {
    $total_students   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(role) = 'student'")->fetchColumn();
    $total_activities = (int)$pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();
    $total_subs       = (int)$pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
    $graded           = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE grade IS NOT NULL AND grade <> ''")->fetchColumn();

    // Average of numeric grades only (ignores letter grades)
    $nums = [];
    foreach ($pdo->query("SELECT grade FROM submissions WHERE grade IS NOT NULL AND grade <> ''")->fetchAll(PDO::FETCH_COLUMN) as $g) {
        if (is_numeric($g)) { $nums[] = (float)$g; }
    }
    if ($nums) { $avg_grade = array_sum($nums) / count($nums); }

    $rows = $pdo->query("
        SELECT a.id, a.teacher_id, a.title, a.deadline,
               COUNT(s.id) AS subs,
               SUM(CASE WHEN s.grade IS NOT NULL AND s.grade <> '' THEN 1 ELSE 0 END) AS graded
        FROM activities a
        LEFT JOIN submissions s ON s.activity_id = a.id
        GROUP BY a.id, a.teacher_id, a.title, a.deadline
        ORDER BY (a.deadline IS NULL), a.deadline DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

$pending = $total_subs - $graded;
require_once 'header.php';
?>

<style>
    .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
    .report-stat { padding: 22px !important; }
    .report-stat .label { font-size: 0.82rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .report-stat .value { font-size: 1.9rem; font-weight: 800; color: #581c87; margin-top: 6px; }
    .report-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .report-table th, .report-table td { padding: 14px; text-align: left; font-size: 0.9rem; border-bottom: 1px solid rgba(233, 213, 255, 0.8); }
    .report-table th { background: #faf5ff; color: #6b21a8; font-weight: 600; }
    .bar { background: #f3e8ff; border-radius: 99px; height: 8px; width: 120px; overflow: hidden; display: inline-block; vertical-align: middle; margin-right: 8px; }
    .bar > span { display: block; height: 100%; background: var(--accent-gradient-hover); }
</style>

<div class="welcome-banner">
    <h1>Reports 📈</h1>
    <p>A quick overview of submissions and grading.</p>
</div>

<div class="portal-container">
    <?php if (!empty($flash['msg'])): ?>
        <div style="margin-bottom: 16px; padding: 12px 16px; border-radius: 12px; font-weight: 600; <?= $flash['type'] === 'success' ? 'background: #dcfce7; color: #15803d;' : 'background: #fee2e2; color: #b91c1c;' ?>">
            <?= htmlspecialchars($flash['msg']) ?>
        </div>
    <?php endif; ?>
    <div class="report-grid">
        <div class="card report-stat"><div class="label">Students</div><div class="value"><?= $total_students ?></div></div>
        <div class="card report-stat"><div class="label">Activities</div><div class="value"><?= $total_activities ?></div></div>
        <div class="card report-stat"><div class="label">Submissions</div><div class="value"><?= $total_subs ?></div></div>
        <div class="card report-stat"><div class="label">Graded</div><div class="value"><?= $graded ?></div></div>
        <div class="card report-stat"><div class="label">Needs Grading</div><div class="value"><?= $pending ?></div></div>
        <div class="card report-stat"><div class="label">Average Grade</div><div class="value"><?= $avg_grade !== null ? number_format($avg_grade, 1) : '&ndash;' ?></div></div>
    </div>

    <div class="card">
        <h3 style="font-size: 1.2rem; font-weight: 700; color: #581c87; margin: 0 0 12px 0;">
            <i class="fa-solid fa-chart-line" style="color: #9333ea;"></i> Per-Activity Breakdown
        </h3>
        <?php if (empty($rows)): ?>
            <p style="color: var(--text-muted); margin: 0;">No activities yet. Create an assignment to get started.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="report-table">
                    <thead>
                        <tr><th>Activity</th><th>Deadline</th><th>Submissions</th><th>Graded</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r):
                            $subs = (int)$r['subs'];
                            $g = (int)$r['graded'];
                            $pct = $subs > 0 ? round($g / $subs * 100) : 0;
                        ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($r['title']) ?></td>
                                <td><?= !empty($r['deadline']) ? date('M d, Y h:i A', strtotime($r['deadline'])) : 'No deadline' ?></td>
                                <td><?= $subs ?></td>
                                <td><span class="bar"><span style="width: <?= $pct ?>%;"></span></span><?= $g ?>/<?= $subs ?></td>
                                <td>
                                    <form method="POST" action="teacher_reports.php" style="margin: 0;" onsubmit="<?= asg_confirm_js($subs) ?>">
                                        <?= asg_delete_fields($r['id']) ?>
                                        <button type="submit" name="delete_assignment" style="background: #fee2e2; color: #b91c1c; border: none; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 0.8rem; cursor: pointer; white-space: nowrap;"><i class="fa-solid fa-trash"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>