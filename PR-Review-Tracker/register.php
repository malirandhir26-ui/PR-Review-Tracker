<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

start_session();
if (current_user()) {
    redirect('index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $githubUsername = trim($_POST['github_username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid name and email.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $exists = db()->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        db()->prepare(
            'INSERT INTO users (name, email, role, github_username, password_hash) VALUES (?, ?, "developer", ?, ?)'
        )->execute([$name, $email, $githubUsername, password_hash($password, PASSWORD_DEFAULT)]);

        flash_set('success', 'Account created. You can now login.');
        redirect('login.php');
    }
}

$page_title = 'Register';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h3 class="text-center mb-4">Create Account</h3>

                <?php if ($errors): ?>
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 ps-3 small">
                            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= base_url('register.php') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Full name</label>
                        <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">GitHub username</label>
                        <input type="text" name="github_username" class="form-control" value="<?= e($_POST['github_username'] ?? '') ?>">
                        <div class="form-text">Used to identify your own pull requests.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>

                <p class="text-center small mt-3 mb-0">
                    Already have an account? <a href="<?= base_url('login.php') ?>">Login</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
