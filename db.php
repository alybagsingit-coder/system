<?php
declare(strict_types=1);

$dbHost = getenv('STUDENT_TASK_DB_HOST') ?: 'localhost';
$dbPort = getenv('STUDENT_TASK_DB_PORT') ?: '3306';
$dbName = 'student_task_planner';
$dbUser = getenv('STUDENT_TASK_DB_USER') ?: 'root';
$dbPassword = getenv('STUDENT_TASK_DB_PASSWORD');
$dbPassword = $dbPassword === false ? '' : $dbPassword;

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $dbHost,
    $dbPort,
    $dbName
);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
} catch (PDOException $exception) {
    error_log('Student Task Planner database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('The database is temporarily unavailable. Please try again later.');
}
