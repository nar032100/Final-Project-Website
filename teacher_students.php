<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// Check teacher authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

// Siguraduhing umiiral ang column na section sa database table
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS section VARCHAR(100) DEFAULT ''");
} catch (Exception $e) {}

// === HANDLE WITHDRAW STUDENT (permanently removes the student account) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_student'])) {
    $student_id = intval($_POST['student_id'] ?? 0);

    if ($student_id > 0) {
        try {
            // Only ever delete accounts that are students (never a teacher)
            $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND LOWER(role) = 'student'");
            $chk->execute([$student_id]);
            if ($chk->fetch()) {
                // Delete the student's uploaded files from the server
                $files = [];
                try {
                    $q = $pdo->prepare("SELECT file_path FROM submissions WHERE student_id = ?");
                    $q->execute([$student_id]);
                    $files = array_merge($files, $q->fetchAll(PDO::FETCH_COLUMN));
                } catch (Exception $e) {}
                try {
                    $q = $pdo->prepare("SELECT file_path FROM projects WHERE student_id = ?");
                    $q->execute([$student_id]);
                    $files = array_merge($files, $q->fetchAll(PDO::FETCH_COLUMN));
                } catch (Exception $e) {}
                foreach ($files as $fp) {
                    if (!empty($fp) && strpos($fp, 'uploads/') === 0 && strpos($fp, '..') === false) {
                        @unlink(__DIR__ . '/' . $fp);
                    }
                }

                // Remove related records first (in case the tables have no cascade rules), then the account
                foreach ([
                    "DELETE FROM submissions WHERE student_id = ?",
                    "DELETE FROM projects WHERE student_id = ?",
                    "DELETE FROM feedback WHERE student_id = ?",
                    "DELETE FROM notifications WHERE user_id = ?",
                ] as $sql) {
                    try { $pdo->prepare($sql)->execute([$student_id]); } catch (Exception $e) {}
                }
                $pdo->prepare("DELETE FROM users WHERE id = ? AND LOWER(role) = 'student'")->execute([$student_id]);
            }
        } catch (Exception $e) {}
    }

    $selected_filter = $_GET['section'] ?? '';
    $redirect_url = "teacher_students.php";
    if (!empty($selected_filter)) {
        $redirect_url .= "?section=" . urlencode($selected_filter);
    }
    header("Location: " . $redirect_url);
    exit;
}

// === HANDLE SECTION UPDATE (DAPAT BAGO MAG-LOAD ANG HEADER.PHP) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student_section'])) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $new_section = trim($_POST['new_section'] ?? '');
    
    if ($student_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET section = ? WHERE id = ?");
            $stmt->execute([$new_section, $student_id]);
        } catch (Exception $e) {}
    }
    
    // I-redirect sa tamang URL upang ma-refresh ang data
    $selected_filter = $_GET['section'] ?? '';
    $redirect_url = "teacher_students.php";
    if (!empty($selected_filter)) {
        $redirect_url .= "?section=" . urlencode($selected_filter);
    }
    
    header("Location: " . $redirect_url);
    exit;
}

// === NGAYON LAMANG ISASAMA ANG HEADER AT DISPLAY CODE ===
$page_title = "Student Files & Classes";
require_once 'header.php';

$selected_section = $_GET['section'] ?? '';

// Kunin ang lahat ng mayroon nang sections mula sa database
$sections = [];
try {
    $sec_stmt = $pdo->query("SELECT DISTINCT section FROM users WHERE LOWER(role) = 'student' AND section IS NOT NULL AND TRIM(section) != '' ORDER BY section ASC");
    $sections = $sec_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Kunin ang mga estudyante depende sa napiling section filter
$students = [];
try {
    if (!empty($selected_section)) {
        if ($selected_section === 'unassigned') {
            $stmt = $pdo->query("SELECT * FROM users WHERE LOWER(role) = 'student' AND (section IS NULL OR TRIM(section) = '') ORDER BY full_name ASC");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(role) = 'student' AND section = ? ORDER BY full_name ASC");
            $stmt->execute([$selected_section]);
        }
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Kapag All Sections ang napili
        $stmt = $pdo->query("SELECT * FROM users WHERE LOWER(role) = 'student' ORDER BY section ASC, full_name ASC");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}
?>

<style>
    .folders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
        margin-top: 10px;
    }
    .folder-card {
        background: #ffffff;
        border: 1px solid rgba(233, 213, 255, 0.8);
        padding: 18px 20px;
        border-radius: 16px;
        text-decoration: none;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(168, 85, 247, 0.02);
    }
    .folder-card:hover, .folder-card.active {
        background: var(--accent-gradient);
        color: #ffffff;
        border-color: transparent;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(192, 132, 252, 0.3);
    }
    .folder-card:hover i, .folder-card.active i {
        color: #ffffff !important;
    }
    table { width: 100%; border-collapse: separate; border-spacing: 0; }
    th, td { padding: 14px; text-align: left; font-size: 0.9rem; }
    th { background: #faf5ff; color: #6b21a8; font-weight: 600; border-bottom: 1px solid rgba(233, 213, 255, 0.8); }
    td { border-bottom: 1px solid rgba(233, 213, 255, 0.8); color: var(--text-dark); }
    .section-input { padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(233, 213, 255, 0.8); font-size: 0.85rem; width: 140px; }
    .section-input:focus { outline: none; border-color: #9333ea; }
    button.btn-withdraw[type="submit"] { background: linear-gradient(135deg, #f87171 0%, #ef4444 100%); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.35); }
    button.btn-withdraw[type="submit"]:hover { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); box-shadow: 0 6px 20px rgba(239, 68, 68, 0.5); }
</style>

<div class="welcome-banner">
    <h1>Student Files & Classes 📁</h1>
    <p>Manage section folders and view enrolled students in one unified place.</p>
</div>

<div class="portal-container">
    <!-- SECTION FOLDERS DISPLAY -->
    <div class="card">
        <h3 style="font-size: 1.2rem; font-weight: 700; color: #581c87; margin-top: 0; margin-bottom: 16px;">
            <i class="fa-solid fa-folder-tree" style="color: #9333ea;"></i> Class Sections & Courses
        </h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: -10px; margin-bottom: 16px;">
            Click a section folder below to filter and view students enrolled in that specific class.
        </p>

        <div class="folders-grid">
            <!-- All Sections Folder -->
            <a href="teacher_students.php" class="folder-card <?= empty($selected_section) ? 'active' : '' ?>">
                <i class="fa-solid fa-folder-open" style="font-size: 1.8rem; color: #9333ea;"></i>
                <div>
                    <div style="font-weight: 700; font-size: 0.95rem;">All Sections</div>
                    <div style="font-size: 0.75rem; opacity: 0.8;">Show all enrolled</div>
                </div>
            </a>

            <!-- Unassigned Folder -->
            <a href="teacher_students.php?section=unassigned" class="folder-card <?= $selected_section === 'unassigned' ? 'active' : '' ?>">
                <i class="fa-solid fa-folder-minus" style="font-size: 1.8rem; color: #f59e0b;"></i>
                <div>
                    <div style="font-weight: 700; font-size: 0.95rem;">Unassigned</div>
                    <div style="font-size: 0.75rem; opacity: 0.8;">No section set</div>
                </div>
            </a>

            <!-- Dynamically Generated Section Folders -->
            <?php foreach ($sections as $sec): ?>
                <a href="teacher_students.php?section=<?= urlencode($sec) ?>" class="folder-card <?= $selected_section === $sec ? 'active' : '' ?>">
                    <i class="fa-solid fa-folder" style="font-size: 1.8rem; color: #8b5cf6;"></i>
                    <div>
                        <div style="font-weight: 700; font-size: 0.95rem;"><?= htmlspecialchars($sec) ?></div>
                        <div style="font-size: 0.75rem; opacity: 0.8;">Section Folder</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- FILTERED STUDENTS TABLE -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
            <h3 style="font-size: 1.2rem; font-weight: 700; color: #581c87; margin: 0;">
                <i class="fa-solid fa-users-rectangle" style="color: #9333ea;"></i> 
                <?php 
                    if (empty($selected_section)) {
                        echo "All Enrolled Students";
                    } elseif ($selected_section === 'unassigned') {
                        echo "Unassigned Students";
                    } else {
                        echo "Students in Section: " . htmlspecialchars($selected_section);
                    }
                ?>
            </h3>
            <?php if (!empty($selected_section)): ?>
                <a href="teacher_students.php" style="padding: 8px 16px; font-size: 0.85rem; background: #f3e8ff; color: #6b21a8; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    <i class="fa-solid fa-arrow-left"></i> View All Sections
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($students)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 24px 0;">
                No students found <?= !empty($selected_section) ? "in section <strong>" . htmlspecialchars($selected_section) . "</strong>" : "" ?>.
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Current Section</th>
                            <th>Assign / Update Section / Withdraw</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $stu): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($stu['full_name'] ?? 'N/A') ?></strong></td>
                                <td style="color: var(--text-muted);"><?= htmlspecialchars($stu['email'] ?? 'N/A') ?></td>
                                <td>
                                    <span style="background: #f3e8ff; color: #6b21a8; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.8rem;">
                                        <?= !empty($stu['section']) ? htmlspecialchars($stu['section']) : 'Unassigned' ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <form method="POST" action="teacher_students.php<?= !empty($selected_section) ? '?section=' . urlencode($selected_section) : '' ?>" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                                            <input type="hidden" name="student_id" value="<?= (int)$stu['id'] ?>">
                                            <input type="text" name="new_section" class="section-input" value="<?= htmlspecialchars($stu['section'] ?? '') ?>" placeholder="e.g. ACT1L">
                                            <button type="submit" name="update_student_section" class="btn-primary" style="padding: 6px 14px; font-size: 0.8rem;">
                                                <i class="fa-solid fa-floppy-disk"></i> Save
                                            </button>
                                        </form>
                                        <form method="POST" action="teacher_students.php<?= !empty($selected_section) ? '?section=' . urlencode($selected_section) : '' ?>" style="margin: 0;"
                                              onsubmit="return confirm('Withdraw <?= htmlspecialchars(addslashes($stu['full_name'] ?? 'this student'), ENT_QUOTES) ?>?\n\nThis permanently deletes the account and ALL of their submissions, grades, and messages. They will no longer be able to sign in. This cannot be undone.');">
                                            <input type="hidden" name="student_id" value="<?= (int)$stu['id'] ?>">
                                            <button type="submit" name="withdraw_student" class="btn-primary btn-withdraw" style="padding: 6px 14px; font-size: 0.8rem;">
                                                <i class="fa-solid fa-user-minus"></i> Withdraw
                                            </button>
                                        </form>
                                    </div>
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