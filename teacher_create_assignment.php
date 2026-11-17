<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
require_once 'ensure_columns.php';
require_once 'assignment_delete.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

ensure_activity_columns($pdo);
ensure_notification_columns($pdo);

$error = '';
$success = isset($_GET['posted']);
$deleted = isset($_GET['deleted']);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_assignment'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $link = trim($_POST['reference_link'] ?? '');
    $deadline_raw = trim($_POST['deadline'] ?? '');
    $deadline = null;

    if ($title === '') {
        $error = 'Please enter an assignment title.';
    } elseif ($link !== '' && !preg_match('#^https?://#i', $link)) {
        $error = 'Reference link must start with http:// or https://';
    } elseif ($deadline_raw !== '' && !($ts = strtotime($deadline_raw))) {
        $error = 'The deadline is not a valid date.';
    } else {
        if ($deadline_raw !== '') { $deadline = date('Y-m-d H:i:s', $ts); }

        // Optional attachment
        $file_path = null;
        if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['assignment_file'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'txt'];
            if ($f['error'] !== UPLOAD_ERR_OK) {
                $error = 'File upload failed. Please try again.';
            } elseif (!in_array($ext, $allowed, true)) {
                $error = 'Allowed file types: PDF, DOC, DOCX, PPT, PPTX, ZIP, TXT.';
            } elseif ($f['size'] > 5 * 1024 * 1024) {
                $error = 'File is too large (max 5 MB).';
            } else {
                $dir = __DIR__ . '/uploads/assignments';
                if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
                if (!file_exists($dir . '/index.html')) { @file_put_contents($dir . '/index.html', ''); }
                $name = 'asg_' . $_SESSION['user_id'] . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
                    $file_path = 'uploads/assignments/' . $name;
                } else {
                    $error = 'Could not save the attached file on the server.';
                }
            }
        }

        $new_id = 0;
        if ($error === '') {
            try {
                $pdo->prepare("INSERT INTO activities (teacher_id, title, description, deadline, attachment_path, reference_link) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$_SESSION['user_id'], $title, $description !== '' ? $description : null, $deadline, $file_path, $link !== '' ? $link : null]);
                $new_id = (int)$pdo->lastInsertId();
            } catch (Exception $e) {
                // Fallback if the optional columns could not be added
                try {
                    $pdo->prepare("INSERT INTO activities (teacher_id, title, description, deadline) VALUES (?, ?, ?, ?)")
                        ->execute([$_SESSION['user_id'], $title, $description !== '' ? $description : null, $deadline]);
                } catch (Exception $e2) {
                    $error = 'Could not save the assignment. Please try again.';
                }
            }

            if ($error === '') {
                // Let students know (shows up in their Notifications badge)
                try {
                    $msg = 'New assignment: ' . mb_substr($title, 0, 200);
                    $ids = $pdo->query("SELECT id FROM users WHERE LOWER(role) = 'student'")->fetchAll(PDO::FETCH_COLUMN);
                    try {
                        $ins = $pdo->prepare("INSERT INTO notifications (user_id, message, activity_id) VALUES (?, ?, ?)");
                        foreach ($ids as $sid) { $ins->execute([$sid, $msg, $new_id ?: null]); }
                    } catch (Exception $e) {
                        $ins = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                        foreach ($ids as $sid) { $ins->execute([$sid, $msg]); }
                    }
                } catch (Exception $e) {}

                header("Location: teacher_create_assignment.php?posted=1");
                exit;
            }
        }
    }
}

// ---- Delete an assignment the teacher posted ----
$del = process_assignment_delete($pdo);
if ($del !== null) {
    if ($del['type'] === 'success') {
        header("Location: teacher_create_assignment.php?deleted=1");
        exit;
    }
    $error = $del['msg'];
}

// ---- Assignments this teacher has posted ----
$my_assignments = [];
try {
    $q = $pdo->prepare("
        SELECT a.id, a.title, a.deadline, a.created_at,
               (SELECT COUNT(*) FROM submissions s WHERE s.activity_id = a.id) AS sub_count
        FROM activities a
        ORDER BY a.created_at DESC, a.id DESC
    ");
    $q->execute();
    $my_assignments = $q->fetchAll();
} catch (Exception $e) {}

$page_title = "Create Assignment";
require_once 'header.php';
?>

<style>
    .portal-container {
        display: flex;
        flex-direction: column;
        align-items: center; /* Gitna horizontally */
        width: 100%;
    }
    .form-container {
        max-width: 720px;
        width: 100%;
        margin: 0 auto; /* Naka-center ang card */
    }
    .form-group {
        margin-bottom: 24px;
    }
    .form-group label {
        display: block;
        font-weight: 700;
        color: #581c87;
        margin-bottom: 8px;
        font-size: 0.95rem;
    }
    .form-control {
        width: 100%;
        padding: 12px 16px;
        border-radius: 12px;
        border: 1px solid rgba(233, 213, 255, 0.9);
        background: #ffffff;
        font-size: 0.95rem;
        color: var(--text-dark);
        box-sizing: border-box;
        font-family: inherit;
        transition: all 0.3s ease;
    }
    .form-control:focus {
        outline: none;
        border-color: #9333ea;
        box-shadow: 0 0 0 4px rgba(147, 51, 234, 0.12);
    }
    textarea.form-control {
        resize: vertical;
        min-height: 120px;
    }
    input[type="file"].form-control {
        padding: 10px;
        background: #faf5ff;
        cursor: pointer;
    }
</style>

<div class="welcome-banner" style="text-align: center;">
    <h1>Create Assignment 📝</h1>
    <p>Set up new coursework or activities for your students.</p>
</div>

<div class="portal-container">
    <div class="card form-container">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: #581c87; margin-top: 0; margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-pen-to-square" style="color: #9333ea;"></i> Create New Assignment
        </h3>

        <?php if ($success): ?>
            <div style="background:#dcfce7; color:#15803d; padding:12px 16px; border-radius:12px; margin-bottom:20px; font-weight:600;">
                <i class="fa-solid fa-circle-check"></i> Assignment posted! Students can now see it in their Activities tab.
            </div>
        <?php endif; ?>
        <?php if ($deleted): ?>
            <div style="background:#dcfce7; color:#15803d; padding:12px 16px; border-radius:12px; margin-bottom:20px; font-weight:600;">
                <i class="fa-solid fa-circle-check"></i> Assignment deleted.
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background:#fee2e2; color:#b91c1c; padding:12px 16px; border-radius:12px; margin-bottom:20px; font-weight:600;">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="teacher_create_assignment.php" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Assignment Title *</label>
                <input type="text" id="title" name="title" class="form-control" placeholder="e.g., Module 1 Activity" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description / Instructions</label>
                <textarea id="description" name="description" class="form-control" placeholder="Provide instructions for the students..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="assignment_file">Attach File (Optional)</label>
                <input type="file" id="assignment_file" name="assignment_file" class="form-control">
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 6px;">Maaaring mag-upload ng PDF, Word document, o ZIP file para sa mga estudyante.</div>
            </div>

            <div class="form-group">
                <label for="reference_link">Reference Link / Demo URL (Optional)</label>
                <input type="url" id="reference_link" name="reference_link" class="form-control" placeholder="https://example.com">
            </div>

            <div class="form-group">
                <label for="deadline">Deadline</label>
                <input type="datetime-local" id="deadline" name="deadline" class="form-control">
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 32px;">
                <button type="submit" name="post_assignment" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-paper-plane"></i> Post Assignment
                </button>
            </div>
        </form>
    </div>

    <div class="card form-container" style="margin-top: 24px;">
        <h3 style="font-size: 1.3rem; font-weight: 800; color: #581c87; margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-list-check" style="color: #9333ea;"></i> Posted Assignments
        </h3>

        <?php if (empty($my_assignments)): ?>
            <p style="color: var(--text-muted); margin: 0;">No assignments have been posted yet.</p>
        <?php else: foreach ($my_assignments as $a): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 0; border-bottom: 1px solid rgba(233, 213, 255, 0.8);">
                <div style="min-width: 0;">
                    <div style="font-weight: 700; color: #581c87; overflow-wrap: anywhere;"><?= htmlspecialchars($a['title']) ?></div>
                    <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 4px;">
                        <?= $a['deadline'] ? 'Due ' . date('M d, Y h:i A', strtotime($a['deadline'])) : 'No deadline' ?>
                        &middot; <?= (int)$a['sub_count'] ?> submission<?= (int)$a['sub_count'] === 1 ? '' : 's' ?>
                    </div>
                </div>
                <form method="POST" action="teacher_create_assignment.php" style="margin: 0;"
                      onsubmit="<?= asg_confirm_js($a['sub_count']) ?>">
                    <?= asg_delete_fields($a['id']) ?>
                    <button type="submit" name="delete_assignment" style="background: #fee2e2; color: #b91c1c; border: none; padding: 8px 14px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; white-space: nowrap;">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </form>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>