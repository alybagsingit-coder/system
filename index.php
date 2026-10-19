<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (isset($_SESSION['student_id']) && is_int($_SESSION['student_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
