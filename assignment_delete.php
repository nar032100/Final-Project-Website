<?php
// Shared helper: lets a teacher delete an assignment they posted.
// Used by teacher_create_assignment.php, teacher_reports.php and teacher_gradebook.php.

function asg_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

// Hidden inputs every delete form must include.
function asg_delete_fields($activity_id) {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(asg_csrf_token()) . '">'
         . '<input type="hidden" name="activity_id" value="' . (int)$activity_id . '">';
}

// JS confirm() text for the Delete button's onsubmit.
function asg_confirm_js($sub_count) {
    $sub_count = (int)$sub_count;
    $extra = $sub_count > 0
        ? "Its $sub_count student submission(s) and grades will also be permanently deleted."
        : 'This cannot be undone.';
    return "return confirm('Delete this assignment?\\n\\n" . $extra . "');";
}

// Call at the top of a page (before any HTML). Returns null when the request is not a
// delete request, otherwise ['type' => 'success'|'error', 'msg' => '...'].
function process_assignment_delete(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['delete_assignment'])) {
        return null;
    }
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    $token_ok = isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    if (!$token_ok) {
        return ['type' => 'error', 'msg' => 'Your session expired. Please refresh the page and try again.'];
    }

    $id = (int)($_POST['activity_id'] ?? 0);
    if ($id <= 0) {
        return ['type' => 'error', 'msg' => 'Assignment not found.'];
    }

    // Any logged-in teacher may delete an assignment
    if (($_SESSION['user_role'] ?? '') !== 'teacher') {
        return ['type' => 'error', 'msg' => 'Only teachers can delete assignments.'];
    }
    $own = $pdo->prepare("SELECT * FROM activities WHERE id = ?");
    $own->execute([$id]);
    $act = $own->fetch();
    if (!$act) {
        return ['type' => 'error', 'msg' => 'Assignment not found.'];
    }

    // Remove a stored file only if it lives inside the uploads folder
    $remove_file = function ($rel) {
        if (empty($rel)) { return; }
        $base = realpath(__DIR__ . '/uploads');
        $full = realpath(__DIR__ . '/' . $rel);
        if ($base && $full && strpos($full, $base . DIRECTORY_SEPARATOR) === 0 && is_file($full)) {
            @unlink($full);
        }
    };

    try {
        $pdo->beginTransaction();

        $subfiles = $pdo->prepare("SELECT file_path FROM submissions WHERE activity_id = ?");
        $subfiles->execute([$id]);
        $subfiles = $subfiles->fetchAll(PDO::FETCH_COLUMN);

        $pdo->prepare("DELETE FROM submissions WHERE activity_id = ?")->execute([$id]);

        try {
            $pdo->prepare("DELETE FROM notifications WHERE activity_id = ?")->execute([$id]);
        } catch (Exception $e) {}

        $pdo->prepare("DELETE FROM activities WHERE id = ?")->execute([$id]);

        $pdo->commit();

        // Files are removed only after the database delete succeeded
        $remove_file($act['attachment_path'] ?? null);
        foreach ($subfiles as $sf) { $remove_file($sf); }

        return ['type' => 'success', 'msg' => 'Assignment deleted.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return ['type' => 'error', 'msg' => 'Could not delete the assignment. Please try again.'];
    }
}