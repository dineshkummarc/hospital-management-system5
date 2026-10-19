<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect(roleHomePage(currentRole()));
}

$errors = [];
$old = $_POST ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name = clean($_POST['name'] ?? '');
    $dob = clean($_POST['date_of_birth'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $address = clean($_POST['address'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    // ---- Validation ----
    if ($name === '') $errors[] = 'Full name is required.';
    if ($dob === '') $errors[] = 'Date of birth is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (!preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) $errors[] = 'A valid phone number is required.';
    if ($address === '') $errors[] = 'Address is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Password and confirm password do not match.';

    if (!$errors) {
        try {
            $pdo = getDB();

            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists. Please log in instead.';
            }
        } catch (Throwable $e) {
            logError('register_check_duplicate', $e);
            $errors[] = 'Unable to process your request right now. Please try again shortly.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, phone, address, date_of_birth, status)
                                    VALUES (?, ?, ?, "patient", ?, ?, ?, "active")');
            $stmt->execute([$name, $email, $hash, $phone, $address, $dob]);
            $userId = (int)$pdo->lastInsertId();

            $patientId = generatePatientId($pdo);
            $stmt = $pdo->prepare('INSERT INTO patients (user_id, patient_id) VALUES (?, ?)');
            $stmt->execute([$userId, $patientId]);

            logAudit($pdo, $userId, 'Patient self-registered', 'users', $userId);
            createNotification($pdo, $userId, 'Welcome to HMS', 'Your account has been created. Your Patient ID is ' . $patientId . '.', 'account');

            $pdo->commit();

            setFlash('success', 'Registration successful! Your Patient ID is ' . $patientId . '. Please log in.');
            redirect('/hms/login.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            logError('register_insert', $e);
            $errors[] = 'Unable to complete registration right now. Please try again shortly.';
        }
    }
}

$pageTitle = 'Register';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card" style="max-width: 560px;">
    <div class="text-center mb-4">
      <i class="fa-solid fa-user-plus fa-2x" style="color: var(--hms-primary);"></i>
      <h3 class="mt-2 mb-0 fw-bold">Create Patient Account</h3>
      <p class="text-muted">Register to book appointments and manage your care</p>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger py-2"><?= esc($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" data-loading>
      <?= csrfField() ?>
      <div class="row">
        <div class="col-md-8 mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="name" class="form-control" required value="<?= esc($old['name'] ?? '') ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Date of Birth</label>
          <input type="date" name="date_of_birth" class="form-control" required value="<?= esc($old['date_of_birth'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required value="<?= esc($old['email'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control" required placeholder="e.g. 9876543210" value="<?= esc($old['phone'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="2" required><?= esc($old['address'] ?? '') ?></textarea>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required minlength="6">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" required minlength="6">
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100 py-2">Create Account</button>
    </form>

    <p class="text-center mt-4 mb-0 text-muted">
      Already have an account? <a href="/hms/login.php">Sign in</a>
    </p>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
