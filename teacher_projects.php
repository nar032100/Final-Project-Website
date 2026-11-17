<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

$page_title = "Projects Management";

$projects = [];
try {
    $projects = $pdo->query("
        SELECT p.title, p.project_url, p.file_path, p.created_at, u.full_name AS student_name
        FROM projects p
        JOIN users u ON u.id = p.student_id
        ORDER BY p.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $projects = [];
}

require_once 'header.php';
?>

<style>
    .proj-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .proj-table th, .proj-table td { padding: 14px; text-align: left; font-size: 0.9rem; border-bottom: 1px solid rgba(233, 213, 255, 0.8); }
    .proj-table th { background: #faf5ff; color: #6b21a8; font-weight: 600; }
    .proj-link { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 0.78rem; font-weight: 600; border-radius: 8px; text-decoration: none; background: #f3e8ff; color: #7c3aed; }
</style>

<div class="welcome-banner">
    <h1>Class Projects 📁</h1>
    <p>Projects uploaded by your students.</p>
</div>

<div class="portal-container">
    <div class="card">
        <h3 style="font-size: 1.2rem; font-weight: 700; color: #581c87; margin-top: 0; margin-bottom: 16px;">
            <i class="fa-solid fa-folder-open" style="color: #9333ea;"></i> Student Project Uploads
        </h3>
        <?php if (empty($projects)): ?>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">No projects have been uploaded yet.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="proj-table">
                    <thead>
                        <tr><th>Student</th><th>Project</th><th>Uploaded</th><th>Link / File</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['student_name']) ?></td>
                                <td style="font-weight: 600;"><?= htmlspecialchars($p['title']) ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($p['created_at'])) ?></td>
                                <td>
                                    <?php if (!empty($p['project_url'])): ?>
                                        <a class="proj-link" href="<?= htmlspecialchars($p['project_url']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-link"></i> Open link</a>
                                    <?php elseif (!empty($p['file_path'])): ?>
                                        <a class="proj-link" href="<?= htmlspecialchars($p['file_path']) ?>" target="_blank"><i class="fa-solid fa-download"></i> Download</a>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">&ndash;</span>
                                    <?php endif; ?>
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
