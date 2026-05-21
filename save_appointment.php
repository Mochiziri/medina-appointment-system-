<?php
require __DIR__ . '/config.php';
require_login();

if (!$pdo) {
    redirect('dashboard.php');
}

$id = (int) ($_POST['appointment_id'] ?? 0);
$patientName = trim($_POST['patient_name'] ?? '');
$contactNumber = trim($_POST['contact_number'] ?? '');
$doctorName = trim($_POST['doctor_name'] ?? '');
$department = trim($_POST['department'] ?? '');
$appointmentDate = $_POST['appointment_date'] ?? '';
$appointmentTime = $_POST['appointment_time'] ?? '';
$reason = trim($_POST['reason'] ?? '');
$status = is_admin() ? ($_POST['status'] ?? 'Scheduled') : 'Scheduled';
$appointmentDateTime = DateTime::createFromFormat('Y-m-d H:i', "{$appointmentDate} {$appointmentTime}");
$now = new DateTime();

if (!in_array($status, ['Scheduled', 'Completed', 'Cancelled'], true)) {
    $status = 'Scheduled';
}

if (!$appointmentDateTime || $appointmentDateTime < $now) {
    redirect('dashboard.php?error=past_datetime');
}

if ($id > 0 && !is_admin()) {
    redirect('dashboard.php');
}

if ($id > 0) {
    if (is_admin()) {
        $stmt = $pdo->prepare('UPDATE appointments SET patient_name = ?, contact_number = ?, doctor_name = ?, department = ?, appointment_date = ?, appointment_time = ?, reason = ?, status = ? WHERE id = ?');
        $stmt->execute([$patientName, $contactNumber, $doctorName, $department, $appointmentDate, $appointmentTime, $reason, $status, $id]);
    }
} else {
    $stmt = $pdo->prepare('INSERT INTO appointments (user_id, patient_name, contact_number, doctor_name, department, appointment_date, appointment_time, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([current_user()['id'], $patientName, $contactNumber, $doctorName, $department, $appointmentDate, $appointmentTime, $reason, $status]);
}

redirect('dashboard.php');
