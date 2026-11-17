<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'StudentWorks' ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --accent-gradient: linear-gradient(135deg, #a5b4fc 0%, #c084fc 100%);
            --accent-gradient-hover: linear-gradient(135deg, #818cf8 0%, #a855f7 100%);
        }
        body {
            margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc; color: var(--text-dark); display: flex; min-height: 100vh;
        }
        .sidebar {
            width: var(--sidebar-width); 
            background: #ffffff; 
            border-right: 1px solid rgba(226, 232, 240, 0.9);
            display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.02);
        }
        .sidebar-brand {
            padding: 24px; font-size: 1.2rem; font-weight: 800; display: flex; align-items: center; gap: 12px; color: #6b21a8;
        }
        .sidebar-menu { list-style: none; padding: 0 16px; margin: 0; display: flex; flex-direction: column; gap: 6px; flex-grow: 1; }
        .sidebar-menu a {
            display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px;
            color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all 0.3s ease;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: var(--accent-gradient); color: #ffffff;
            box-shadow: 0 4px 15px rgba(192, 132, 252, 0.25);
        }
        .sidebar-menu a:hover i, .sidebar-menu a.active i {
            color: #ffffff !important;
        }
        .main-content {
            margin-left: var(--sidebar-width); flex-grow: 1; padding: 32px; box-sizing: border-box;
            background: linear-gradient(135deg, #f8fafc 0%, #f3f0ff 100%); min-height: 100vh;
        }
        .welcome-banner { margin-bottom: 28px; }
        .welcome-banner h1 { font-size: 1.8rem; font-weight: 800; margin: 0 0 6px 0; color: #581c87; }
        .welcome-banner p { color: #7e22ce; opacity: 0.8; margin: 0; font-size: 0.95rem; }

        .portal-container { display: flex; flex-direction: column; gap: 24px; margin-top: 10px; }
        .card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            padding: 32px; border-radius: 20px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }
        
        .btn-primary, button[type="submit"] {
            background: var(--accent-gradient);
            color: #ffffff; border: none; padding: 12px 24px; border-radius: 12px;
            font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(192, 132, 252, 0.4);
            transition: all 0.3s ease;
        }
        .btn-primary:hover, button[type="submit"]:hover {
            background: var(--accent-gradient-hover);
            transform: translateY(-1px); box-shadow: 0 6px 20px rgba(192, 132, 252, 0.6);
        }

        /* ---- Small polish ---- */
        html { scroll-behavior: smooth; }
        ::selection { background: #ddd6fe; }
        .sidebar { overflow-y: auto; }
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {
            outline: 2px solid #c4b5fd; outline-offset: 2px;
        }
        input:focus, textarea:focus, select:focus {
            outline: none; border-color: #9333ea; box-shadow: 0 0 0 4px rgba(147, 51, 234, 0.12);
        }
        .btn-primary:active, button[type="submit"]:active { transform: translateY(0); }
        .btn-primary:disabled, button[type="submit"]:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        tbody tr { transition: background 0.15s ease; }
        tbody tr:hover td { background: #faf5ff; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/file_preview.php'; ?>

    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-graduation-cap" style="color: #9333ea;"></i> STUDENTWORKS
        </div>
        <ul class="sidebar-menu">
            <li><a href="teacher_dashboard.php" class="<?= $current_page == 'teacher_dashboard.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-pie" style="color: #9333ea;"></i> Dashboard</a></li>
            <li><a href="teacher_students.php" class="<?= $current_page == 'teacher_students.php' ? 'active' : '' ?>"><i class="fa-solid fa-folder-open" style="color: #9333ea;"></i> Student Files</a></li>
            <li><a href="teacher_create_assignment.php" class="<?= $current_page == 'teacher_create_assignment.php' ? 'active' : '' ?>"><i class="fa-solid fa-pen-to-square" style="color: #9333ea;"></i> Create Assignment</a></li>
            <li><a href="teacher_gradebook.php" class="<?= $current_page == 'teacher_gradebook.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-line" style="color: #9333ea;"></i> Gradebook</a></li>
            <li><a href="teacher_reports.php" class="<?= $current_page == 'teacher_reports.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-column" style="color: #9333ea;"></i> Reports</a></li>
            <li><a href="teacher_announcements.php" class="<?= $current_page == 'teacher_announcements.php' ? 'active' : '' ?>"><i class="fa-solid fa-bullhorn" style="color: #9333ea;"></i> Announcements</a></li>
            <li><a href="teacher_settings.php" class="<?= $current_page == 'teacher_settings.php' ? 'active' : '' ?>"><i class="fa-solid fa-gear" style="color: #9333ea;"></i> Settings</a></li>
            <li style="margin-top: auto; margin-bottom: 20px;"><a href="logout.php" style="color: #ef4444;"><i class="fa-solid fa-right-from-bracket" style="color: #ef4444;"></i> Log Out</a></li>
        </ul>
    </div>

    <div class="main-content">