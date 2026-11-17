<?php
// Fallback if not explicitly set by the page
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}

// Unread notifications count for the sidebar badge
$unread_count = 0;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/ensure_columns.php';
    sync_assignment_notifications($pdo, $_SESSION['user_id']);
    try {
        $u = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $u->execute([$_SESSION['user_id']]);
        $unread_count = (int)$u->fetchColumn();
    } catch (Exception $e) { $unread_count = 0; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'StudentWorks') ?> | StudentWorks</title>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            height: 100vh;
            background-color: #ffffff;
            position: fixed;
            top: 0;
            left: 0;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 20px 0;
            box-sizing: border-box;
            z-index: 1000;
        }
        .sidebar-top {
            display: flex;
            flex-direction: column;
        }
        .sidebar-brand {
            display: flex;
            align-items: center;
            padding: 0 20px 20px 20px;
            font-size: 20px;
            font-weight: bold;
            color: #6f42c1;
        }
        .sidebar-brand i {
            font-size: 24px;
            margin-right: 12px;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin: 4px 15px;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #4a5568;
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            border-radius: 12px;
            transition: all 0.2s ease;
        }
        .sidebar-menu a i {
            margin-right: 14px;
            font-size: 18px;
            color: #8b5cf6;
        }
        .sidebar-menu a:hover {
            background-color: #f3f0ff;
            color: #6f42c1;
        }
        /* Active Menu Item Styling */
        .sidebar-menu li.active a {
            background: linear-gradient(135deg, #a78bfa 0%, #8b5cf6 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }
        .sidebar-menu li.active a i {
            color: #ffffff;
        }
        .nav-badge {
            margin-left: auto;
            background: #ef4444;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
        }
        /* Logout Link Styling */
        .sidebar-menu .logout-link a {
            color: #ef4444;
        }
        .sidebar-menu .logout-link a i {
            color: #ef4444;
        }
        .sidebar-menu .logout-link a:hover {
            background-color: #fee2e2;
        }
        /* Main content offset so it doesn't hide behind the fixed sidebar */
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }

        /* ---- Small polish (shared by all student pages) ---- */
        :root {
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --accent-primary: #8b5cf6;
            --accent-gradient: linear-gradient(135deg, #a78bfa 0%, #8b5cf6 100%);
            --accent-gradient-hover: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            --radius-md: 12px;
            --transition: all 0.2s ease;
            --border-color: #e2e8f0;
        }
        html { scroll-behavior: smooth; }
        body { color: var(--text-dark); }
        ::selection { background: #ddd6fe; }
        .sidebar { overflow-y: auto; }
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {
            outline: 2px solid #c4b5fd; outline-offset: 2px;
        }
        .card {
            background: #ffffff; padding: 24px; border-radius: 16px;
            border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
        }
        .card-header { margin-bottom: 16px; }
        .card-header h3 {
            margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-dark);
            display: flex; align-items: center; gap: 10px;
        }
        .btn-primary, button[type="submit"].btn-primary {
            background: var(--accent-gradient); color: #ffffff; border: none; padding: 10px 20px;
            border-radius: 12px; font-weight: 600; cursor: pointer; font-family: inherit;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25); transition: var(--transition);
        }
        .btn-primary:hover { background: var(--accent-gradient-hover); transform: translateY(-1px); }
        .btn-primary:active { transform: translateY(0); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn, .btn-secondary {
            display: inline-flex; align-items: center; gap: 6px; background: #f3f0ff; color: #6f42c1;
            border: none; border-radius: 10px; font-weight: 600; text-decoration: none; cursor: pointer;
            font-family: inherit; transition: var(--transition);
        }
        .btn:hover, .btn-secondary:hover { background: #ede9fe; }
        .form-control {
            width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 10px;
            box-sizing: border-box; font-family: inherit; font-size: 0.95rem; transition: var(--transition);
        }
        input:focus, textarea:focus, select:focus {
            outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.12);
        }
        tbody tr { transition: background 0.15s ease; }
        tbody tr:hover td { background: #faf5ff; }
        div[onclick*="location.href"]:hover { transform: translateY(-1px); }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/file_preview.php'; ?>

<div class="sidebar">
    <div class="sidebar-top">
        <!-- Brand Header -->
        <div class="sidebar-brand">
            <i class="fa-solid fa-graduation-cap"></i>
            <span>STUDENTWORKS</span>
        </div>

        <!-- Navigation Menu -->
        <ul class="sidebar-menu">
            <li class="<?= ($current_page == 'student_dashboard.php') ? 'active' : ''; ?>">
                <a href="student_dashboard.php">
                    <i class="fa-solid fa-chart-pie"></i> Dashboard
                </a>
            </li>
            <li class="<?= ($current_page == 'academic_records.php') ? 'active' : ''; ?>">
                <a href="academic_records.php">
                    <i class="fa-solid fa-file-lines"></i> Academic Records
                </a>
            </li>
            <li class="<?= ($current_page == 'student_activities.php') ? 'active' : ''; ?>">
                <a href="student_activities.php">
                    <i class="fa-solid fa-list-check"></i> Activities
                </a>
            </li>
            <li class="<?= ($current_page == 'student_notifications.php') ? 'active' : ''; ?>">
                <a href="student_notifications.php">
                    <i class="fa-solid fa-bell"></i> Notifications
                    <?php if ($unread_count > 0): ?><span class="nav-badge"><?= $unread_count > 99 ? '99+' : $unread_count ?></span><?php endif; ?>
                </a>
            </li>
            <li class="<?= ($current_page == 'student_calendar.php') ? 'active' : ''; ?>">
                <a href="student_calendar.php">
                    <i class="fa-solid fa-calendar-days"></i> Calendar
                </a>
            </li>
            <li class="<?= ($current_page == 'student_profile.php') ? 'active' : ''; ?>">
                <a href="student_profile.php">
                    <i class="fa-solid fa-user"></i> Profile
                </a>
            </li>
            <li class="<?= ($current_page == 'student_help.php') ? 'active' : ''; ?>">
                <a href="student_help.php">
                    <i class="fa-solid fa-circle-question"></i> Help
                </a>
            </li>
            <li class="<?= ($current_page == 'student_settings.php') ? 'active' : ''; ?>">
                <a href="student_settings.php">
                    <i class="fa-solid fa-gear"></i> Settings
                </a>
            </li>
        </ul>
    </div>

    <!-- Bottom Logout Link -->
    <ul class="sidebar-menu">
        <li class="logout-link">
            <a href="logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Log Out
            </a>
        </li>
    </ul>
</div>

<div class="main-content">