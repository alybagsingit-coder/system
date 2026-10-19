<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
redirect_if_logged_in();

$error = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $email = strtolower(trim(form_value($_POST, 'email')));
    $password = form_value($_POST, 'password');

    if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $password === '') {
        $error = 'Email or password is incorrect.';
    } else {
        try {
            $statement = $pdo->prepare(
                'SELECT Student_ID, Name, Password_Hash FROM STUDENT WHERE Email = :email LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $student = $statement->fetch();

            if ($student && password_verify($password, $student['Password_Hash'])) {
                session_regenerate_id(true);
                $_SESSION['student_id'] = (int) $student['Student_ID'];
                $_SESSION['student_name'] = $student['Name'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                if (password_needs_rehash($student['Password_Hash'], PASSWORD_DEFAULT)) {
                    try {
                        $update = $pdo->prepare(
                            'UPDATE STUDENT SET Password_Hash = :password_hash WHERE Student_ID = :student_id'
                        );
                        $update->execute([
                            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                            'student_id' => (int) $student['Student_ID'],
                        ]);
                    } catch (PDOException $exception) {
                        error_log('Student Task Planner password rehash failed: ' . $exception->getMessage());
                    }
                }

                header('Location: dashboard.php');
                exit;
            }

            $error = 'Email or password is incorrect.';
        } catch (PDOException $exception) {
            error_log('Student Task Planner login failed: ' . $exception->getMessage());
            $error = 'Login could not be completed right now. Please try again.';
        }
    }
}

$flash = take_flash();
render_header('Log in');
render_flash($flash);
if (isset($_GET['logged_out'])):
    ?><div class="notice notice--success" role="status"><span class="notice-icon" aria-hidden="true">✓</span><p>You have been logged out.</p></div><?php
endif;
if ($error !== ''):
    ?><div class="notice notice--error" role="alert"><span class="notice-icon" aria-hidden="true">!</span><p><?= h($error) ?></p></div><?php
endif;
?>
<form class="form-stack" method="post" action="<?= h(app_url('login.php')) ?>">
    <?= csrf_field() ?>
    <label for="email">Email</label>
    <input class="form-control" id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= h($email) ?>">

    <label for="password">Password</label>
    <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>

    <button class="button button--primary button--full" type="submit">Log in <span aria-hidden="true">→</span></button>
</form>
<p class="auth-switch">New to Studyflow? <a href="<?= h(app_url('register.php')) ?>">Create your account</a></p>
<?php render_footer(); ?>
