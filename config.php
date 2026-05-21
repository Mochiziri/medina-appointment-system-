<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Manila');

$dbHost = '127.0.0.1';
$dbName = 'medina_db';
$dbUser = 'root';
$dbPass = '';

$dbError = '';
$pdo = null;

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 3,
        ]
    );
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$dbName}");
    $pdo->exec("USE {$dbName}");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
          id INT AUTO_INCREMENT PRIMARY KEY,
          full_name VARCHAR(120) NOT NULL,
          email VARCHAR(160) NOT NULL UNIQUE,
          password_hash VARCHAR(255) NOT NULL,
          role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS appointments (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          patient_name VARCHAR(120) NOT NULL,
          contact_number VARCHAR(40) NOT NULL,
          doctor_name VARCHAR(120) NOT NULL,
          department VARCHAR(120) NOT NULL,
          appointment_date DATE NOT NULL,
          appointment_time TIME NOT NULL,
          reason TEXT NOT NULL,
          status ENUM('Scheduled', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Scheduled',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    $adminEmail = 'admin@medina.local';
    $adminPassword = '$2y$10$Tlt1RurC90Faz9M3gHptUetZ3DDY.Enxi8AJERELh9dP9vlu0KsZW';
    $adminStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $adminStmt->execute([$adminEmail]);
    if (!$adminStmt->fetch()) {
        $createAdmin = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $createAdmin->execute(['System Admin', $adminEmail, $adminPassword, 'admin']);
    }
} catch (PDOException $error) {
    $dbError = 'Database connection failed. Start MySQL in XAMPP, import database.sql, then refresh this page.';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function redirect(string $path): void
{
    header("Location: {$path}");
    exit;
}

function database_alert(string $message): string
{
    return '<div class="alert error">' . e($message) . '</div>';
}
