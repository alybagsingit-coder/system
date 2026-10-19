<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
redirect_if_logged_in();

$error = '';
$name = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $name = trim(form_value($_POST, 'name'));
    $email = strtolower(trim(form_value($_POST, 'email')));
    $password = form_value($_POST, 'password');
    $passwordConfirmation = form_value($_POST, 'password_confirmation');

    if ($name === '' || text_length($name) > 150) {
        $error = 'Enter a name no longer than 150 characters.';
    } elseif (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $error = 'Enter a valid email address (maximum 254 characters).';
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $error = 'Password must be between 8 and 72 bytes.';
    } elseif (!hash_equals($password, $passwordConfirmation)) {
        $error = 'The password confirmation does not match.';
    } else {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO STUDENT (Name, Email, Password_Hash) VALUES (:name, :email, :password_hash)'
            );
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            session_regenerate_id(true);
            $_SESSION['student_id'] = (int) $pdo->lastInsertId();
            $_SESSION['student_name'] = $name;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            set_flash('Your account has been created.');
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'An account with that email address already exists.';
            } else {
                error_log('Student Task Planner registration failed: ' . $exception->getMessage());
                $error = 'Registration could not be completed right now. Please try again.';
            }
        }
    }
}

render_header('Create account');
if ($error !== ''):
    ?><div class="notice notice--error" role="alert"><span class="notice-icon" aria-hidden="true">!</span><p><?= h($error) ?></p></div><?php
endif;
?>
<form class="form-stack" method="post" action="<?= h(app_url('register.php')) ?>">
    <?= csrf_field() ?>
    <label for="name">Name</label>
    <input class="form-control" id="name" name="name" type="text" maxlength="150" autocomplete="name" required value="<?= h($name) ?>">

    <label for="email">Email</label>
    <input class="form-control" id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= h($email) ?>">

    <label for="password">Password <span class="label-hint">(8–72 bytes)</span></label>
    <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>

    <label for="password_confirmation">Confirm password</label>
    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>

    <button class="button button--primary button--full" type="submit">Create account <span aria-hidden="true">→</span></button>
</form>
<p class="auth-switch">Already have an account? <a href="<?= h(app_url('login.php')) ?>">Log in</a></p>
<?php render_footer(); ?>
