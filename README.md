# medina

Simple PHP and MySQL medical appointment management system.

## Features

- User registration
- One default admin account
- Login/logout sessions
- User role can create appointments and manage only their own scheduled appointments
- Admin role can view all appointments, edit all records, update status, and delete records
- Appointment records are stored in MySQL

## Setup

1. Start Apache/PHP and MySQL in XAMPP, WAMP, Laragon, or another local server.
2. Import `database.sql` using phpMyAdmin, or double-click `import-database.bat` after MySQL is running.
4. If needed, edit database login settings in `config.php`.
5. Recommended XAMPP method: copy the whole `medina` folder into:

```text
C:\xampp\htdocs\medina
```

6. Start Apache and MySQL in XAMPP, then open:

```text
http://localhost/medina/
```

Alternative: you can double-click `start-medina.bat`, then open:

```text
http://localhost:8001/
```

Default admin account:

```text
Email: admin@medina.local
Password: admin123
```

The register page creates normal user accounts only.
