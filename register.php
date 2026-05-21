<?php
require __DIR__ . '/config.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = 'user';

    if (!$pdo) {
        $error = $dbError;
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            redirect('login.php');
        } catch (PDOException $exception) {
            $error = 'Email is already registered.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register | medina</title>
  <link rel="stylesheet" href="styles.css?v=12">
</head>
<body class="auth-page">
  <main class="auth-card">
    <img class="logo-mark auth-logo" src="assets/medina-logo.svg" alt="Medina">
    <p class="eyebrow">Medical Appointment Management System</p>
    <h2>Create Account</h2>
    <p class="auth-subtitle">Register as a patient user and start booking appointments.</p>
    <?php if ($dbError): ?><?= database_alert($dbError) ?><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <label>Full Name
        <input name="full_name" type="text" required placeholder="Your full name">
      </label>
      <label>Email
        <input name="email" type="email" required placeholder="you@example.com">
      </label>
      <div class="grid-2">
        <label>Password
          <input name="password" type="password" required>
        </label>
        <label>Confirm
          <input name="confirm_password" type="password" required>
        </label>
      </div>
      <button class="primary" type="submit">Register</button>
    </form>
    <p class="auth-link">Already have an account? <a href="login.php">Login here</a></p>
  </main>
</body>
</html>
