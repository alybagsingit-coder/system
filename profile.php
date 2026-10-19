<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$studentId = current_student_id();

$error = '';
$name = '';
$email = '';
$currentPassword = '';
$newPassword = '';
$passwordConfirmation = '';

try {
    $studentStatement = $pdo->prepare(
        'SELECT Name, Email, Password_Hash FROM STUDENT WHERE Student_ID = :student_id'
    );
    $studentStatement->execute(['student_id' => $studentId]);
    $student = $studentStatement->fetch();
} catch (PDOException $exception) {
    error_log('Student Task Planner profile lookup failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Your profile could not be loaded. Please try again later.');
}

if (!$student) {
    http_response_code(404);
    exit('Student account not found.');
}

$name = $student['Name'];
$email = $student['Email'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post();
    verify_csrf();

    $name = trim(form_value($_POST, 'name'));
    $email = strtolower(trim(form_value($_POST, 'email')));
    $currentPassword = form_value($_POST, 'current_password');
    $newPassword = form_value($_POST, 'new_password');
    $passwordConfirmation = form_value($_POST, 'password_confirmation');

    if ($name === '' || text_length($name) > 150) {
        $error = 'Enter a name no longer than 150 characters.';
    } elseif (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $error = 'Enter a valid email address (maximum 254 characters).';
    } elseif ($newPassword !== '' && (strlen($newPassword) < 8 || strlen($newPassword) > 72)) {
        $error = 'New password must be between 8 and 72 bytes.';
    } elseif ($newPassword !== '' && !hash_equals($newPassword, $passwordConfirmation)) {
        $error = 'The new password confirmation does not match.';
    } elseif ($newPassword === '' && $passwordConfirmation !== '') {
        $error = 'Enter a new password before confirming it.';
    } elseif ($newPassword !== '' && !password_verify($currentPassword, $student['Password_Hash'])) {
        $error = 'Your current password is incorrect.';
    } else {
        try {
            if ($newPassword === '') {
                $updateStatement = $pdo->prepare(
                    'UPDATE STUDENT SET Name = :name, Email = :email WHERE Student_ID = :student_id'
                );
                $parameters = [
                    'name' => $name,
                    'email' => $email,
                    'student_id' => $studentId,
                ];
            } else {
                $updateStatement = $pdo->prepare(
                    'UPDATE STUDENT
                     SET Name = :name, Email = :email, Password_Hash = :password_hash
                     WHERE Student_ID = :student_id'
                );
                $parameters = [
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'student_id' => $studentId,
                ];
            }
            $updateStatement->execute($parameters);

            $_SESSION['student_name'] = $name;
            set_flash('Your profile has been updated.');
            header('Location: profile.php');
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'An account with that email address already exists.';
            } else {
                error_log('Student Task Planner profile update failed: ' . $exception->getMessage());
                $error = 'Your profile could not be updated right now. Please try again.';
            }
        }
    }
}

render_header('My profile');
render_flash(take_flash());
if ($error !== ''):
    ?><div class="notice notice--error" role="alert"><span class="notice-icon" aria-hidden="true">!</span><p><?= h($error) ?></p></div><?php
endif;
?>
<section class="content-panel form-panel">
    <div class="section-heading"><div><span class="eyebrow">ACCOUNT SETTINGS</span><h2>Personal information</h2><p class="section-subtitle">Update the details associated with your student account.</p></div></div>
    <form class="task-form" method="post" action="<?= h(app_url('profile.php')) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field field--wide">
                <label for="name">Name</label>
                <input class="form-control" id="name" name="name" type="text" maxlength="150" autocomplete="name" required value="<?= h($name) ?>">
            </div>
            <div class="field field--wide">
                <label for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= h($email) ?>">
            </div>
        </div>

        <div class="section-heading profile-password-heading"><div><span class="eyebrow">OPTIONAL</span><h2>Change password</h2><p class="section-subtitle">Leave these fields blank if you do not want to change your password.</p></div></div>
        <div class="form-grid">
            <div class="field field--wide">
                <label for="current_password">Current password</label>
                <input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password">
            </div>
            <div class="field">
                <label for="new_password">New password <span class="label-hint">(8–72 bytes)</span></label>
                <input class="form-control" id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="8" maxlength="72">
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72">
            </div>
        </div>
        <div class="form-actions">
            <a class="button button--quiet" href="<?= h(app_url('dashboard.php')) ?>">Cancel</a>
            <button class="button button--primary" type="submit">Save changes</button>
        </div>
    </form>
</section>
<?php render_footer(); ?>
