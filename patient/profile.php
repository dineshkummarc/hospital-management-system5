<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('patient');

$pdo = getDB();
$errors = [];

$stmt = $pdo->prepare('SELECT u.*, p.patient_id, p.emergency_contact, p.blood_group FROM users u
                        JOIN patients p ON p.user_id = u.id WHERE u.id = ?');
$stmt->execute([currentUserId()]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name = clean($_POST['name'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $address = clean($_POST['address'] ?? '');
    $emergencyContact = clean($_POST['emergency_contact'] ?? '');
    $bloodGroup = clean($_POST['blood_group'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if (!preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) $errors[] = 'A valid phone number is required.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?');
            $stmt->execute([$name, $phone, $address, currentUserId()]);

            $stmt = $pdo->prepare('UPDATE patients SET emergency_contact = ?, blood_group = ? WHERE user_id = ?');
            $stmt->execute([$emergencyContact ?: null, $bloodGroup ?: null, currentUserId()]);

            $pdo->commit();
            $_SESSION['name'] = $name;
            logAudit($pdo, currentUserId(), 'Updated profile', 'users', currentUserId());
            setFlash('success', 'Profile updated successfully.');
            redirect('profile.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            logError('update_patient_profile', $e);
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

<div class="card" style="max-width: 640px;">
  <div class="card-body">
    <div class="mb-3 text-muted small">Patient ID: <strong><?= esc($user['patient_id']) ?></strong> &bull; Email: <?= esc($user['email']) ?> (cannot be changed)</div>
    <form method="POST" data-loading>
      <?= csrfField() ?>
      <div class="mb-3">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" class="form-control" required value="<?= esc($user['name']) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" required value="<?= esc($user['phone']) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="2"><?= esc($user['address']) ?></textarea>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Emergency Contact</label>
          <input type="text" name="emergency_contact" class="form-control" value="<?= esc($user['emergency_contact']) ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Blood Group</label>
          <select name="blood_group" class="form-select">
            <option value="">Select</option>
            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
              <option value="<?= $bg ?>" <?= $user['blood_group'] === $bg ? 'selected' : '' ?>><?= $bg ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
