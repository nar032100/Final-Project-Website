<?php
$page_title = "Teacher Submissions";
require_once 'db.php';
require_once 'header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') { header("Location: login.php"); exit; }

$subs = $pdo->query("SELECT s.*, u.full_name as student_name, a.title as act_title FROM submissions s JOIN users u ON s.student_id = u.id JOIN activities a ON s.activity_id = a.id ORDER BY s.submitted_at DESC")->fetchAll();
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-inbox icon-submits"></i> Student Submissions</h3>
    </div>
    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Activity</th>
                <th>Submitted At</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($subs as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['student_name']) ?></td>
                    <td><?= htmlspecialchars($s['act_title']) ?></td>
                    <td><?= date('M d, Y h:i A', strtotime($s['submitted_at'])) ?></td>
                    <td><?= $s['grade'] ?? 'Pending' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>