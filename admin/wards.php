<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verifyCsrf();
    $name = clean($_POST['ward_name'] ?? '');
    $type = clean($_POST['ward_type'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);

    if ($name === '') $errors[] = 'Ward name is required.';
    if ($type === '') $errors[] = 'Ward type is required.';
    if ($capacity <= 0) $errors[] = 'Capacity must be greater than zero.';

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO wards (ward_name, ward_type, capacity, status) VALUES (?, ?, ?, "active")');
            $stmt->execute([$name, $type, $capacity]);
            logAudit($pdo, currentUserId(), 'Created ward', 'wards', (int)$pdo->lastInsertId());
            setFlash('success', 'Ward created successfully.');
        } catch (Throwable $e) {
            logError('add_ward', $e);
            $errors[] = 'Unable to create ward right now. Please try again.';
        }
        if (!$errors) redirect('wards.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    verifyCsrf();
    $wardId = (int)($_POST['ward_id'] ?? 0);
    $name = clean($_POST['ward_name'] ?? '');
    $type = clean($_POST['ward_type'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);

    if ($name === '' || $type === '' || $capacity <= 0) {
        setFlash('danger', 'Please fill in all fields correctly.');
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE wards SET ward_name=?, ward_type=?, capacity=? WHERE id=?');
            $stmt->execute([$name, $type, $capacity, $wardId]);
            logAudit($pdo, currentUserId(), 'Edited ward', 'wards', $wardId);
            setFlash('success', 'Ward updated.');
        } catch (Throwable $e) {
            logError('edit_ward', $e);
            setFlash('danger', 'Unable to update ward right now. Please try again.');
        }
    }
    redirect('wards.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    verifyCsrf();
    $wardId = (int)($_POST['ward_id'] ?? 0);
    try {
        $stmt = $pdo->prepare('SELECT status FROM wards WHERE id = ?');
        $stmt->execute([$wardId]);
        $current = $stmt->fetchColumn();
        if ($current !== false) {
            $new = $current === 'active' ? 'inactive' : 'active';
            $stmt = $pdo->prepare('UPDATE wards SET status = ? WHERE id = ?');
            $stmt->execute([$new, $wardId]);
            logAudit($pdo, currentUserId(), 'Set ward status to ' . $new, 'wards', $wardId);
            setFlash('success', 'Ward status updated.');
        }
    } catch (Throwable $e) {
        logError('toggle_ward_status', $e);
        setFlash('danger', 'Unable to update status right now. Please try again.');
    }
    redirect('wards.php');
}

$wards = $pdo->query("SELECT w.*,
                       (SELECT COUNT(*) FROM beds WHERE ward_id = w.id) AS total_beds,
                       (SELECT COUNT(*) FROM beds WHERE ward_id = w.id AND status = 'Occupied') AS occupied_beds
                       FROM wards w ORDER BY w.ward_name")->fetchAll();

$pageTitle = 'Manage Wards';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="fa-solid fa-hospital me-2"></i>Manage Wards</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addWardModal"><i class="fa-solid fa-plus me-1"></i>Add Ward</button>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= esc($err) ?></div><?php endforeach; ?>

<div class="row g-3">
  <?php if (!$wards): ?>
    <div class="empty-state"><i class="fa-solid fa-hospital"></i>No wards created yet.</div>
  <?php endif; ?>
  <?php foreach ($wards as $w): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <h6 class="fw-bold"><?= esc($w['ward_name']) ?></h6>
            <span class="badge <?= statusBadgeClass($w['status']) ?>"><?= esc(ucfirst($w['status'])) ?></span>
          </div>
          <div class="text-muted small mb-2"><?= esc($w['ward_type']) ?></div>
          <div class="mb-2">
            <div class="d-flex justify-content-between small"><span>Beds Occupied</span><span><?= $w['occupied_beds'] ?> / <?= $w['total_beds'] ?> (capacity <?= $w['capacity'] ?>)</span></div>
            <div class="progress" style="height:6px;"><div class="progress-bar bg-danger" style="width: <?= $w['total_beds'] ? round($w['occupied_beds']/$w['total_beds']*100) : 0 ?>%"></div></div>
          </div>
          <div class="d-flex gap-2 mt-3">
            <button class="btn btn-sm btn-outline-primary flex-fill" data-bs-toggle="modal" data-bs-target="#editWardModal" onclick='openEditWard(<?= json_encode($w) ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
            <form method="POST" class="flex-fill" data-confirm="Change status of this ward?">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="ward_id" value="<?= $w['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-<?= $w['status'] === 'active' ? 'danger' : 'success' ?> w-100"><i class="fa-solid fa-power-off"></i></button>
            </form>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Add Ward Modal -->
<div class="modal fade" id="addWardModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" data-loading>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add Ward</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Ward Name</label><input type="text" name="ward_name" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Ward Type</label><input type="text" name="ward_type" class="form-control" required placeholder="e.g. General, ICU"></div>
          <div class="mb-2"><label class="form-label">Capacity</label><input type="number" name="capacity" min="1" class="form-control" required></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Create Ward</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Ward Modal -->
<div class="modal fade" id="editWardModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" data-loading>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="ward_id" id="editWardId">
        <div class="modal-header"><h5 class="modal-title">Edit Ward</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Ward Name</label><input type="text" name="ward_name" id="editWardName" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Ward Type</label><input type="text" name="ward_type" id="editWardType" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Capacity</label><input type="number" name="capacity" id="editWardCapacity" min="1" class="form-control" required></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditWard(w) {
  document.getElementById('editWardId').value = w.id;
  document.getElementById('editWardName').value = w.ward_name;
  document.getElementById('editWardType').value = w.ward_type;
  document.getElementById('editWardCapacity').value = w.capacity;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
