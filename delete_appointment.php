<?php
require __DIR__ . '/config.php';
require_login();

if (!$pdo) {
    redirect('dashboard.php');
}

if (!is_admin()) {
    redirect('dashboard.php');
}

$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM appointments WHERE id = ?');
    $stmt->execute([$id]);
}

redirect('dashboard.php');
