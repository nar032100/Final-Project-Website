<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
require_once 'ensure_columns.php';
ensure_activity_columns($pdo);

// TEMPORARY: show the real database error when a submission fails, so it can be diagnosed.
// Set to false once submissions work.
$DEBUG_SUBMIT_ERRORS = true;

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// ---- Handle submission (modal form) ----
$flash = ['type' => '', 'msg' => ''];
if (isset($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_output'])) {
    $activity_id = (int)($_POST['activity_id'] ?? 0);
    $url = trim($_POST['submission_url'] ?? '');
    // The browser strips the scheme before sending (some hosts block "http://" in POST data); put it back.
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        $url = (($_POST['url_scheme'] ?? 'https') === 'http' ? 'http://' : 'https://') . $url;
    }
    $result = ['type' => 'error', 'msg' => ''];

    try {
        $chk = $pdo->prepare("SELECT id FROM activities WHERE id = ?");
        $chk->execute([$activity_id]);
        $has_file = isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] !== UPLOAD_ERR_NO_FILE;
        // Fallback: file sent as base64 text in a normal POST field (for hosts that block multipart uploads)
        $b64 = $_POST['file_b64'] ?? '';
        $b64_name = basename((string)($_POST['file_name'] ?? ''));
        $has_b64 = $b64 !== '' && $b64_name !== '';

        if (!$chk->fetch()) {
            $result['msg'] = 'Activity not found.';
        } elseif ($url === '' && !$has_file && !$has_b64) {
            $result['msg'] = 'Please provide a URL or upload a file.';
        } elseif ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $result['msg'] = 'URL must start with http:// or https://';
        } elseif (strlen($url) > 255) {
            $result['msg'] = 'URL is too long.';
        } else {
            $file_path = null;
            $ok = true;
            if ($has_b64) {
                $ext = strtolower(pathinfo($b64_name, PATHINFO_EXTENSION));
                $allowed = ['zip', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
                $bin = base64_decode(strtr($b64, ' ', '+'), true);
                if (!in_array($ext, $allowed, true)) {
                    $result['msg'] = 'Allowed file types: ZIP, PDF, DOC, DOCX, PPT, PPTX, TXT, JPG, PNG, GIF, WEBP.';
                    $ok = false;
                } elseif ($bin === false || $bin === '') {
                    $result['msg'] = 'File upload failed. Please try again.';
                    $ok = false;
                } elseif (strlen($bin) > 5 * 1024 * 1024) {
                    $result['msg'] = 'File is too large (max 5 MB).';
                    $ok = false;
                } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) && @getimagesizefromstring($bin) === false) {
                    $result['msg'] = 'That image file looks invalid or damaged.';
                    $ok = false;
                } else {
                    $dir = __DIR__ . '/uploads/submissions';
                    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
                    if (!file_exists($dir . '/index.html')) { @file_put_contents($dir . '/index.html', ''); }
                    $name = 'sub_' . $student_id . '_' . $activity_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    if (file_put_contents($dir . '/' . $name, $bin) !== false) {
                        $file_path = 'uploads/submissions/' . $name;
                    } else {
                        $result['msg'] = 'Could not save the file on the server.';
                        $ok = false;
                    }
                }
            } elseif ($has_file) {
                $f = $_FILES['submission_file'];
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                $allowed = ['zip', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
                if ($f['error'] !== UPLOAD_ERR_OK) {
                    $result['msg'] = 'File upload failed. Please try again.';
                    $ok = false;
                } elseif (!in_array($ext, $allowed, true)) {
                    $result['msg'] = 'Allowed file types: ZIP, PDF, DOC, DOCX, PPT, PPTX, TXT, JPG, PNG, GIF, WEBP.';
                    $ok = false;
                } elseif ($f['size'] > 5 * 1024 * 1024) {
                    $result['msg'] = 'File is too large (max 5 MB).';
                    $ok = false;
                } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) && @getimagesize($f['tmp_name']) === false) {
                    $result['msg'] = 'That image file looks invalid or damaged.';
                    $ok = false;
                } else {
                    $dir = __DIR__ . '/uploads/submissions';
                    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
                    if (!file_exists($dir . '/index.html')) { @file_put_contents($dir . '/index.html', ''); }
                    $name = 'sub_' . $student_id . '_' . $activity_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    if (move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
                        $file_path = 'uploads/submissions/' . $name;
                    } else {
                        $result['msg'] = 'Could not save the file on the server.';
                        $ok = false;
                    }
                }
            }

            if ($ok) {
                $ex = $pdo->prepare("SELECT id, file_path, grade FROM submissions WHERE activity_id = ? AND student_id = ?");
                $ex->execute([$activity_id, $student_id]);
                $old = $ex->fetch(PDO::FETCH_ASSOC);

                if ($old && $old['grade'] !== null && $old['grade'] !== '') {
                    $result['msg'] = 'This activity was already graded and cannot be resubmitted.';
                } elseif ($old) {
                    $pdo->prepare("UPDATE submissions SET submission_url = ?, file_path = ?, submitted_at = NOW() WHERE id = ?")
                        ->execute([$url, $file_path ?: ($old['file_path'] ?? ''), $old['id']]);
                    $result = ['type' => 'success', 'msg' => 'Submission updated successfully.'];
                } else {
                    $pdo->prepare("INSERT INTO submissions (activity_id, student_id, submission_url, file_path) VALUES (?, ?, ?, ?)")
                        ->execute([$activity_id, $student_id, $url, $file_path ?? '']);
                    $result = ['type' => 'success', 'msg' => 'Output submitted successfully.'];
                }
            }
        }
    } catch (Exception $e) {
        error_log('Submission error: ' . $e->getMessage());
        $result['msg'] = 'Something went wrong while saving your submission.'
            . ($DEBUG_SUBMIT_ERRORS ? ' [' . $e->getMessage() . ']' : '');
    }

    $_SESSION['flash'] = $result;
    header("Location: student_activities.php");
    exit;
}

// Lahat ng activities kasama ang status ng submission ng estudyante
$activities = [];
$load_error = '';
try {
    $stmt = $pdo->prepare("
        SELECT a.id, a.title, a.description, a.deadline, a.created_at, a.attachment_path, a.reference_link,
               u.full_name AS teacher_name,
               s.id AS submission_id, s.submitted_at, s.grade, s.submission_url, s.file_path AS sub_file
        FROM activities a
        LEFT JOIN users u ON u.id = a.teacher_id
        LEFT JOIN submissions s ON s.activity_id = a.id AND s.student_id = ?
        ORDER BY (a.deadline IS NULL), a.deadline ASC, a.created_at DESC
    ");
    $stmt->execute([$student_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $load_error = 'Unable to load activities right now. Please try again later.';
}

$page_title = "Activities";
if (file_exists('student_header.php')) {
    require_once 'student_header.php';
} else {
    require_once 'header.php';
}

$th = 'padding: 12px; text-align: left; background: #faf5ff; color: #6b21a8; font-weight: 600; border-bottom: 1px solid rgba(233, 213, 255, 0.8);';
$td = 'padding: 12px; border-bottom: 1px solid rgba(233, 213, 255, 0.8); color: var(--text-dark); vertical-align: top;';
?>

<div class="welcome-banner">
    <h1>Activities</h1>
    <p>Tasks and deadlines assigned by your instructors.</p>
</div>

<?php if (!empty($flash['msg'])): ?>
    <div style="margin-bottom: 16px; padding: 12px 16px; border-radius: 12px; font-weight: 600; <?= $flash['type'] === 'success' ? 'background: #dcfce7; color: #15803d;' : 'background: #fee2e2; color: #b91c1c;' ?>">
        <?= htmlspecialchars($flash['msg']) ?>
    </div>
<?php endif; ?>

<div class="card">
    <?php if ($load_error): ?>
        <p style="color: #ef4444; text-align: center; padding: 20px 0;"><?= htmlspecialchars($load_error) ?></p>
    <?php elseif (empty($activities)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 20px 0;">No activities assigned yet.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: separate; border-spacing: 0;">
                <thead>
                    <tr>
                        <th style="<?= $th ?>">Activity</th>
                        <th style="<?= $th ?>">Instructor</th>
                        <th style="<?= $th ?>">Deadline</th>
                        <th style="<?= $th ?>">Status</th>
                        <th style="<?= $th ?>">Grade</th>
                        <th style="<?= $th ?>">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $act):
                        $overdue = !empty($act['deadline']) && strtotime($act['deadline']) < time() && empty($act['submission_id']);
                    ?>
                        <tr id="act-<?= (int)$act['id'] ?>" class="<?= (isset($_GET['highlight']) && (int)$_GET['highlight'] === (int)$act['id']) ? 'highlight-row' : '' ?>">
                            <td style="<?= $td ?>">
                                <strong><?= htmlspecialchars($act['title']) ?></strong>
                                <?php if (!empty($act['description'])): ?>
                                    <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;"><?= htmlspecialchars($act['description']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($act['attachment_path'])): ?>
                                    <a href="<?= htmlspecialchars($act['attachment_path']) ?>" target="_blank" style="display:inline-block; margin-top:8px; margin-right:10px; font-size:0.82rem; font-weight:600; color:#7c3aed; text-decoration:none;"><i class="fa-solid fa-paperclip"></i> Attached file</a>
                                <?php endif; ?>
                                <?php if (!empty($act['reference_link'])): ?>
                                    <a href="<?= htmlspecialchars($act['reference_link']) ?>" target="_blank" rel="noopener" style="display:inline-block; margin-top:8px; font-size:0.82rem; font-weight:600; color:#7c3aed; text-decoration:none;"><i class="fa-solid fa-link"></i> Reference link</a>
                                <?php endif; ?>
                            </td>
                            <td style="<?= $td ?>"><?= htmlspecialchars($act['teacher_name'] ?? '—') ?></td>
                            <td style="<?= $td ?>">
                                <?= !empty($act['deadline']) ? date('M d, Y h:i A', strtotime($act['deadline'])) : '<span style="color: var(--text-muted);">No deadline</span>' ?>
                            </td>
                            <td style="<?= $td ?>">
                                <?php if (!empty($act['submission_id'])): ?>
                                    <span style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">Submitted</span>
                                <?php elseif ($overdue): ?>
                                    <span style="background: #fee2e2; color: #b91c1c; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">Overdue</span>
                                <?php else: ?>
                                    <span style="background: #f3e8ff; color: #6b21a8; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="<?= $td ?>"><?= htmlspecialchars($act['grade'] ?? '—') ?></td>
                            <td style="<?= $td ?>">
                                <?php if (!empty($act['grade'])): ?>
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Graded</span>
                                <?php else: ?>
                                    <button type="button" class="btn-primary" style="padding: 8px 16px; font-size: 0.85rem;"
                                        data-id="<?= (int)$act['id'] ?>"
                                        data-title="<?= htmlspecialchars($act['title'], ENT_QUOTES) ?>"
                                        data-url="<?= htmlspecialchars($act['submission_url'] ?? '', ENT_QUOTES) ?>"
                                        onclick="openSubmit(this)">
                                        <i class="fa-solid fa-upload"></i> <?= !empty($act['submission_id']) ? 'Resubmit' : 'Submit' ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>


<!-- Submit Output Modal -->
<div id="submitModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:1000; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; border-radius:20px; width:100%; max-width:480px; padding:28px; box-shadow:0 20px 50px rgba(0,0,0,0.25);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; color:#581c87; font-size:1.1rem;">Submit Output for: <span id="modalTitle"></span></h3>
            <button type="button" onclick="closeSubmit()" style="background:none; border:none; font-size:1.2rem; color:#64748b; cursor:pointer;">&times;</button>
        </div>
        <form method="POST" action="student_activities.php" enctype="application/x-www-form-urlencoded" onsubmit="return prepSubmit(this)">
            <input type="hidden" name="submit_output" value="1">
            <input type="hidden" name="activity_id" id="modalActivityId">
            <input type="hidden" name="url_scheme" id="modalScheme" value="https">
            <input type="hidden" name="file_b64" id="modalB64">
            <input type="hidden" name="file_name" id="modalFileName">
            <label style="display:block; color:#6b21a8; font-size:0.85rem; font-weight:600; margin-bottom:6px;">Repository / Demo URL (Optional)</label>
            <input type="text" name="submission_url" id="modalUrl" placeholder="https://github.com/..." style="width:100%; box-sizing:border-box; padding:12px; border:1px solid #cbd5e1; border-radius:10px; margin-bottom:18px; font-size:0.9rem;">
            <label style="display:block; color:#6b21a8; font-size:0.85rem; font-weight:600; margin-bottom:6px;">Upload File (ZIP, PDF, Docs, Images - max 5 MB)</label>
            <input type="file" id="modalFile" accept=".zip,.pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.webp" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #e9d5ff; background:#faf5ff; border-radius:10px; margin-bottom:22px;">
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeSubmit()" style="background:#f1f5f9; color:#64748b; border:none; padding:12px 20px; border-radius:12px; font-weight:600; cursor:pointer;">Cancel</button>
                <button type="submit">Upload &amp; Submit</button>
            </div>
        </form>
    </div>
</div>
<script>
function openSubmit(btn) {
    document.getElementById('modalActivityId').value = btn.dataset.id;
    document.getElementById('modalTitle').textContent = btn.dataset.title;
    document.getElementById('modalUrl').value = btn.dataset.url || '';
    document.getElementById('submitModal').style.display = 'flex';
}
function prepSubmit(form) {
    var u = document.getElementById('modalUrl');
    var m = u.value.trim().match(/^(https?):\/\//i);
    if (m) { document.getElementById('modalScheme').value = m[1].toLowerCase(); u.value = u.value.trim().replace(/^https?:\/\//i, ''); }

    var fi = document.getElementById('modalFile');
    if (fi.files.length === 0) return true;
    var file = fi.files[0];
    if (file.size > 5 * 1024 * 1024) { alert('File is too large (max 5 MB).'); return false; }
    var btn = form.querySelector('button[type=submit]');
    btn.disabled = true; btn.textContent = 'Uploading...';
    var reader = new FileReader();
    reader.onload = function () {
        document.getElementById('modalB64').value = reader.result.split(',')[1] || '';
        document.getElementById('modalFileName').value = file.name;
        form.submit();
    };
    reader.onerror = function () { btn.disabled = false; btn.textContent = 'Upload & Submit'; alert('Could not read the file.'); };
    reader.readAsDataURL(file);
    return false;
}
function closeSubmit() { document.getElementById('submitModal').style.display = 'none'; }
document.getElementById('submitModal').addEventListener('click', function (e) { if (e.target === this) closeSubmit(); });
</script>

<style>
    .highlight-row td { background: #f3e8ff; animation: rowFlash 2.5s ease-out; }
    @keyframes rowFlash { 0%, 50% { background: #ddd6fe; } 100% { background: #f3e8ff; } }
</style>
<script>
(function () {
    var el = document.querySelector('.highlight-row');
    if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
})();
</script>

<?php echo "</div>"; ?>