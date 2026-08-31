<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

start_session();
if (current_user()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        unset($_SESSION['throttle']);
        login_user((int) $user['id']);
        flash_set('success', 'Welcome back, ' . $user['name'] . '!');
        redirect('index.php');
    }
    throttle_check('login'); // count the failed attempt
    $error = 'Invalid email or password.';
}

$page_title = 'Login';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h3 class="text-center mb-1"><?= e(APP_NAME) ?></h3>
                <p class="text-center text-muted small mb-4">Sign in to monitor your pull requests</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= base_url('login.php') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>

                <p class="text-center small mt-3 mb-0">
                    No account? <a href="<?= base_url('register.php') ?>">Register here</a>
                </p>
                <hr>
                <p class="text-center small text-muted mb-0">
                    Demo admin login: <code>admin@example.com</code> / <code>admin123</code>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
