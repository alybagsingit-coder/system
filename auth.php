<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($basePath === '.' || $basePath === '') {
    $basePath = '';
}

define('APP_BASE_URL', $basePath);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('student_task_planner_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => APP_BASE_URL === '' ? '/' : APP_BASE_URL,
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
session_start();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $base = APP_BASE_URL === '' ? '' : APP_BASE_URL;
    return $base . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function form_value(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) ? $value : '';
}

function text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    $count = preg_match_all('/./us', $value);
    return $count === false ? strlen($value) : $count;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sentToken = $_POST['csrf_token'] ?? '';
    if (!is_string($sentToken) || !hash_equals(csrf_token(), $sentToken)) {
        http_response_code(400);
        exit('Invalid or expired form token. Reload the page and try again.');
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method not allowed.');
    }
}

function current_student_id(): int
{
    $studentId = $_SESSION['student_id'] ?? null;
    if (!is_int($studentId) || $studentId < 1) {
        header('Location: login.php');
        exit;
    }

    try {
        $statement = $GLOBALS['pdo']->prepare(
            "UPDATE TASK
             SET Status = 'Completed'
             WHERE Student_ID = :student_id
               AND TIMESTAMP(Deadline, Deadline_Time) <= CURRENT_TIMESTAMP
               AND Status IN ('Pending', 'In Progress')"
        );
        $statement->execute(['student_id' => $studentId]);
    } catch (PDOException $exception) {
        error_log('Student Task Planner overdue task update failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('Task status could not be updated. Please try again later.');
    }

    return $studentId;
}

function redirect_if_logged_in(): void
{
    if (isset($_SESSION['student_id']) && is_int($_SESSION['student_id'])) {
        header('Location: dashboard.php');
        exit;
    }
}

function set_flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function render_header(string $title): void
{
    $studentName = $_SESSION['student_name'] ?? null;
    $isAuthenticated = is_string($studentName);
    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $pageClass = $isAuthenticated ? 'app-shell' : 'app-shell app-shell--auth';
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($title) ?> - Student Task Planner</title>
        <meta name="theme-color" content="#f4f2e8">
        <link rel="stylesheet" href="<?= h(app_url('style.css')) ?>">
    </head>
    <body>
    <div class="<?= h($pageClass) ?>">
        <?php if ($isAuthenticated): ?>
            <aside class="sidebar" aria-label="Main navigation">
                <a class="brand" href="<?= h(app_url('dashboard.php')) ?>">
                    <span class="brand-mark" aria-hidden="true">ST</span>
                    <span class="brand-copy"><strong>Studyflow</strong><small>Student Task Planner</small></span>
                </a>
                <p class="sidebar-label">WORKSPACE</p>
                <nav class="sidebar-nav">
                    <a class="nav-link<?= $currentPage === 'dashboard.php' ? ' is-active' : '' ?>"
                       href="<?= h(app_url('dashboard.php')) ?>"<?= $currentPage === 'dashboard.php' ? ' aria-current="page"' : '' ?>>
                        <span class="nav-icon" aria-hidden="true">D</span><span>Dashboard</span>
                    </a>
                    <a class="nav-link<?= $currentPage === 'tasks.php' ? ' is-active' : '' ?>"
                       href="<?= h(app_url('tasks.php')) ?>"<?= $currentPage === 'tasks.php' ? ' aria-current="page"' : '' ?>>
                        <span class="nav-icon" aria-hidden="true">T</span><span>My tasks</span>
                    </a>
                    <a class="nav-link<?= $currentPage === 'history.php' ? ' is-active' : '' ?>"
                       href="<?= h(app_url('history.php')) ?>"<?= $currentPage === 'history.php' ? ' aria-current="page"' : '' ?>>
                        <span class="nav-icon" aria-hidden="true">H</span><span>History</span>
                    </a>
                    <a class="nav-link<?= $currentPage === 'profile.php' ? ' is-active' : '' ?>"
                       href="<?= h(app_url('profile.php')) ?>"<?= $currentPage === 'profile.php' ? ' aria-current="page"' : '' ?>>
                        <span class="nav-icon" aria-hidden="true">U</span><span>My profile</span>
                    </a>
                </nav>
                <div class="sidebar-bottom">
                    <span class="avatar" aria-hidden="true"><?= h(strtoupper(substr($studentName, 0, 1))) ?></span>
                    <span class="profile-copy"><strong><?= h($studentName) ?></strong><small>Student account</small></span>
                    <form action="<?= h(app_url('logout.php')) ?>" method="post" class="logout-form">
                        <?= csrf_field() ?>
                        <button class="icon-button" type="submit" aria-label="Log out" title="Log out">↗</button>
                    </form>
                </div>
            </aside>
            <div class="app-main">
                <header class="topbar">
                    <div><span class="eyebrow">STUDENT WORKSPACE</span><p class="topbar-date"><?= h(date('l, F j, Y')) ?></p></div>
                    <div class="topbar-user"><span class="avatar avatar--small" aria-hidden="true"><?= h(strtoupper(substr($studentName, 0, 1))) ?></span><span><?= h($studentName) ?></span></div>
                </header>
                <main class="page-content">
                    <div class="page-heading"><div><span class="eyebrow">YOUR ACADEMIC ORGANIZER</span><h1><?= h($title) ?></h1></div></div>
        <?php else: ?>
            <header class="auth-topbar">
                <a class="brand" href="<?= h(app_url('login.php')) ?>">
                    <span class="brand-mark" aria-hidden="true">ST</span>
                    <span class="brand-copy"><strong>Studyflow</strong><small>Student Task Planner</small></span>
                </a>
                <nav class="auth-nav" aria-label="Account navigation">
                    <a href="<?= h(app_url('login.php')) ?>"<?= $currentPage === 'login.php' ? ' aria-current="page"' : '' ?>>Log in</a>
                    <a class="button button--small" href="<?= h(app_url('register.php')) ?>">Create account</a>
                </nav>
            </header>
            <main class="auth-content">
                <section class="auth-card">
                    <div class="auth-intro"><span class="eyebrow">PLAN WITH CLARITY</span><h1><?= h($title) ?></h1><p>Keep your academic tasks, deadlines, and priorities in one calm, organized place.</p></div>
        <?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?>
        <?php if (is_string($_SESSION['student_name'] ?? null)): ?>
                </main>
            </div>
        <?php else: ?>
                    <p class="auth-footer">A simple space to stay on top of your studies.</p>
                </section>
            </main>
        <?php endif; ?>
    </div>
    </body>
    </html>
    <?php
}

function render_flash(?array $flash): void
{
    if ($flash === null || !isset($flash['message']) || !is_string($flash['message'])) {
        return;
    }

    $isError = ($flash['type'] ?? '') === 'error';
    ?>
    <div class="notice<?= $isError ? ' notice--error' : ' notice--success' ?>" role="<?= $isError ? 'alert' : 'status' ?>">
        <span class="notice-icon" aria-hidden="true"><?= $isError ? '!' : '✓' ?></span>
        <p><?= h($flash['message']) ?></p>
    </div>
    <?php
}
