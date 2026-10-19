<?php
declare(strict_types=1);

$dbHost = getenv('STUDENT_TASK_DB_HOST') ?: 'localhost';
$dbPort = getenv('STUDENT_TASK_DB_PORT') ?: '3306';
$dbName = 'student_task_planner';
$dbUser = getenv('STUDENT_TASK_DB_USER') ?: 'root';
$dbPassword = getenv('STUDENT_TASK_DB_PASSWORD');
$dbPassword = $dbPassword === false ? '' : $dbPassword;

try {
    $serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $dbHost, $dbPort);
    $serverPdo = new PDO($serverDsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $serverPdo->exec(
        'CREATE DATABASE IF NOT EXISTS `student_task_planner` '
        . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );

    $databaseDsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $dbHost,
        $dbPort,
        $dbName
    );
    $pdo = new PDO($databaseDsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `STUDENT` (
            `Student_ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `Name` VARCHAR(150) NOT NULL,
            `Email` VARCHAR(254) NOT NULL,
            `Password_Hash` VARCHAR(255) NOT NULL,
            PRIMARY KEY (`Student_ID`),
            UNIQUE KEY `uq_student_email` (`Email`)
        ) ENGINE=InnoDB
          DEFAULT CHARACTER SET utf8mb4
          COLLATE utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `TASK` (
            `Task_ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `Student_ID` INT UNSIGNED NOT NULL,
            `Task_Name` VARCHAR(255) NOT NULL,
            `Description` TEXT NULL,
            `Subject` VARCHAR(150) NOT NULL,
            `Deadline` DATE NOT NULL,
            `Deadline_Time` TIME NOT NULL DEFAULT '23:59:00',
            `Priority` ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium',
            `Status` ENUM('Pending', 'In Progress', 'Completed') NOT NULL DEFAULT 'Pending',
            PRIMARY KEY (`Task_ID`),
            KEY `idx_task_student_deadline` (`Student_ID`, `Deadline`),
            KEY `idx_task_student_status_deadline` (`Student_ID`, `Status`, `Deadline`),
            KEY `idx_task_student_priority` (`Student_ID`, `Priority`),
            CONSTRAINT `fk_task_student`
                FOREIGN KEY (`Student_ID`)
                REFERENCES `STUDENT` (`Student_ID`)
                ON UPDATE CASCADE
                ON DELETE RESTRICT
        ) ENGINE=InnoDB
          DEFAULT CHARACTER SET utf8mb4
          COLLATE utf8mb4_unicode_ci"
    );

    $columnCheck = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $columnCheck->execute(['table_name' => 'TASK', 'column_name' => 'Deadline_Time']);
    if ((int) $columnCheck->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `TASK`
             ADD COLUMN `Deadline_Time` TIME NOT NULL DEFAULT '23:59:00' AFTER `Deadline`"
        );
    }

} catch (PDOException $exception) {
    error_log('Student Task Planner setup failed: ' . $exception->getMessage());
    http_response_code(500);
    $setupError = 'Database setup failed. Check the PHP error log for details.';
}

$appBaseUrl = rtrim(
    str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/student_task_planner/setup.php')),
    '/'
);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database setup - Student Task Planner</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl . '/style.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="setup-page">
<main class="setup-card">
    <a class="brand setup-brand" href="<?= htmlspecialchars($appBaseUrl . '/login.php', ENT_QUOTES, 'UTF-8') ?>">
        <span class="brand-mark" aria-hidden="true">ST</span>
        <span class="brand-copy"><strong>Studyflow</strong><small>Student Task Planner</small></span>
    </a>
    <span class="eyebrow">PROJECT CONFIGURATION</span>
    <?php if (isset($setupError)): ?>
        <div class="notice notice--error" role="alert"><span class="notice-icon" aria-hidden="true">!</span><p><?= htmlspecialchars($setupError, ENT_QUOTES, 'UTF-8') ?></p></div>
    <?php else: ?>
        <h1>Database is ready</h1>
        <p class="setup-copy">Your application database and required tables are available.</p>
        <div class="setup-details"><strong>Database</strong><span>student_task_planner</span><strong>Tables</strong><span>STUDENT, TASK</span></div>
        <div class="notice notice--success" role="status"><span class="notice-icon" aria-hidden="true">✓</span><p>Existing tables and records were left intact.</p></div>
        <a class="button button--primary" href="<?= htmlspecialchars($appBaseUrl . '/login.php', ENT_QUOTES, 'UTF-8') ?>">Continue to log in</a>
    <?php endif; ?>
</main>
</body>
</html>
