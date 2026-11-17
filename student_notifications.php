<?php
require_once 'db.php';
require_once 'ensure_columns.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$message = "";
$error = "";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    try { $pdo->exec("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS announcement_id INT DEFAULT NULL"); } catch (Exception $e) {}
    ensure_notification_columns($pdo);
    sync_assignment_notifications($pdo, $student_id);
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    if (isset($_GET['click'])) {
        $notif_id = intval($_GET['click']);
        
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $student_id]);
        $notif = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($notif) {
            $upd = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
            $upd->execute([$notif_id]);

            $msg_text = strtolower($notif['message']);

            if (strpos($msg_text, 'new assignment:') === 0) {
                $aid = (int)($notif['activity_id'] ?? 0);
                if (!$aid) {
                    // Older notifications: find the activity by its title
                    $title = trim(substr($notif['message'], strlen('New assignment:')));
                    $f = $pdo->prepare("SELECT id FROM activities WHERE title = ? ORDER BY id DESC LIMIT 1");
                    $f->execute([$title]);
                    $aid = (int)$f->fetchColumn();
                }
                header("Location: student_activities.php" . ($aid ? "?highlight=" . $aid . "#act-" . $aid : ""));
                exit;
            } elseif (strpos($msg_text, 'graded:') === 0) {
                header("Location: student_activities.php");
                exit;
            } elseif (strpos($msg_text, 'announcement:') === 0) {
                header("Location: student_notifications.php?view=" . $notif_id);
                exit;
            } elseif (strpos($msg_text, 'grade') !== false || strpos($msg_text, 'graded') !== false || strpos($msg_text, 'submitted') !== false) {
                header("Location: student_activities.php");
                exit;
            } elseif (strpos($msg_text, 'activity') !== false || strpos($msg_text, 'deadline') !== false || strpos($msg_text, 'reminder') !== false) {
                header("Location: student_activities.php");
                exit;
            } else {
                header("Location: student_notifications.php");
                exit;
            }
        }
    }

    if (isset($_GET['mark_read'])) {
        $notif_id = intval($_GET['mark_read']);
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $student_id]);
        header("Location: student_notifications.php");
        exit;
    }

    if (isset($_GET['mark_all'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$student_id]);
        header("Location: student_notifications.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT n.*, an.title AS ann_title, an.message AS ann_message, an.created_at AS ann_created
                           FROM notifications n
                           LEFT JOIN announcements an ON an.id = n.announcement_id
                           WHERE n.user_id = ? ORDER BY n.created_at DESC, n.id DESC");
    $stmt->execute([$student_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $notifications = [];
    $error = "Database Error: " . $e->getMessage();
}

// Notification to open in the "read full text" popup
$view = null;
if (isset($_GET['view'])) {
    foreach ($notifications as $n) {
        if ((int)$n['id'] === (int)$_GET['view']) { $view = $n; break; }
    }
}

$page_title = "Student Notifications";
$current_page = 'student_notifications.php'; // Ensures active sidebar highlight
require_once 'student_header.php'; // Fixed: using student sidebar header
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3><i class="fa-solid fa-bell" style="color: var(--accent-primary);"></i> Your Notifications</h3>
        <?php if (!empty($notifications)): ?>
            <a href="student_notifications.php?mark_all=1" class="btn btn-secondary" style="font-size: 0.82rem; padding: 6px 12px;">
                <i class="fa-solid fa-check-double"></i> Mark all as read
            </a>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div style="background: #fee2e2; color: #b91c1c; padding: 12px 16px; border-radius: var(--radius-md); font-weight: 500; margin-bottom: 20px; border: 1px solid #fca5a5;">
            <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>
        <div style="text-align: center; padding: 40px 0; color: var(--text-muted);">
            <i class="fa-regular fa-bell-slash" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
            No notifications yet.
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 10px;">
            <?php foreach ($notifications as $n): ?>
                <div onclick="window.location.href='student_notifications.php?click=<?= $n['id'] ?>'" style="cursor: pointer; background: <?= $n['is_read'] ? 'rgba(248, 250, 252, 0.8)' : '#ffffff' ?>; padding: 16px 20px; border-radius: var(--radius-md); border: 1px solid <?= $n['is_read'] ? '#e2e8f0' : 'var(--accent-primary)' ?>; display: flex; justify-content: space-between; align-items: center; gap: 12px; box-shadow: <?= $n['is_read'] ? 'none' : '0 4px 12px rgba(99, 102, 241, 0.08)' ?>; transition: var(--transition); min-width: 0;">
                    <div style="display: flex; align-items: flex-start; gap: 14px; min-width: 0; flex: 1;">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: <?= $n['is_read'] ? '#f1f5f9' : '#e0e7ff' ?>; color: <?= $n['is_read'] ? '#64748b' : '#4338ca' ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <div style="min-width: 0; flex: 1;">
                            <?php $is_ann = stripos($n['message'], 'announcement:') === 0; ?>
                            <p style="font-size: 0.95rem; color: var(--text-dark); margin-bottom: 4px; font-weight: <?= $n['is_read'] ? '400' : '600' ?>; overflow-wrap: anywhere; word-break: break-word;<?= $is_ann ? ' display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;' : '' ?>">
                                <?= htmlspecialchars($n['message']) ?>
                            </p>
                            <span style="font-size: 0.78rem; color: var(--text-muted);">
                                <i class="fa-regular fa-clock"></i> <?= date('M d, Y h:i A', strtotime($n['created_at'])) ?>
                            </span>
                            <?php if ($is_ann): ?>
                                <span style="font-size: 0.78rem; color: var(--accent-primary); margin-left: 8px;">Click to read the full announcement</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div onclick="event.stopPropagation();" style="flex-shrink: 0;">
                        <?php if (!$n['is_read']): ?>
                            <a href="student_notifications.php?mark_read=<?= $n['id'] ?>" class="btn" style="padding: 6px 12px; font-size: 0.78rem; white-space: nowrap;">
                                <i class="fa-solid fa-check"></i> Mark Read
                            </a>
                        <?php else: ?>
                            <span style="font-size: 0.78rem; color: var(--text-muted); font-style: italic;">Read</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($view): ?>
<div id="notifModal" style="position: fixed; inset: 0; background: rgba(15,23,42,0.55); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 16px;">
    <div style="background: #fff; border-radius: 20px; width: 100%; max-width: 560px; max-height: 85vh; display: flex; flex-direction: column; padding: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.25); box-sizing: border-box;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 14px;">
            <h3 style="margin: 0; color: #581c87; font-size: 1.15rem; overflow-wrap: anywhere; word-break: break-word; min-width: 0;">
                <i class="fa-solid fa-bullhorn" style="color: #9333ea;"></i>
                <?= htmlspecialchars(!empty($view['ann_title']) ? $view['ann_title'] : 'Announcement') ?>
            </h3>
            <a href="student_notifications.php" style="background: none; border: none; font-size: 1.4rem; line-height: 1; color: #64748b; text-decoration: none;">&times;</a>
        </div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 14px;">
            <i class="fa-regular fa-clock"></i>
            <?= date('M d, Y h:i A', strtotime(!empty($view['ann_created']) ? $view['ann_created'] : $view['created_at'])) ?>
        </div>
        <div style="overflow-y: auto; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; line-height: 1.6; color: var(--text-dark); font-size: 0.95rem;"><?= htmlspecialchars(!empty($view['ann_message']) ? $view['ann_message'] : $view['message']) ?></div>
        <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
            <a href="student_notifications.php" class="btn-primary" style="text-decoration: none; padding: 10px 22px; border-radius: 12px; display: inline-block;">Close</a>
        </div>
    </div>
</div>
<script>
document.getElementById('notifModal').addEventListener('click', function (e) { if (e.target === this) window.location.href = 'student_notifications.php'; });
</script>
<?php endif; ?>

<?php require_once 'footer.php'; ?>