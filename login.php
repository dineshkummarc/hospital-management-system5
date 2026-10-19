<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect(roleHomePage(currentRole()));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email = clean($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    }

    if (!$errors) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Invalid email or password.';
                logAudit($pdo, null, 'Failed login attempt', 'users', null);
            } elseif ($user['status'] !== 'active') {
                $errors[] = 'This account has been deactivated. Please contact the administrator.';
            } else {
                // Regenerate session id on privilege change to prevent session fixation.
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];

                logAudit($pdo, (int)$user['id'], 'Login', 'users', (int)$user['id']);
                setFlash('success', 'Welcome back, ' . $user['name'] . '!');
                redirect(roleHomePage($user['role']));
            }
        } catch (Throwable $e) {
            logError('login', $e);
            $errors[] = 'Unable to process your request right now. Please try again shortly.';
        }
    }
}

$pageTitle = 'Login';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="text-center mb-4">
      <i class="fa-solid fa-hospital-user fa-2x" style="color: var(--hms-primary);"></i>
      <h3 class="mt-2 mb-0 fw-bold">Hospital Management System</h3>
      <p class="text-muted">Sign in to continue</p>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger py-2"><?= esc($err) ?></div>
    <?php endforeach; ?>

    <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert alert-<?= esc($flash['type']) ?> py-2"><?= esc($flash['message']) ?></div>
    <?php endif; ?>

    <form method="POST" data-loading>
      <?= csrfField() ?>
      <div class="mb-3">
        <label class="form-label">Email address</label>
        <input type="email" name="email" class="form-control" required autofocus value="<?= esc($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100 py-2">Sign In</button>
    </form>

    <p class="text-center mt-4 mb-0 text-muted">
      New patient? <a href="/hms/register.php">Create an account</a>
    </p>

    <div class="mt-4 p-3 bg-light rounded small text-muted">
      <strong>Demo credentials</strong><br>
      Admin: admin@hms.com / Admin@123<br>
      Doctor: ananya.sharma@hms.com / Doctor@123<br>
      Patient: aarav.mehta@example.com / Patient@123
    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="/hms/assets/js/main.js"></script>
</body>
</html>
