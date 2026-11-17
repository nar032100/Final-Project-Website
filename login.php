<?php
session_start();
if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
require_once 'db.php';

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - StudentWorks</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: linear-gradient(-45deg, #e0e7ff, #f3e8ff, #f0f9ff, #f1f5f9); animation: gradientBG 15s ease infinite; background-size: 400% 400%; }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px); padding: 40px; border-radius: 20px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; }
        .brand { font-size: 1.4rem; font-weight: 700; text-align: center; margin-bottom: 24px; color: #0f172a; }
        input { width: 100%; padding: 12px; margin-bottom: 18px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.95rem; }
        button { width: 100%; padding: 12px; background: #3b82f6; color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; }
        p { text-align: center; margin-top: 16px; font-size: 0.88rem; color: #64748b; }
        p a { color: #2563eb; text-decoration: none; font-weight: 600; }
        .divider { display: flex; align-items: center; gap: 10px; margin: 20px 0 14px; color: #94a3b8; font-size: 0.8rem; }
        .divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: #e2e8f0; }
        .guest-btn { display: block; text-align: center; padding: 12px; background: #fff; color: #3b82f6; border: 1.5px solid #3b82f6; border-radius: 10px; font-weight: 600; font-size: 0.95rem; text-decoration: none; }
        .guest-btn:hover { background: #eff6ff; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand"><i class="fa-solid fa-graduation-cap" style="color:#3b82f6;"></i> STUDENTWORKS</div>
        <?php if ($error): ?><p style="color: #ef4444; margin-bottom: 12px; text-align: left;"><?= $error ?></p><?php endif; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Log In</button>
        </form>
        <div class="divider">or</div>
        <a class="guest-btn" href="guest_assignments.php"><i class="fa-solid fa-eye"></i> Continue as Guest</a>
        <p>Don't have an account? <a href="register.php">Register</a></p>
    </div>
</body>
</html>