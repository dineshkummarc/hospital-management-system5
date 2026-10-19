<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('doctor');

$pdo = getDB();
$errors = [];

$stmt = $pdo->prepare('SELECT u.*, d.specialization, d.qualification, d.consultation_fee, d.availability FROM users u
                        JOIN doctors d ON d.user_id = u.id WHERE u.id = ?');
$stmt->execute([currentUserId()]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = clean($_POST['name'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $address = clean($_POST['address'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if (!preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) $errors[] = 'A valid phone number is required.';

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?');
            $stmt->execute([$name, $phone, $address, currentUserId()]);
            $_SESSION['name'] = $name;
            logAudit($pdo, currentUserId(), 'Updated profile', 'users', currentUserId());
            setFlash('success', 'Profile updated successfully.');
            redirect('profile.php');
        } catch (Throwable $e) {
            logError('update_doctor_profile', $e);
            $errors[] = 'Unable to update profile right now. Please try again.';
        }
    }
}

$pageTitle = 'My Profile';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-4"><i class="fa-solid fa-user me-2"></i>My Profile</h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger"><?= esc($err) ?></div>
<?php endforeach; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">Personal Information</div>
      <div class="card-body">
        <form method="POST" data-loading>
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" required value="<?= esc($user['name']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" value="<?= esc($user['email']) ?>" disabled>
          </div>
          <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" required value="<?= esc($user['phone']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="2"><?= esc($user['address']) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">Professional Details</div>
      <div class="card-body">
        <p class="text-muted small mb-3"><i class="fa-solid fa-circle-info me-1"></i>These are managed by the hospital administrator.</p>
        <table class="table table-sm">
          <tr><th>Specialization</th><td><?= esc($user['specialization']) ?></td></tr>
          <tr><th>Qualification</th><td><?= esc($user['qualification'] ?: '-') ?></td></tr>
          <tr><th>Consultation Fee</th><td><?= formatMoney((float)$user['consultation_fee']) ?></td></tr>
          <tr><th>Availability</th><td><?= esc($user['availability'] ?: '-') ?></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
