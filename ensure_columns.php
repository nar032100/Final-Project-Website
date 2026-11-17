<?php
// Adds the attachment/link columns to `activities` the first time they are needed.
function ensure_activity_columns(PDO $pdo) {
    try {
        $cols = ['attachment_path' => 'VARCHAR(255) DEFAULT NULL', 'reference_link' => 'VARCHAR(255) DEFAULT NULL'];
        foreach ($cols as $name => $def) {
            $has = $pdo->query("SHOW COLUMNS FROM activities LIKE '" . $name . "'")->fetch();
            if (!$has) {
                $pdo->exec("ALTER TABLE activities ADD COLUMN $name $def");
            }
        }
    } catch (Exception $e) {
        // ignore; pages still work without the optional columns
    }
}

// Lets a notification remember which activity it is about
function ensure_notification_columns(PDO $pdo) {
    try {
        $has = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'activity_id'")->fetch();
        if (!$has) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN activity_id INT DEFAULT NULL");
        }
    } catch (Exception $e) {
    }
}

// Gives a student a "New assignment" notification for every still-open assignment they
// have not been told about yet (e.g. posted before their account existed) and have not submitted.
function sync_assignment_notifications(PDO $pdo, $student_id) {
    try {
        ensure_notification_columns($pdo);
        $stmt = $pdo->prepare("
            SELECT a.id, a.title
            FROM activities a
            WHERE (a.deadline IS NULL OR a.deadline > NOW())
              AND NOT EXISTS (
                    SELECT 1 FROM notifications n
                    WHERE n.user_id = ?
                      AND (n.activity_id = a.id OR n.message = CONCAT('New assignment: ', a.title))
              )
              AND NOT EXISTS (
                    SELECT 1 FROM submissions s WHERE s.activity_id = a.id AND s.student_id = ?
              )
        ");
        $stmt->execute([$student_id, $student_id]);
        $ins = $pdo->prepare("INSERT INTO notifications (user_id, message, activity_id) VALUES (?, ?, ?)");
        foreach ($stmt->fetchAll() as $a) {
            $ins->execute([$student_id, 'New assignment: ' . mb_substr($a['title'], 0, 200), $a['id']]);
        }
    } catch (Exception $e) {
        // ignore; notifications are optional
    }
}