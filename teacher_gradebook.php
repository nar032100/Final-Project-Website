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

// ---- Save a grade (runs before any HTML so the redirect works) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grade'])) {
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $grade = trim($_POST['grade'] ?? '');
    $result = ['type' => 'error', 'msg' => ''];

    if ($submission_id <= 0) {
        $result['msg'] = 'Submission not found.';
    } elseif ($grade !== '' && (!is_numeric($grade) || $grade < 0 || $grade > 100)) {
        $result['msg'] = 'Grade must be a number from 0 to 100.';
    } else {
        try {
            // Look up the submission first (who it belongs to, which activity, previous grade)
            $info = $pdo->prepare("SELECT s.student_id, s.grade, a.title FROM submissions s JOIN activities a ON a.id = s.activity_id WHERE s.id = ?");
            $info->execute([$submission_id]);
            $row = $info->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $result['msg'] = 'Submission not found.';
            } else {
                $pdo->prepare("UPDATE submissions SET grade = ? WHERE id = ?")
                    ->execute([$grade === '' ? null : $grade, $submission_id]);
                $result = ['type' => 'success', 'msg' => $grade === '' ? 'Grade cleared.' : 'Grade saved.'];

                // Tell the student (only when a grade is set and it actually changed)
                $old = ($row['grade'] === null) ? '' : trim($row['grade']);
                if ($grade !== '' && ($old === '' || (float)$grade !== (float)$old)) {
                    try {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            message TEXT NOT NULL,
                            is_read TINYINT(1) DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )");
                        $act_title = mb_substr($row['title'], 0, 150);
                        $note = 'Graded: "' . $act_title . '" - you received ' . $grade . '/100.';
                        $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")
                            ->execute([$row['student_id'], $note]);
                        $result['msg'] = 'Grade saved and the student was notified.';
                    } catch (Exception $e) {}
                }
            }
        } catch (Exception $e) {
            $result['msg'] = 'Could not save the grade. Please try again.';
        }
    }
    $_SESSION['gb_flash'] = $result;
    header("Location: teacher_gradebook.php");
    exit;
}

// ---- Delete an assignment ----
$del = process_assignment_delete($pdo);
if ($del !== null) {
    $_SESSION['gb_flash'] = $del;
    header("Location: teacher_gradebook.php");
    exit;
}

$flash = ['type' => '', 'msg' => ''];
if (isset($_SESSION['gb_flash'])) { $flash = $_SESSION['gb_flash']; unset($_SESSION['gb_flash']); }

// ---- Load real submissions (students that were withdrawn no longer exist, so they don't appear) ----
$subs = [];
$load_error = '';
try {
    $subs = $pdo->query("
        SELECT s.id, s.submission_url, s.file_path, s.grade, s.submitted_at,
               u.full_name AS student_name, a.title AS act_title
        FROM submissions s
        JOIN users u ON u.id = s.student_id
        JOIN activities a ON a.id = s.activity_id
        ORDER BY s.submitted_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $load_error = 'Unable to load submissions right now.';
}

// ---- Assignments this teacher posted (for the Manage Assignments card) ----
$my_assignments = [];
try {
    $q = $pdo->prepare("
        SELECT a.id, a.title, a.deadline,
               (SELECT COUNT(*) FROM submissions s WHERE s.activity_id = a.id) AS sub_count
        FROM activities a
        ORDER BY a.created_at DESC, a.id DESC
    ");
    $q->execute();
    $my_assignments = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$page_title = "Teacher Gradebook";
require_once 'header.php';
?>

<style>
    table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; }
    th, td { padding: 14px 16px; text-align: left; font-size: 0.9rem; }
    th { background: #faf5ff; color: #6b21a8; font-weight: 600; border-bottom: 1px solid rgba(233, 213, 255, 0.8); }
    td { border-bottom: 1px solid rgba(233, 213, 255, 0.8); color: var(--text-dark); vertical-align: middle; }
    .grade-input { padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(233, 213, 255, 0.8); font-size: 0.85rem; width: 90px; }
    .grade-input:focus { outline: none; border-color: #9333ea; }
    .chip { padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.8rem; text-decoration: none; display: inline-block; margin-right: 6px; }
    .chip-file { background: #dcfce7; color: #166534; }
    .chip-link { background: #f3e8ff; color: #6b21a8; }
</style>

<div class="welcome-banner">
    <h1>Teacher Gradebook 📊</h1>
    <p>Manage and assign grades to student submissions.</p>
</div>

<div class="portal-container">
    <?php if (!empty($flash['msg'])): ?>
        <div style="margin-bottom: 16px; padding: 12px 16px; border-radius: 12px; font-weight: 600; <?= $flash['type'] === 'success' ? 'background: #dcfce7; color: #15803d;' : 'background: #fee2e2; color: #b91c1c;' ?>">
            <?= htmlspecialchars($flash['msg']) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3 style="font-size: 1.2rem; font-weight: 700; color: #581c87; margin-top: 0; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-chart-line" style="color: #9333ea;"></i> Student Submissions
        </h3>

        <?php if ($load_error): ?>
            <p style="color: #ef4444; text-align: center; padding: 20px 0;"><?= htmlspecialchars($load_error) ?></p>
        <?php elseif (empty($subs)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 20px 0;">No submissions yet.</p>
        <?php else: ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Activity</th>
                        <th>Link / File</th>
                        <th>Submitted</th>
                        <th>Action / Assign Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subs as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['student_name']) ?></strong></td>
                            <td><?= htmlspecialchars($s['act_title']) ?></td>
                            <td>
                                <?php if (!empty($s['file_path'])): ?>
                                    <a class="chip chip-file" href="<?= htmlspecialchars($s['file_path']) ?>" download><i class="fa-solid fa-download"></i> Download File</a>
                                <?php endif; ?>
                                <?php if (!empty($s['submission_url']) && preg_match('#^https?://#i', $s['submission_url'])): ?>
                                    <a class="chip chip-link" href="<?= htmlspecialchars($s['submission_url']) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-link"></i> View Link</a>
                                <?php endif; ?>
                                <?php if (empty($s['file_path']) && empty($s['submission_url'])): ?>
                                    <span style="color: var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--text-muted);"><?= date('M d, Y h:i A', strtotime($s['submitted_at'])) ?></td>
                            <td>
                                <form method="POST" action="teacher_gradebook.php" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                                    <input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
                                    <input type="text" name="grade" class="grade-input" placeholder="Grade" value="<?= htmlspecialchars($s['grade'] ?? '') ?>">
                                    <button type="submit" name="save_grade" class="btn-primary" style="padding: 6px 14px; font-size: 0.8rem;">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card" style="margin-top: 24px;">
        <h3 style="font-size: 1.2rem; font-weight: 700; color: #581c87; margin-top: 0; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-list-check" style="color: #9333ea;"></i> Manage Assignments
        </h3>
        <?php if (empty($my_assignments)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 20px 0;">No assignments have been posted yet.</p>
        <?php else: ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr><th>Assignment</th><th>Deadline</th><th>Submissions</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($my_assignments as $a): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($a['title']) ?></strong></td>
                            <td style="color: var(--text-muted);"><?= !empty($a['deadline']) ? date('M d, Y h:i A', strtotime($a['deadline'])) : 'No deadline' ?></td>
                            <td><?= (int)$a['sub_count'] ?></td>
                            <td>
                                <form method="POST" action="teacher_gradebook.php" style="margin: 0;" onsubmit="<?= asg_confirm_js($a['sub_count']) ?>">
                                    <?= asg_delete_fields($a['id']) ?>
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

<?php echo "</div>"; ?>