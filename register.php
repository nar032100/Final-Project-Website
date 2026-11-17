<?php
session_start();
if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
require_once 'db.php';

$error = ""; $success = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $course = trim($_POST['course'] ?? '');
    $section = trim($_POST['section'] ?? '');
    // Only students get a section. Format matches the teacher's folders, e.g. BSIT-3 + W = BSIT-3W
    $valid_courses = ['BSIT-1','BSIT-2','BSIT-3','BSIT-4','BSCS-1','BSCS-2','BSCS-3','BSCS-4','ACT-1','ACT-2'];
    $role = ($role === 'teacher') ? 'teacher' : 'student';
    $course_section = '';
    if ($role === 'student' && in_array($course, $valid_courses, true) && preg_match('/^[A-Z]$/', $section)) {
        $course_section = $course . $section;
    }

    if (!empty($full_name) && !empty($email) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            // Make sure the section column exists (same column teacher_students.php reads)
            try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS section VARCHAR(100) DEFAULT ''"); } catch (Exception $e) {}
            try {
                $ins = $pdo->prepare("INSERT INTO users (full_name, email, password, role, section) VALUES (?, ?, ?, ?, ?)");
                if ($ins->execute([$full_name, $email, $hash, $role, $course_section])) {
                    $success = "Registration successful! You can now login.";
                }
            } catch (Exception $e) {
                $error = "Could not save your account (section column problem): " . htmlspecialchars($e->getMessage());
            }
        }
    } else {
        $error = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - StudentWorks Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            background: #f1f5f9;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(168, 85, 247, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(6, 182, 212, 0.15) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(99, 102, 241, 0.1) 0px, transparent 50%);
            background-attachment: fixed;
            padding: 20px;
        }
        .card { 
            background: rgba(255, 255, 255, 0.92); 
            backdrop-filter: blur(16px); 
            padding: 36px; 
            border-radius: 24px; 
            width: 100%; 
            max-width: 440px; 
            box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.05), 0 10px 15px -5px rgba(0, 0, 0, 0.02); 
            border: 1px solid rgba(255, 255, 255, 0.4); 
        }
        .brand { font-size: 1.35rem; font-weight: 800; text-align: center; margin-bottom: 22px; color: #0f172a; letter-spacing: -0.5px; }
        .brand i { color: #6366f1; }
        label { display: block; font-size: 0.8rem; font-weight: 700; color: #64748b; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        input, select { width: 100%; padding: 11px 14px; margin-bottom: 14px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 0.9rem; outline: none; background: #f8fafc; transition: all 0.2s; }
        input:focus, select:focus { border-color: #6366f1; background: #ffffff; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); }
        .row-group { display: flex; gap: 10px; }
        .row-group > div { flex: 1; }
        button { width: 100%; padding: 13px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #fff; border: none; border-radius: 12px; font-weight: 700; font-size: 0.95rem; cursor: pointer; box-shadow: 0 8px 25px rgba(99, 102, 241, 0.28); transition: all 0.2s; margin-top: 6px; }
        button:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(99, 102, 241, 0.38); }
        .alert-error { background: #fee2e2; color: #ef4444; padding: 10px 14px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; font-weight: 600; }
        .alert-success { background: #dcfce7; color: #16a34a; padding: 10px 14px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; font-weight: 600; }
        p { text-align: center; margin-top: 18px; font-size: 0.88rem; color: #64748b; font-weight: 500; }
        p a { color: #6366f1; text-decoration: none; font-weight: 700; }
        p a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand"><i class="fa-solid fa-graduation-cap"></i> STUDENTWORKS</div>
        
        <?php if ($error): ?><div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div><?php endif; ?>
        
        <form method="POST">
            <label>Full Name</label>
            <input type="text" name="full_name" placeholder="Enter your full name" required>
            
            <label>Email Address</label>
            <input type="email" name="email" placeholder="name@example.com" required>
            
            <label>Password</label>
            <input type="password" name="password" placeholder="Create a password" required>
            
            <label>Role</label>
            <select name="role">
                <option value="student">Student</option>
                <option value="teacher">Teacher / Instructor</option>
            </select>

            <div class="row-group">
                <div>
                    <label>Course / Year</label>
                    <select name="course">
                        <option value="BSIT-1">BSIT-1</option>
                        <option value="BSIT-2">BSIT-2</option>
                        <option value="BSIT-3">BSIT-3</option>
                        <option value="BSIT-4">BSIT-4</option>
                        <option value="BSCS-1">BSCS-1</option>
                        <option value="BSCS-2">BSCS-2</option>
                        <option value="BSCS-3">BSCS-3</option>
                        <option value="BSCS-4">BSCS-4</option>
                        <option value="ACT-1">ACT-1</option>
                        <option value="ACT-2">ACT-2</option>
                    </select>
                </div>
                <div>
                    <label>Section</label>
                    <select name="section">
                        <?php 
                        // Loop para sa A hanggang Z options
                        for ($i = ord('A'); $i <= ord('Z'); $i++) {
                            $char = chr($i);
                            echo '<option value="' . $char . '">' . $char . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <button type="submit">Create Account</button>
        </form>
        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
</body>
</html>