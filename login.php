<?php
require __DIR__ . '/config.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$pdo) {
        $error = $dbError;
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];
            redirect('dashboard.php');
        }

        $error = 'Invalid email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | medina</title>
  <link rel="stylesheet" href="styles.css?v=12">
</head>
<body class="auth-page">
  <main class="auth-card">
    <img class="logo-mark auth-logo" src="assets/medina-logo.svg" alt="Medina">
    <p class="eyebrow">Medical Appointment Management System</p>
    <h2>Welcome back</h2>
    <p class="auth-subtitle">Sign in to manage appointments and clinic bookings.</p>
    <?php if ($dbError): ?><?= database_alert($dbError) ?><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <label>Email
        <input name="email" type="email" required placeholder="admin@medina.local">
      </label>
      <label>Password
        <input name="password" type="password" required placeholder="Enter password">
      </label>
      <button class="primary" type="submit">Login</button>
    </form>
    <p class="auth-link">No account yet? <a href="register.php">Register here</a></p>
  </main>
</body>
</html>
