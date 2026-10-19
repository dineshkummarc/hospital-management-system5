<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo = getDB();
$errors = [];

// ---- Add bed ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verifyCsrf();
    $wardId = (int)($_POST['ward_id'] ?? 0);
    $bedNumber = clean($_POST['bed_number'] ?? '');

    if ($wardId <= 0 || $bedNumber === '') {
        $errors[] = 'Please choose a ward and enter a bed number.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO beds (ward_id, bed_number, status) VALUES (?, ?, "Available")');
            $stmt->execute([$wardId, $bedNumber]);
            logAudit($pdo, currentUserId(), 'Added bed', 'beds', (int)$pdo->lastInsertId());
            setFlash('success', 'Bed added successfully.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'That bed number already exists in this ward.';
            } else {
                logError('add_bed', $e);
                $errors[] = 'Unable to add bed right now. Please try again.';
            }
        }
        if (!$errors) redirect('beds.php');
    }
}

// ---- Assign bed to patient (prevents double assignment) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign') {
    verifyCsrf();
    $bedId = (int)($_POST['bed_id'] ?? 0);
    $patientId = (int)($_POST['patient_id'] ?? 0);

    try {
        $stmt = $pdo->prepare('SELECT * FROM beds WHERE id = ?');
        $stmt->execute([$bedId]);
        $bed = $stmt->fetch();

        if (!$bed) {
            setFlash('danger', 'Bed not found.');
        } elseif ($bed['status'] === 'Occupied') {
            setFlash('danger', 'Bed is already occupied.');
        } elseif ($patientId <= 0) {
            setFlash('danger', 'Please select a patient.');
        } else {
            $stmt = $pdo->prepare('UPDATE beds SET status = "Occupied", patient_id = ?, assigned_at = NOW(), released_at = NULL WHERE id = ? AND status = "Available"');
            $stmt->execute([$patientId, $bedId]);

            if ($stmt->rowCount() === 0) {
                // Someone else assigned it in the meantime (race condition guard).
                setFlash('danger', 'Bed is already occupied.');
            } else {
                logAudit($pdo, currentUserId(), 'Assigned bed', 'beds', $bedId);
                $stmt = $pdo->prepare('SELECT user_id FROM patients WHERE id = ?');
                $stmt->execute([$patientId]);
                $patientUserId = $stmt->fetchColumn();
                if ($patientUserId) {
                    createNotification($pdo, (int)$patientUserId, 'Bed Assigned', 'You have been assigned bed ' . $bed['bed_number'] . '.', 'ward');
                }
                setFlash('success', 'Bed assigned successfully.');
            }
        }
    } catch (Throwable $e) {
        logError('assign_bed', $e);
        setFlash('danger', 'Unable to assign bed right now. Please try again.');
    }
    redirect('beds.php');
}

// ---- Release bed ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'release') {
    verifyCsrf();
    $bedId = (int)($_POST['bed_id'] ?? 0);
    try {
        $stmt = $pdo->prepare('UPDATE beds SET status = "Available", patient_id = NULL, released_at = NOW() WHERE id = ? AND status = "Occupied"');
        $stmt->execute([$bedId]);
        if ($stmt->rowCount() > 0) {
            logAudit($pdo, currentUserId(), 'Released bed', 'beds', $bedId);
            setFlash('success', 'Bed released and marked available.');
        } else {
            setFlash('warning', 'Bed was not occupied.');
        }
    } catch (Throwable $e) {
        logError('release_bed', $e);
        setFlash('danger', 'Unable to release bed right now. Please try again.');
    }
    redirect('beds.php');
}

// ---- List ----
$wardFilter = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : null;
$statusFilter = clean($_GET['status'] ?? '');

$sql = "SELECT b.*, w.ward_name, u.name AS patient_name, p.patient_id AS patient_code
        FROM beds b JOIN wards w ON b.ward_id = w.id
        LEFT JOIN patients p ON b.patient_id = p.id LEFT JOIN users u ON p.user_id = u.id
        WHERE 1=1";
$params = [];
if ($wardFilter) { $sql .= ' AND b.ward_id = ?'; $params[] = $wardFilter; }
if ($statusFilter !== '') { $sql .= ' AND b.status = ?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY w.ward_name, b.bed_number';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$beds = $stmt->fetchAll();

$wards = $pdo->query("SELECT * FROM wards WHERE status='active' ORDER BY ward_name")->fetchAll();
$patients = $pdo->query("SELECT p.id, p.patient_id, u.name FROM patients p JOIN users u ON p.user_id=u.id ORDER BY u.name")->fetchAll();

$pageTitle = 'Manage Beds';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="fa-solid fa-bed me-2"></i>Manage Beds</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBedModal"><i class="fa-solid fa-plus me-1"></i>Add Bed</button>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= esc($err) ?></div><?php endforeach; ?>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex gap-2">
      <select name="ward_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <option value="">All Wards</option>
        <?php foreach ($wards as $w): ?><option value="<?= $w['id'] ?>" <?= $wardFilter === (int)$w['id'] ? 'selected' : '' ?>><?= esc($w['ward_name']) ?></option><?php endforeach; ?>
      </select>
      <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="Available" <?= $statusFilter === 'Available' ? 'selected' : '' ?>>Available</option>
        <option value="Occupied" <?= $statusFilter === 'Occupied' ? 'selected' : '' ?>>Occupied</option>
      </select>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (!$beds): ?>
      <div class="empty-state"><i class="fa-solid fa-bed"></i>No beds found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead><tr><th>Ward</th><th>Bed No.</th><th>Status</th><th>Patient</th><th>Assigned</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($beds as $b): ?>
            <tr>
              <td><?= esc($b['ward_name']) ?></td>
              <td><?= esc($b['bed_number']) ?></td>
              <td><span class="badge <?= statusBadgeClass($b['status']) ?>"><?= esc($b['status']) ?></span></td>
              <td><?= $b['patient_name'] ? esc($b['patient_name']) . ' (' . esc($b['patient_code']) . ')' : '-' ?></td>
              <td class="small text-muted"><?= $b['assigned_at'] ? formatDateTime($b['assigned_at']) : '-' ?></td>
              <td class="text-nowrap">
                <?php if ($b['status'] === 'Available'): ?>
                  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal" onclick="openAssign(<?= $b['id'] ?>, '<?= esc($b['ward_name']) ?> - <?= esc($b['bed_number']) ?>')">Assign</button>
                <?php else: ?>
                  <form method="POST" class="d-inline" data-confirm="Release this bed?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="release">
                    <input type="hidden" name="bed_id" value="<?= $b['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Release</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Bed Modal -->
<div class="modal fade" id="addBedModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" data-loading>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add Bed</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Ward</label>
            <select name="ward_id" class="form-select" required>
              <?php foreach ($wards as $w): ?><option value="<?= $w['id'] ?>"><?= esc($w['ward_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Bed Number</label><input type="text" name="bed_number" class="form-control" required placeholder="e.g. A-04"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Bed</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Assign Bed Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" data-loading>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="assign">
        <input type="hidden" name="bed_id" id="assignBedId">
        <div class="modal-header"><h5 class="modal-title">Assign Bed: <span id="assignBedLabel"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <label class="form-label">Patient</label>
          <select name="patient_id" class="form-select" required>
            <option value="">Select a patient</option>
            <?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>"><?= esc($p['name']) ?> (<?= esc($p['patient_id']) ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Assign</button></div>
      </form>
    </div>
  </div>
</div>

<script>
function openAssign(bedId, label) {
  document.getElementById('assignBedId').value = bedId;
  document.getElementById('assignBedLabel').textContent = label;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
