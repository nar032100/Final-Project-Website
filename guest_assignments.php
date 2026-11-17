<?php
// Public, read-only list of all assignments. No login needed and nothing here can be changed.
require_once 'db.php';
require_once 'ensure_columns.php';
ensure_activity_columns($pdo);

$assignments = [];
$load_error = '';
try {
    $assignments = $pdo->query("
        SELECT a.*, u.full_name AS teacher_name
        FROM activities a
        LEFT JOIN users u ON u.id = a.teacher_id
        ORDER BY (a.deadline IS NULL), a.deadline ASC, a.id DESC
    ")->fetchAll();
} catch (Exception $e) {
    $load_error = 'Unable to load assignments right now. Please try again later.';
}

// Student submissions per assignment (names, links and files only - grades stay private)
$subs_by_act = [];
try {
    $rows = $pdo->query("
        SELECT s.activity_id, s.submission_url, s.file_path, s.submitted_at, u.full_name AS student_name
        FROM submissions s
        JOIN users u ON u.id = s.student_id
        ORDER BY s.submitted_at DESC
    ")->fetchAll();
    foreach ($rows as $r) { $subs_by_act[$r['activity_id']][] = $r; }
} catch (Exception $e) {}

// Upcoming / no-deadline first, overdue ones at the bottom
$now = time();
$open = $closed = [];
foreach ($assignments as $a) {
    if (!empty($a['deadline']) && strtotime($a['deadline']) < $now) { $closed[] = $a; }
    else { $open[] = $a; }
}
$closed = array_reverse($closed); // most recently closed first
$assignments = array_merge($open, $closed);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assignments (Guest) - StudentWorks</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { min-height: 100vh; background: linear-gradient(-45deg, #e0e7ff, #f3e8ff, #f0f9ff, #f1f5f9); color: #1e293b; padding: 24px 16px 48px; }
        .wrap { max-width: 860px; margin: 0 auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
        .brand { font-size: 1.3rem; font-weight: 800; color: #6b21a8; }
        .brand i { color: #9333ea; }
        .back { background: #fff; color: #6b21a8; border: 1px solid #e9d5ff; padding: 9px 16px; border-radius: 10px; font-weight: 700; font-size: 0.88rem; text-decoration: none; }
        h1 { font-size: 1.8rem; font-weight: 800; color: #581c87; }
        .sub { color: #9333ea; margin: 4px 0 8px; }
        .note { display: inline-flex; align-items: center; gap: 8px; background: #f3e8ff; color: #6b21a8; padding: 8px 14px; border-radius: 10px; font-size: 0.85rem; font-weight: 600; margin-bottom: 20px; }
        .item { background: rgba(255,255,255,0.95); border: 1px solid #e9d5ff; border-radius: 16px; padding: 20px 22px; margin-bottom: 14px; box-shadow: 0 6px 18px rgba(88,28,135,0.06); }
        .item.closed { opacity: 0.75; }
        .row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
        .title { font-size: 1.1rem; font-weight: 800; color: #581c87; overflow-wrap: anywhere; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; white-space: nowrap; }
        .badge.open { background: #f3e8ff; color: #6b21a8; }
        .badge.closed { background: #fee2e2; color: #b91c1c; }
        .desc { color: #475569; font-size: 0.92rem; margin-top: 10px; white-space: pre-line; overflow-wrap: anywhere; }
        .meta { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 12px; font-size: 0.84rem; color: #64748b; }
        .meta i { color: #9333ea; margin-right: 5px; }
        .links { margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap; }
        .links a { background: #faf5ff; color: #7c3aed; border: 1px solid #e9d5ff; padding: 6px 12px; border-radius: 8px; font-size: 0.82rem; font-weight: 700; text-decoration: none; }
        details.subs { margin-top: 14px; border-top: 1px solid #f3e8ff; padding-top: 12px; }
        details.subs summary { cursor: pointer; font-weight: 700; font-size: 0.88rem; color: #6b21a8; }
        .sub-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 12px; margin-top: 8px; background: #faf5ff; border-radius: 10px; font-size: 0.88rem; }
        .sub-row .who { font-weight: 700; color: #581c87; }
        .sub-row .when { color: #64748b; font-size: 0.8rem; }
        .sub-row .links a { padding: 4px 10px; font-size: 0.78rem; }
        .empty { text-align: center; color: #64748b; padding: 40px 0; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div class="brand"><i class="fa-solid fa-graduation-cap"></i> STUDENTWORKS</div>
        <a class="back" href="login.php"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
    </div>

    <h1>Assignments</h1>
    <p class="sub">All tasks and deadlines posted by instructors.</p>
    <div class="note"><i class="fa-solid fa-eye"></i> Guest view &mdash; view only. Log in as a student to submit work.</div>

    <?php if ($load_error): ?>
        <p class="empty" style="color:#ef4444;"><?= htmlspecialchars($load_error) ?></p>
    <?php elseif (empty($assignments)): ?>
        <p class="empty">No assignments have been posted yet.</p>
    <?php else: foreach ($assignments as $a):
        $is_closed = !empty($a['deadline']) && strtotime($a['deadline']) < $now;
        $link = $a['reference_link'] ?? '';
        $file = $a['attachment_path'] ?? '';
    ?>
        <div class="item <?= $is_closed ? 'closed' : '' ?>">
            <div class="row">
                <div class="title"><?= htmlspecialchars($a['title']) ?></div>
                <span class="badge <?= $is_closed ? 'closed' : 'open' ?>"><?= $is_closed ? 'Deadline passed' : 'Open' ?></span>
            </div>
            <?php if (!empty($a['description'])): ?>
                <div class="desc"><?= htmlspecialchars($a['description']) ?></div>
            <?php endif; ?>
            <div class="meta">
                <span><i class="fa-solid fa-user"></i><?= htmlspecialchars($a['teacher_name'] ?? 'Instructor') ?></span>
                <span><i class="fa-regular fa-clock"></i><?= !empty($a['deadline']) ? 'Due ' . date('M d, Y h:i A', strtotime($a['deadline'])) : 'No deadline' ?></span>
            </div>
            <?php if (strpos($file, 'uploads/') === 0 || preg_match('#^https?://#i', $link)): ?>
                <div class="links">
                    <?php if (strpos($file, 'uploads/') === 0): ?>
                        <a href="<?= htmlspecialchars($file) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-paperclip"></i> Attached file</a>
                    <?php endif; ?>
                    <?php if (preg_match('#^https?://#i', $link)): ?>
                        <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-link"></i> Reference link</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php $subs = $subs_by_act[$a['id']] ?? []; ?>
            <details class="subs">
                <summary><i class="fa-solid fa-inbox"></i> Student submissions (<?= count($subs) ?>)</summary>
                <?php if (empty($subs)): ?>
                    <div class="sub-row" style="color:#64748b;">No submissions yet.</div>
                <?php else: foreach ($subs as $sub):
                    $sf = $sub['file_path'] ?? '';
                    $su = $sub['submission_url'] ?? '';
                ?>
                    <div class="sub-row">
                        <div>
                            <div class="who"><?= htmlspecialchars($sub['student_name']) ?></div>
                            <div class="when"><?= date('M d, Y h:i A', strtotime($sub['submitted_at'])) ?></div>
                        </div>
                        <div class="links" style="margin-top:0;">
                            <?php if (strpos($sf, 'uploads/') === 0): ?>
                                <a href="<?= htmlspecialchars($sf) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file"></i> View file</a>
                            <?php endif; ?>
                            <?php if (preg_match('#^https?://#i', $su)): ?>
                                <a href="<?= htmlspecialchars($su) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-link"></i> View link</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </details>
        </div>
    <?php endforeach; endif; ?>
</div>
</body>
</html>