<?php
$page_title = "Academic Records";
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$id = $_SESSION['user_id'];

// 3. Every activity with its submission/grade status (includes overdue and not-yet-submitted ones)
$my_grades = [];
$graded_count = 0;
$overdue_count = 0;
$grade_nums = [];
$base_grade = 60; // lowest grade counted in the average (base 60)
try {
    $gs = $pdo->prepare("
        SELECT a.id AS activity_id, a.title, a.deadline, a.created_at, u.full_name AS teacher_name,
               s.id AS submission_id, s.grade, s.submitted_at
        FROM activities a
        LEFT JOIN users u ON u.id = a.teacher_id
        LEFT JOIN submissions s ON s.activity_id = a.id AND s.student_id = ?
        ORDER BY a.created_at DESC, a.id DESC
    ");
    $gs->execute([$id]);
    foreach ($gs->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $has_grade = $row['grade'] !== null && $row['grade'] !== '';
        if ($row['submission_id']) {
            $row['status'] = $has_grade ? 'graded' : 'submitted';
        } elseif (!empty($row['deadline']) && strtotime($row['deadline']) < time()) {
            $row['status'] = 'overdue';
        } else {
            $row['status'] = 'pending';
        }
        if ($row['status'] === 'graded') {
            $graded_count++;
            if (is_numeric($row['grade'])) { $grade_nums[] = max($base_grade, (float)$row['grade']); }
        }
        if ($row['status'] === 'overdue') {
            $overdue_count++;
            $grade_nums[] = (float)$base_grade; // missing work counts as the base grade (60)
        }
        $my_grades[] = $row;
    }
} catch (Exception $e) {
    $my_grades = [];
}
$avg_grade = $grade_nums ? array_sum($grade_nums) / count($grade_nums) : null;

// Explicitly set current page so sidebar highlights Academic Records
$current_page = 'academic_records.php';

if (file_exists('student_header.php')) {
    require_once 'student_header.php';
} else {
    require_once 'header.php';
}
?>

<style>
    .records-container { display: flex; flex-direction: column; gap: 28px; }
    .card {
        background: var(--card-bg, rgba(255, 255, 255, 0.95));
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        padding: 32px; border-radius: 20px;
        border: 1px solid rgba(226, 232, 240, 0.9);
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
    }
    .card-header-flex {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
    }
    .card-header-flex h3 {
        font-size: 1.25rem; font-weight: 800; color: #581c87;
        display: flex; align-items: center; gap: 10px; margin: 0;
    }
    .btn-action-link {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 14px; font-size: 0.8rem; font-weight: 600;
        border-radius: 8px; text-decoration: none; transition: all 0.3s ease;
    }
    .btn-demo { background: #f3e8ff; color: #6b21a8; }
    .btn-demo:hover { background: #e9d5ff; }
    .btn-file { background: #dcfce7; color: #15803d; }
    .btn-file:hover { background: #bbf7d0; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; }
    th, td { padding: 14px; text-align: left; font-size: 0.9rem; }
    th { background: #faf5ff; color: #6b21a8; font-weight: 600; border-bottom: 1px solid rgba(233, 213, 255, 0.8); }
    td { border-bottom: 1px solid rgba(233, 213, 255, 0.8); color: var(--text-dark); }
</style>

<div class="welcome-banner" style="margin-bottom: 24px;">
    <h1>My Academic Records 📚</h1>
    <p>View all your grades and activity status in one place.</p>
</div>

<div class="records-container">
    <!-- Grades and status for every activity -->
    <div class="card">
        <div class="card-header-flex">
            <h3><i class="fa-solid fa-star" style="color: #9333ea;"></i> My Grades</h3>
            <?php if (!empty($my_grades)): ?>
                <div style="display: flex; gap: 10px; flex-wrap: wrap; font-size: 0.85rem;">
                    <span title="Base 60: the lowest grade counted is 60" style="background: #f3e8ff; color: #6b21a8; font-weight: 600; padding: 6px 12px; border-radius: 8px;">Average: <?= $avg_grade !== null ? number_format($avg_grade, 1) : '&ndash;' ?></span>
                    <span style="background: #dcfce7; color: #15803d; font-weight: 600; padding: 6px 12px; border-radius: 8px;">Graded: <?= $graded_count ?> of <?= count($my_grades) ?></span>
                    <?php if ($overdue_count > 0): ?>
                        <span style="background: #fee2e2; color: #b91c1c; font-weight: 600; padding: 6px 12px; border-radius: 8px;">Overdue: <?= $overdue_count ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if (empty($my_grades)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 20px 0;">No activities yet. Your grades will show up here.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Activity</th>
                            <th>Instructor</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th style="text-align: right;">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_grades as $row):
                            $badge = [
                                'graded'    => ['Graded',    '#dcfce7', '#15803d'],
                                'submitted' => ['Submitted', '#dbeafe', '#1d4ed8'],
                                'overdue'   => ['Overdue',   '#fee2e2', '#b91c1c'],
                                'pending'   => ['Pending',   '#f3e8ff', '#6b21a8'],
                            ][$row['status']];
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                                <td><?= htmlspecialchars($row['teacher_name'] ?? '—') ?></td>
                                <td style="color: var(--text-muted);"><?= !empty($row['deadline']) ? date('M d, Y h:i A', strtotime($row['deadline'])) : 'No deadline' ?></td>
                                <td><span style="background: <?= $badge[1] ?>; color: <?= $badge[2] ?>; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;"><?= $badge[0] ?></span></td>
                                <td style="text-align: right;">
                                    <?php if ($row['status'] === 'graded'): ?>
                                        <span style="font-weight: 700; color: #6b21a8; background: #f3e8ff; padding: 4px 12px; border-radius: 8px;"><?= htmlspecialchars($row['grade']) ?></span>
                                    <?php elseif ($row['status'] === 'overdue'): ?>
                                        <span style="font-weight: 600; color: #b91c1c;">Missing</span>
                                    <?php elseif ($row['status'] === 'submitted'): ?>
                                        <span style="color: var(--text-muted); font-style: italic;">Awaiting grade</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">&ndash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p style="color: var(--text-muted); font-size: 0.82rem; margin: 14px 0 0 0;">
                <i class="fa-solid fa-circle-info"></i> Base 60: the lowest grade counted in your average is 60.<?= $overdue_count > 0 ? ' Overdue activities count as 60 until they are submitted and graded.' : '' ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php 
if (file_exists('footer.php')) {
    require_once 'footer.php';
}
?>