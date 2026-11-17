<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

// Make sure the tables exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS announcement_id INT DEFAULT NULL"); } catch (Exception $e) {}

// ---- Broadcast: save the announcement and notify every student ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['message'] ?? '');
    $result = ['type' => 'error', 'msg' => ''];

    if ($title === '' || $body === '') {
        $result['msg'] = 'Please fill in both the title and the message.';
    } elseif (mb_strlen($title) > 150) {
        $result['msg'] = 'Title is too long (max 150 characters).';
    } else {
        try {
            $pdo->prepare("INSERT INTO announcements (teacher_id, title, message) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], $title, $body]);
            $ann_id = (int)$pdo->lastInsertId();

            // Notification text (kept under 255 characters in case the column is VARCHAR(255))
            $note = 'Announcement: ' . $title . ' - ' . preg_replace('/\s+/', ' ', $body);
            if (mb_strlen($note) > 250) { $note = mb_substr($note, 0, 247) . '...'; }

            try {
                $n = $pdo->prepare("INSERT INTO notifications (user_id, message, announcement_id) SELECT id, ?, ? FROM users WHERE LOWER(role) = 'student'");
                $n->execute([$note, $ann_id]);
            } catch (Exception $e) {
                // announcement_id column missing: fall back to a plain notification
                $n = $pdo->prepare("INSERT INTO notifications (user_id, message) SELECT id, ? FROM users WHERE LOWER(role) = 'student'");
                $n->execute([$note]);
            }
            $count = $n->rowCount();

            $result = ['type' => 'success', 'msg' => 'Announcement sent to ' . $count . ' student' . ($count === 1 ? '' : 's') . '.'];
        } catch (Exception $e) {
            $result['msg'] = 'Could not post the announcement. Please try again.';
        }
    }
    $_SESSION['ann_flash'] = $result;
    header("Location: teacher_announcements.php");
    exit;
}

$flash = ['type' => '', 'msg' => ''];
if (isset($_SESSION['ann_flash'])) { $flash = $_SESSION['ann_flash']; unset($_SESSION['ann_flash']); }

$announcements = [];
try {
    $q = $pdo->prepare("SELECT id, title, message, created_at FROM announcements WHERE teacher_id = ? ORDER BY created_at DESC, id DESC");
    $q->execute([$_SESSION['user_id']]);
    $announcements = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$page_title = "Announcements";
require_once 'header.php';
?>

<style>
    .announcement-container {
        max-width: 800px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    .form-group {
        margin-bottom: 20px;
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
    
    /* Previous Announcements Styling */
    .announcement-item {
        background: #faf5ff;
        border: 1px solid rgba(233, 213, 255, 0.8);
        border-radius: 16px;
        padding: 20px;
        margin-top: 16px;
        transition: all 0.3s ease;
    }
    .announcement-item:hover {
        box-shadow: 0 4px 15px rgba(147, 51, 234, 0.08);
        border-color: #c084fc;
    }
    .announcement-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }
    .announcement-title {
        font-size: 1.1rem;
        font-weight: 800;
        color: #581c87;
        margin: 0;
    }
    .announcement-date {
        font-size: 0.82rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .announcement-container > * { min-width: 0; max-width: 100%; }
    .announcement-item { min-width: 0; overflow-wrap: anywhere; word-break: break-word; }
    .announcement-title { overflow-wrap: anywhere; word-break: break-word; min-width: 0; }
    .announcement-body {
        overflow-wrap: anywhere;
        word-break: break-word;
        font-size: 0.95rem;
        color: var(--text-dark);
        line-height: 1.6;
        margin: 0;
        white-space: pre-line;
    }
</style>

<div class="welcome-banner" style="text-align: center;">
    <h1>Class Announcements 📢</h1>
    <p>Broadcast updates, reminders, and class notices to all your students.</p>
</div>

<div class="portal-container">
    <div class="announcement-container">
        
        <?php if (!empty($flash['msg'])): ?>
            <div style="padding: 12px 16px; border-radius: 12px; font-weight: 600; <?= $flash['type'] === 'success' ? 'background: #dcfce7; color: #15803d;' : 'background: #fee2e2; color: #b91c1c;' ?>">
                <?= htmlspecialchars($flash['msg']) ?>
            </div>
        <?php endif; ?>

        <!-- POST NEW ANNOUNCEMENT CARD -->
        <div class="card">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #581c87; margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-bullhorn" style="color: #9333ea;"></i> Post Class Announcement
            </h3>

            <form method="POST" action="teacher_announcements.php">
                <div class="form-group">
                    <label for="title">Announcement Title *</label>
                    <input type="text" id="title" name="title" class="form-control" placeholder="e.g., Suspension of Classes / Deadline Extension" required>
                </div>

                <div class="form-group">
                    <label for="message">Message Details *</label>
                    <textarea id="message" name="message" class="form-control" placeholder="Write your notice or announcement here..." required></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                    <button type="submit" name="post_announcement" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-paper-plane"></i> Broadcast Announcement
                    </button>
                </div>
            </form>
        </div>

        <!-- PREVIOUS ANNOUNCEMENTS CARD -->
        <div class="card">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #581c87; margin-top: 0; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-clock-rotate-left" style="color: #9333ea;"></i> Previous Announcements
            </h3>

            <?php if (empty($announcements)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 20px 0; margin: 0;">No announcements yet.</p>
            <?php else: foreach ($announcements as $a): ?>
                <div class="announcement-item">
                    <div class="announcement-header">
                        <h4 class="announcement-title"><?= htmlspecialchars($a['title']) ?></h4>
                        <span class="announcement-date">
                            <i class="fa-regular fa-clock"></i> <?= date('M d, Y h:i A', strtotime($a['created_at'])) ?>
                        </span>
                    </div>
                    <p class="announcement-body"><?= htmlspecialchars($a['message']) ?></p>
                </div>
            <?php endforeach; endif; ?>

        </div>

    </div>
</div>

<?php echo "</div>"; ?>