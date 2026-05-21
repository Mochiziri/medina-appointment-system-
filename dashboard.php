<?php
require __DIR__ . '/config.php';
require_login();

$search = trim($_GET['search'] ?? '');
$editId = (int) ($_GET['edit'] ?? 0);
$pageError = $_GET['error'] ?? '';
$params = [];
$where = '';

if (!$pdo) {
    $appointments = [];
    $stats = ['total' => 0, 'scheduled' => 0, 'completed' => 0, 'cancelled' => 0];
    $users = [];
    $doctorAvailability = [];
    $editing = null;
    $today = date('Y-m-d');
    $calendarMonth = date('Y-m');
    $calendarTitle = date('F Y');
    $calendarDays = [];
    $calendarStartOffset = 0;
    $appointmentsByDate = [];
    goto render_page;
}

if (!is_admin()) {
    $where = 'WHERE appointments.user_id = ?';
    $params[] = current_user()['id'];
}

if ($search !== '') {
    $searchSql = "(patient_name LIKE ? OR contact_number LIKE ? OR doctor_name LIKE ? OR department LIKE ? OR reason LIKE ? OR status LIKE ?)";
    $searchParams = array_fill(0, 6, "%{$search}%");
    $where .= $where ? " AND {$searchSql}" : "WHERE {$searchSql}";
    $params = array_merge($params, $searchParams);
}

$stmt = $pdo->prepare(
    "SELECT appointments.*, users.full_name AS owner_name
     FROM appointments
     JOIN users ON users.id = appointments.user_id
     {$where}
     ORDER BY appointment_date ASC, appointment_time ASC"
);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$calendarMonth = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
$monthStart = new DateTimeImmutable($calendarMonth . '-01');
$calendarTitle = $monthStart->format('F Y');
$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$calendarDays = range(1, (int) $monthStart->format('t'));
$calendarStartOffset = (int) $monthStart->format('w');
$calendarEndOffset = (7 - (($calendarStartOffset + count($calendarDays)) % 7)) % 7;
$appointmentsByDate = [];

foreach ($appointments as $appointment) {
    if (str_starts_with($appointment['appointment_date'], $calendarMonth)) {
        $appointmentsByDate[$appointment['appointment_date']][] = $appointment;
    }
}

$allStatsWhere = is_admin() ? '' : 'WHERE user_id = ?';
$allStatsParams = is_admin() ? [] : [current_user()['id']];
$statsStmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'Scheduled') AS scheduled,
        SUM(status = 'Completed') AS completed,
        SUM(status = 'Cancelled') AS cancelled
     FROM appointments {$allStatsWhere}"
);
$statsStmt->execute($allStatsParams);
$stats = $statsStmt->fetch() ?: ['total' => 0, 'scheduled' => 0, 'completed' => 0, 'cancelled' => 0];

$editing = null;
if ($editId > 0 && is_admin()) {
    $editStmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ?');
    $editStmt->execute([$editId]);
    $editing = $editStmt->fetch() ?: null;
}

$today = date('Y-m-d');
render_page:
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard | medina</title>
  <link rel="stylesheet" href="styles.css?v=12">
</head>
<body>
  <header class="topbar">
    <div>
      <div class="brand-row">
        <img class="logo-mark header-logo" src="assets/medina-logo.svg" alt="Medina">
        <p class="eyebrow">Medical Appointment Management System</p>
      </div>
      <h1>Appointment</h1>
    </div>
    <nav class="user-nav">
      <a href="logout.php">Logout</a>
    </nav>
  </header>

  <main class="layout">
    <?php if ($dbError): ?>
      <section class="panel form-panel full-width">
        <h2>Database Not Running</h2>
        <?= database_alert($dbError) ?>
        <p class="hint">Open XAMPP, start MySQL, run import-database.bat, then refresh.</p>
      </section>
    <?php else: ?>
    <?php if (is_admin()): ?>
      <section class="admin-hero full-width">
        <div>
          <h2>Appointment Dashboard</h2>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!is_admin()): ?><section class="user-dashboard full-width"><?php endif; ?>

    <section class="panel form-panel <?= is_admin() ? '' : 'user-booking-panel' ?>">
      <div class="section-head">
        <h2><?= $editing ? 'Edit Booking' : (is_admin() ? 'Booking Control' : 'Book Appointment') ?></h2>
        <?php if ($editing): ?><a class="ghost link-button" href="dashboard.php">Cancel</a><?php endif; ?>
      </div>

      <form method="post" action="save_appointment.php">
        <?php if ($pageError === 'past_datetime'): ?>
          <div class="alert error">Please choose a future appointment date and time.</div>
        <?php endif; ?>
        <input type="hidden" name="appointment_id" value="<?= e((string)($editing['id'] ?? '')) ?>">

        <label>
          Patient Name
          <input name="patient_name" type="text" value="<?= e($editing['patient_name'] ?? '') ?>" placeholder="e.g. Maria Santos" required>
        </label>

        <label>
          Contact Number
          <input name="contact_number" type="tel" value="<?= e($editing['contact_number'] ?? '') ?>" placeholder="e.g. 0917 123 4567" required>
        </label>

        <div class="grid-2">
          <label>
            Doctor
            <select name="doctor_name" required>
              <option value="">Select doctor</option>
              <?php foreach (['Dr. Medina', 'Dr. Reyes', 'Dr. Cruz', 'Dr. Garcia'] as $doctor): ?>
                <option <?= ($editing['doctor_name'] ?? '') === $doctor ? 'selected' : '' ?>><?= e($doctor) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label>
            Department
            <select name="department" required>
              <option value="">Select department</option>
              <?php foreach (['General Medicine', 'Pediatrics', 'Cardiology', 'Dental', 'Laboratory'] as $dept): ?>
                <option <?= ($editing['department'] ?? '') === $dept ? 'selected' : '' ?>><?= e($dept) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <div class="grid-2">
          <label>
            Date
            <input id="appointmentDate" name="appointment_date" type="date" min="<?= e($today) ?>" value="<?= e($editing['appointment_date'] ?? '') ?>" required>
          </label>

          <label>
            Time
            <input id="appointmentTime" name="appointment_time" type="time" value="<?= e(isset($editing['appointment_time']) ? substr($editing['appointment_time'], 0, 5) : '') ?>" required>
          </label>
        </div>

        <label>
          Reason for Visit
          <textarea name="reason" rows="3" placeholder="Brief reason or symptoms" required><?= e($editing['reason'] ?? '') ?></textarea>
        </label>

        <?php if (is_admin()): ?>
          <label>
            Status
            <select name="status" required>
              <?php foreach (['Scheduled', 'Completed', 'Cancelled'] as $status): ?>
                <option <?= ($editing['status'] ?? 'Scheduled') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>

        <button class="primary" type="submit"><?= $editing ? 'Update Appointment' : 'Save Appointment' ?></button>
      </form>
    </section>

    <?php if (!is_admin()): ?>
      <section class="panel calendar-panel user-calendar-panel">
        <div class="section-head calendar-head">
          <div>
            <h2>Appointment Calendar</h2>
            <p>Your scheduled appointment dates.</p>
          </div>
          <div class="calendar-nav">
            <a class="ghost link-button" href="dashboard.php?month=<?= e($previousMonth) ?>">Prev</a>
            <strong><?= e($calendarTitle) ?></strong>
            <a class="ghost link-button" href="dashboard.php?month=<?= e($nextMonth) ?>">Next</a>
          </div>
        </div>
        <div class="calendar-grid">
          <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
            <div class="calendar-weekday"><?= e($weekday) ?></div>
          <?php endforeach; ?>
          <?php for ($blank = 0; $blank < $calendarStartOffset; $blank++): ?>
            <div class="calendar-day is-empty"></div>
          <?php endfor; ?>
          <?php foreach ($calendarDays as $day): ?>
            <?php
              $dateKey = $calendarMonth . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
              $dayAppointments = $appointmentsByDate[$dateKey] ?? [];
            ?>
            <div class="calendar-day <?= $dateKey === $today ? 'is-today' : '' ?>" data-date="<?= e($dateKey) ?>">
              <div class="day-number"><?= $day ?></div>
              <?php foreach (array_slice($dayAppointments, 0, 2) as $item): ?>
                <div class="calendar-event">
                  <strong><?= e(substr($item['appointment_time'], 0, 5)) ?> - <?= e($item['doctor_name']) ?></strong>
                  <span><?= e($item['department']) ?></span>
                  <em><?= e($item['status']) ?></em>
                </div>
              <?php endforeach; ?>
              <?php if (count($dayAppointments) > 2): ?>
                <div class="calendar-more">+<?= count($dayAppointments) - 2 ?> more</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php for ($blank = 0; $blank < $calendarEndOffset; $blank++): ?>
            <div class="calendar-day is-empty"></div>
          <?php endfor; ?>
        </div>
      </section>
    </section>
    <?php endif; ?>

    <?php if (is_admin()): ?>
    <section class="content">
      <div class="stats">
        <article><span><?= (int) $stats['total'] ?></span><p>Total</p></article>
        <article><span><?= (int) $stats['scheduled'] ?></span><p>Scheduled</p></article>
        <article><span><?= (int) $stats['completed'] ?></span><p>Completed</p></article>
        <article><span><?= (int) $stats['cancelled'] ?></span><p>Cancelled</p></article>
      </div>

      <section class="panel calendar-panel">
        <div class="section-head calendar-head">
          <div>
            <h2>Appointment Calendar</h2>
            <p>Monthly view of all bookings.</p>
          </div>
          <div class="calendar-nav">
            <a class="ghost link-button" href="dashboard.php?month=<?= e($previousMonth) ?>">Prev</a>
            <strong><?= e($calendarTitle) ?></strong>
            <a class="ghost link-button" href="dashboard.php?month=<?= e($nextMonth) ?>">Next</a>
          </div>
        </div>
        <div class="calendar-grid">
          <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
            <div class="calendar-weekday"><?= e($weekday) ?></div>
          <?php endforeach; ?>
          <?php for ($blank = 0; $blank < $calendarStartOffset; $blank++): ?>
            <div class="calendar-day is-empty"></div>
          <?php endfor; ?>
          <?php foreach ($calendarDays as $day): ?>
            <?php
              $dateKey = $calendarMonth . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
              $dayAppointments = $appointmentsByDate[$dateKey] ?? [];
            ?>
            <button class="calendar-day <?= $dateKey === $today ? 'is-today' : '' ?>" type="button" data-date="<?= e($dateKey) ?>">
              <div class="day-number"><?= $day ?></div>
              <?php foreach (array_slice($dayAppointments, 0, 2) as $item): ?>
                <a class="calendar-event" href="dashboard.php?edit=<?= (int) $item['id'] ?>">
                  <strong><?= e(substr($item['appointment_time'], 0, 5)) ?> - <?= e($item['patient_name']) ?></strong>
                  <span><?= e($item['doctor_name']) ?>, <?= e($item['department']) ?></span>
                  <em><?= e($item['status']) ?></em>
                </a>
              <?php endforeach; ?>
              <?php if (count($dayAppointments) > 2): ?>
                <div class="calendar-more">+<?= count($dayAppointments) - 2 ?> more</div>
              <?php endif; ?>
            </button>
          <?php endforeach; ?>
          <?php for ($blank = 0; $blank < $calendarEndOffset; $blank++): ?>
            <div class="calendar-day is-empty"></div>
          <?php endfor; ?>
        </div>
      </section>

      <section class="panel appointments-panel">
        <div class="section-head appointments-head">
          <div>
            <h2><?= is_admin() ? 'Booking Management' : 'My Appointments' ?></h2>
            <p><?= is_admin() ? 'Review, edit, update status, and delete appointment bookings.' : 'Users can create appointments and edit scheduled records only.' ?></p>
          </div>
          <form class="search-form" method="get">
            <input name="search" type="search" value="<?= e($search) ?>" placeholder="Search patient, doctor, department...">
          </form>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Patient</th>
                <?php if (is_admin()): ?><th>Owner</th><?php endif; ?>
                <th>Doctor</th>
                <th>Schedule</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($appointments as $appointment): ?>
                <tr>
                  <td>
                    <span class="patient-name"><?= e($appointment['patient_name']) ?></span>
                    <span class="subtext"><?= e($appointment['contact_number']) ?></span>
                  </td>
                  <?php if (is_admin()): ?><td><?= e($appointment['owner_name']) ?></td><?php endif; ?>
                  <td>
                    <?= e($appointment['doctor_name']) ?>
                    <div class="subtext"><?= e($appointment['department']) ?></div>
                  </td>
                  <td><?= e(date('M j, Y g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']))) ?></td>
                  <td><?= e($appointment['reason']) ?></td>
                  <td><span class="badge <?= e($appointment['status']) ?>"><?= e($appointment['status']) ?></span></td>
                  <td>
                    <div class="actions">
                      <a class="action-btn link-button" href="dashboard.php?edit=<?= (int) $appointment['id'] ?>">Edit</a>
                      <form method="post" action="delete_appointment.php" onsubmit="return confirm('Delete this appointment?');">
                        <input type="hidden" name="id" value="<?= (int) $appointment['id'] ?>">
                        <button class="action-btn delete-btn" type="submit">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if (!$appointments): ?>
          <div class="empty-state is-visible">
            <h3>No appointments found</h3>
            <p>Add an appointment using the form.</p>
          </div>
        <?php endif; ?>
      </section>
    </section>
    <?php endif; ?>
    <?php endif; ?>
  </main>
  <script>
    const appointmentDateInput = document.getElementById('appointmentDate');
    const appointmentTimeInput = document.getElementById('appointmentTime');

    function pad(value) {
      return String(value).padStart(2, '0');
    }

    function todayString() {
      const now = new Date();
      return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
    }

    function currentTimeString() {
      const now = new Date();
      return `${pad(now.getHours())}:${pad(now.getMinutes())}`;
    }

    function updateTimeLimit() {
      if (!appointmentDateInput || !appointmentTimeInput) return;
      const today = todayString();
      appointmentDateInput.min = today;

      if (appointmentDateInput.value === today) {
        appointmentTimeInput.min = currentTimeString();
      } else {
        appointmentTimeInput.removeAttribute('min');
      }
    }

    appointmentDateInput?.addEventListener('change', updateTimeLimit);
    updateTimeLimit();

    document.querySelectorAll('[data-date]').forEach((day) => {
      day.addEventListener('click', (event) => {
        if (event.target.closest('a')) return;
        const dateInput = document.querySelector('input[name="appointment_date"]');
        if (!dateInput) return;
        dateInput.value = day.dataset.date;
        updateTimeLimit();
        dateInput.focus();
        document.querySelector('.form-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  </script>
</body>
</html>
