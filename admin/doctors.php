<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo = getDB();
$errors = [];

// ---- Add doctor ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verifyCsrf();
    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $specialization = clean($_POST['specialization'] ?? '');
    $qualification = clean($_POST['qualification'] ?? '');
    $fee = (float)($_POST['consultation_fee'] ?? 0);
    $availability = clean($_POST['availability'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($specialization === '') $errors[] = 'Specialization is required.';
    if ($fee <= 0) $errors[] = 'Consultation fee must be greater than zero.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = 'A user with this email already exists.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, phone, status) VALUES (?, ?, ?, "doctor", ?, "active")');
            $stmt->execute([$name, $email, $hash, $phone]);
            $userId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO doctors (user_id, specialization, qualification, consultation_fee, availability, status)
                                    VALUES (?, ?, ?, ?, ?, "active")');
            $stmt->execute([$userId, $specialization, $qualification ?: null, $fee, $availability ?: null]);

            logAudit($pdo, currentUserId(), 'Added doctor', 'doctors', (int)$pdo->lastInsertId());
            $pdo->commit();
            setFlash('success', 'Doctor added successfully.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            logError('add_doctor', $e);
            $errors[] = 'Unable to add doctor right now. Please try again.';
        }
        if (!$errors) redirect('doctors.php');
    }
}

// ---- Edit doctor ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    verifyCsrf();
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $name = clean($_POST['name'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $specialization = clean($_POST['specialization'] ?? '');
    $qualification = clean($_POST['qualification'] ?? '');
    $fee = (float)($_POST['consultation_fee'] ?? 0);
    $availability = clean($_POST['availability'] ?? '');

    if ($name === '' || $specialization === '' || $fee <= 0) {
        setFlash('danger', 'Please fill in all required fields correctly.');
    } else {
        try {
            $stmt = $pdo->prepare('SELECT user_id FROM doctors WHERE id = ?');
            $stmt->execute([$doctorId]);
            $userId = $stmt->fetchColumn();

            if ($userId) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?');
                $stmt->execute([$name, $phone, $userId]);
                $stmt = $pdo->prepare('UPDATE doctors SET specialization = ?, qualification = ?, consultation_fee = ?, availability = ? WHERE id = ?');
                $stmt->execute([$specialization, $qualification ?: null, $fee, $availability ?: null, $doctorId]);
                logAudit($pdo, currentUserId(), 'Edited doctor', 'doctors', $doctorId);
                $pdo->commit();
                setFlash('success', 'Doctor updated successfully.');
            } else {
                setFlash('danger', 'Doctor not found.');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            logError('edit_doctor', $e);
            setFlash('danger', 'Unable to update doctor right now. Please try again.');
        }
    }
    redirect('doctors.php');
}

// ---- Toggle active/inactive ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    verifyCsrf();
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    try {
        $stmt = $pdo->prepare('SELECT status FROM doctors WHERE id = ?');
        $stmt->execute([$doctorId]);
        $current = $stmt->fetchColumn();
        if ($current !== false) {
            $new = $current === 'active' ? 'inactive' : 'active';
            $stmt = $pdo->prepare('UPDATE doctors SET status = ? WHERE id = ?');
            $stmt->execute([$new, $doctorId]);
            // Status-based deactivation preserves all historical appointments/records.
            logAudit($pdo, currentUserId(), 'Set doctor status to ' . $new, 'doctors', $doctorId);
            setFlash('success', 'Doctor status updated to ' . ucfirst($new) . '.');
        }
    } catch (Throwable $e) {
        logError('toggle_doctor_status', $e);
        setFlash('danger', 'Unable to update status right now. Please try again.');
    }
    redirect('doctors.php');
}

// ---- List ----
$search = clean($_GET['search'] ?? '');
$sql = "SELECT d.*, u.name, u.email, u.phone FROM doctors d JOIN users u ON d.user_id = u.id WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (u.name LIKE ? OR d.specialization LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY u.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

$pageTitle = 'Manage Doctors';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="fa-solid fa-user-doctor me-2"></i>Manage Doctors</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDoctorModal"><i class="fa-solid fa-plus me-1"></i>Add Doctor</button>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger"><?= esc($err) ?></div>
<?php endforeach; ?>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex gap-2">
      <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name or specialization..." value="<?= esc($search) ?>">
      <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (!$doctors): ?>
      <div class="empty-state"><i class="fa-solid fa-user-doctor"></i>No doctors found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead><tr><th>Name</th><th>Specialization</th><th>Fee</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($doctors as $d): ?>
            <tr>
              <td>Dr. <?= esc($d['name']) ?></td>
              <td><?= esc($d['specialization']) ?></td>
              <td><?= formatMoney((float)$d['consultation_fee']) ?></td>
              <td><?= esc($d['phone']) ?></td>
              <td><span class="badge <?= statusBadgeClass($d['status']) ?>"><?= esc(ucfirst($d['status'])) ?></span></td>
              <td class="text-nowrap">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editDoctorModal"
                  onclick='openEditDoctor(<?= json_encode($d) ?>)'><i class="fa-solid fa-pen"></i></button>
                <form method="POST" class="d-inline" data-confirm="Change status of Dr. <?= esc($d['name']) ?>?">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="doctor_id" value="<?= $d['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-<?= $d['status'] === 'active' ? 'danger' : 'success' ?>">
                    <i class="fa-solid fa-power-off"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Doctor Modal -->
<div class="modal fade" id="addDoctorModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" data-loading>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add Doctor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-2">
          <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
          <div class="col-md-6"><label class="form-label">Temporary Password</label><input type="password" name="password" class="form-control" required minlength="6"></div>
          <div class="col-md-6"><label class="form-label">Specialization</label>
            <select name="specialization" class="form-select" required>
              <?php foreach (['Cardiology','Neurology','Dermatology','General Medicine','Orthopedics','Pediatrics','ENT','Gynecology'] as $s): ?>
                <option value="<?= $s ?>"><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6"><label class="form-label">Qualification</label><input type="text" name="qualification" class="form-control"></div>
          <div class="col-md-6"><label class="form-label">Consultation Fee (Rs.)</label><input type="number" step="0.01" min="1" name="consultation_fee" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">Availability</label><input type="text" name="availability" class="form-control" placeholder="e.g. Mon-Fri 09:00-17:00"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Doctor</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Doctor Modal -->
<div class="modal fade" id="editDoctorModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" data-loading>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="doctor_id" id="editDoctorId">
        <div class="modal-header"><h5 class="modal-title">Edit Doctor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-2">
          <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" name="name" id="editDoctorName" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="editDoctorPhone" class="form-control"></div>
          <div class="col-md-6"><label class="form-label">Specialization</label>
            <select name="specialization" id="editDoctorSpecialization" class="form-select" required>
              <?php foreach (['Cardiology','Neurology','Dermatology','General Medicine','Orthopedics','Pediatrics','ENT','Gynecology'] as $s): ?>
                <option value="<?= $s ?>"><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6"><label class="form-label">Qualification</label><input type="text" name="qualification" id="editDoctorQualification" class="form-control"></div>
          <div class="col-md-6"><label class="form-label">Consultation Fee (Rs.)</label><input type="number" step="0.01" min="1" name="consultation_fee" id="editDoctorFee" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">Availability</label><input type="text" name="availability" id="editDoctorAvailability" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditDoctor(d) {
  document.getElementById('editDoctorId').value = d.id;
  document.getElementById('editDoctorName').value = d.name;
  document.getElementById('editDoctorPhone').value = d.phone || '';
  document.getElementById('editDoctorSpecialization').value = d.specialization;
  document.getElementById('editDoctorQualification').value = d.qualification || '';
  document.getElementById('editDoctorFee').value = d.consultation_fee;
  document.getElementById('editDoctorAvailability').value = d.availability || '';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
