<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verifyCsrf();
    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $designation = clean($_POST['designation'] ?? '');
    $department = clean($_POST['department'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($designation === '') $errors[] = 'Designation is required.';
    if ($department === '') $errors[] = 'Department is required.';

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM staff WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = 'A staff member with this email already exists.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO staff (name, email, phone, designation, department, status) VALUES (?, ?, ?, ?, ?, "active")');
            $stmt->execute([$name, $email, $phone ?: null, $designation, $department]);
            logAudit($pdo, currentUserId(), 'Added staff', 'staff', (int)$pdo->lastInsertId());
            setFlash('success', 'Staff member added successfully.');
        } catch (Throwable $e) {
            logError('add_staff', $e);
            $errors[] = 'Unable to add staff member right now. Please try again.';
        }
        if (!$errors) redirect('staff.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    verifyCsrf();
    $staffId = (int)($_POST['staff_id'] ?? 0);
    $name = clean($_POST['name'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $designation = clean($_POST['designation'] ?? '');
    $department = clean($_POST['department'] ?? '');

    if ($name === '' || $designation === '' || $department === '') {
        setFlash('danger', 'Please fill in all required fields.');
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE staff SET name=?, phone=?, designation=?, department=? WHERE id=?');
            $stmt->execute([$name, $phone ?: null, $designation, $department, $staffId]);
            logAudit($pdo, currentUserId(), 'Edited staff', 'staff', $staffId);
            setFlash('success', 'Staff member updated.');
        } catch (Throwable $e) {
            logError('edit_staff', $e);
            setFlash('danger', 'Unable to update staff member right now. Please try again.');
        }
    }
    redirect('staff.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    verifyCsrf();
    $staffId = (int)($_POST['staff_id'] ?? 0);
    try {
        $stmt = $pdo->prepare('SELECT status FROM staff WHERE id = ?');
        $stmt->execute([$staffId]);
        $current = $stmt->fetchColumn();
        if ($current !== false) {
            $new = $current === 'active' ? 'inactive' : 'active';
            $stmt = $pdo->prepare('UPDATE staff SET status = ? WHERE id = ?');
            $stmt->execute([$new, $staffId]);
            logAudit($pdo, currentUserId(), 'Set staff status to ' . $new, 'staff', $staffId);
            setFlash('success', 'Staff status updated to ' . ucfirst($new) . '.');
        }
    } catch (Throwable $e) {
        logError('toggle_staff_status', $e);
        setFlash('danger', 'Unable to update status right now. Please try again.');
    }
    redirect('staff.php');
}

// ---- List / search / filter ----
$search = clean($_GET['search'] ?? '');
$department = clean($_GET['department'] ?? '');
$designation = clean($_GET['designation'] ?? '');

$sql = "SELECT * FROM staff WHERE 1=1";
$params = [];
if ($search !== '') { $sql .= ' AND name LIKE ?'; $params[] = '%' . $search . '%'; }
if ($department !== '') { $sql .= ' AND department = ?'; $params[] = $department; }
if ($designation !== '') { $sql .= ' AND designation = ?'; $params[] = $designation; }
$sql .= ' ORDER BY name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staffList = $stmt->fetchAll();

$departments = $pdo->query("SELECT DISTINCT department FROM staff ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$designations = $pdo->query("SELECT DISTINCT designation FROM staff ORDER BY designation")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Manage Staff';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="fa-solid fa-id-badge me-2"></i>Manage Staff</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal"><i class="fa-solid fa-plus me-1"></i>Add Staff</button>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= esc($err) ?></div><?php endforeach; ?>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2">
      <div class="col-md-4"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name..." value="<?= esc($search) ?>"></div>
      <div class="col-md-3">
        <select name="department" class="form-select form-select-sm">
          <option value="">All Departments</option>
          <?php foreach ($departments as $d): ?><option value="<?= esc($d) ?>" <?= $department === $d ? 'selected' : '' ?>><?= esc($d) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select name="designation" class="form-select form-select-sm">
          <option value="">All Designations</option>
          <?php foreach ($designations as $d): ?><option value="<?= esc($d) ?>" <?= $designation === $d ? 'selected' : '' ?>><?= esc($d) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100" type="submit">Filter</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (!$staffList): ?>
      <div class="empty-state"><i class="fa-solid fa-id-badge"></i>No staff members found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead><tr><th>Name</th><th>Designation</th><th>Department</th><th>Phone</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($staffList as $s): ?>
            <tr>
              <td><?= esc($s['name']) ?></td>
              <td><?= esc($s['designation']) ?></td>
              <td><?= esc($s['department']) ?></td>
              <td><?= esc($s['phone'] ?: '-') ?></td>
              <td><?= esc($s['email']) ?></td>
              <td><span class="badge <?= statusBadgeClass($s['status']) ?>"><?= esc(ucfirst($s['status'])) ?></span></td>
              <td class="text-nowrap">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editStaffModal" onclick='openEditStaff(<?= json_encode($s) ?>)'><i class="fa-solid fa-pen"></i></button>
                <form method="POST" class="d-inline" data-confirm="Change status of <?= esc($s['name']) ?>?">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-<?= $s['status'] === 'active' ? 'danger' : 'success' ?>"><i class="fa-solid fa-power-off"></i></button>
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

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" data-loading>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add Staff</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Designation</label><input type="text" name="designation" class="form-control" required placeholder="e.g. Head Nurse"></div>
          <div class="mb-2"><label class="form-label">Department</label><input type="text" name="department" class="form-control" required placeholder="e.g. Nursing"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Staff</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal fade" id="editStaffModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" data-loading>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="staff_id" id="editStaffId">
        <div class="modal-header"><h5 class="modal-title">Edit Staff</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Name</label><input type="text" name="name" id="editStaffName" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Phone</label><input type="text" name="phone" id="editStaffPhone" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Designation</label><input type="text" name="designation" id="editStaffDesignation" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Department</label><input type="text" name="department" id="editStaffDepartment" class="form-control" required></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditStaff(s) {
  document.getElementById('editStaffId').value = s.id;
  document.getElementById('editStaffName').value = s.name;
  document.getElementById('editStaffPhone').value = s.phone || '';
  document.getElementById('editStaffDesignation').value = s.designation;
  document.getElementById('editStaffDepartment').value = s.department;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
