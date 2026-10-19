<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    verifyCsrf();
    $userId = (int)($_POST['user_id'] ?? 0);
    try {
        $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ? AND role = "patient"');
        $stmt->execute([$userId]);
        $current = $stmt->fetchColumn();
        if ($current !== false) {
            $new = $current === 'active' ? 'inactive' : 'active';
            $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
            $stmt->execute([$new, $userId]);
            logAudit($pdo, currentUserId(), 'Set patient status to ' . $new, 'users', $userId);
            setFlash('success', 'Patient status updated to ' . ucfirst($new) . '.');
        }
    } catch (Throwable $e) {
        logError('toggle_patient_status', $e);
        setFlash('danger', 'Unable to update status right now. Please try again.');
    }
    redirect('patients.php');
}

$viewId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($viewId) {
    $stmt = $pdo->prepare('SELECT u.*, p.patient_id AS patient_code, p.blood_group, p.emergency_contact
                            FROM patients p JOIN users u ON p.user_id = u.id WHERE p.id = ?');
    $stmt->execute([$viewId]);
    $patient = $stmt->fetch();

    if (!$patient) {
        setFlash('danger', 'Patient not found.');
        redirect('patients.php');
    }

    $stmt = $pdo->prepare("SELECT a.*, u.name AS doctor_name FROM appointments a JOIN doctors d ON a.doctor_id=d.id JOIN users u ON d.user_id=u.id
                            WHERE a.patient_id = ? ORDER BY a.appointment_date DESC LIMIT 20");
    $stmt->execute([$viewId]);
    $appts = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM bills WHERE patient_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$viewId]);
    $bills = $stmt->fetchAll();

    $pageTitle = 'Patient: ' . $patient['name'];
    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/navbar.php';
    ?>
    <a href="patients.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header">Patient Details</div>
          <div class="card-body">
            <h5 class="fw-bold"><?= esc($patient['name']) ?></h5>
            <p class="text-muted mb-1">ID: <?= esc($patient['patient_code']) ?></p>
            <p class="mb-1"><i class="fa-solid fa-phone me-2"></i><?= esc($patient['phone']) ?></p>
            <p class="mb-1"><i class="fa-regular fa-envelope me-2"></i><?= esc($patient['email']) ?></p>
            <p class="mb-1"><i class="fa-solid fa-droplet me-2"></i><?= esc($patient['blood_group'] ?: '-') ?></p>
            <p class="mb-1"><span class="badge <?= statusBadgeClass($patient['status']) ?>"><?= esc(ucfirst($patient['status'])) ?></span></p>
            <form method="POST" class="mt-2" data-confirm="Change this patient's account status?">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="user_id" value="<?= $patient['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-<?= $patient['status'] === 'active' ? 'danger' : 'success' ?>">
                <?= $patient['status'] === 'active' ? 'Deactivate' : 'Activate' ?> Account
              </button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="card mb-3">
          <div class="card-header">Appointments</div>
          <div class="card-body p-0">
            <div class="table-responsive"><table class="table table-sm mb-0">
              <thead><tr><th>Date</th><th>Doctor</th><th>Status</th></tr></thead>
              <tbody><?php foreach ($appts as $a): ?>
                <tr><td><?= formatDate($a['appointment_date']) ?></td><td>Dr. <?= esc($a['doctor_name']) ?></td><td><span class="badge <?= statusBadgeClass($a['status']) ?>"><?= esc($a['status']) ?></span></td></tr>
              <?php endforeach; if (!$appts): ?><tr><td colspan="3" class="text-muted text-center py-3">No appointments.</td></tr><?php endif; ?></tbody>
            </table></div>
          </div>
        </div>
        <div class="card">
          <div class="card-header">Bills</div>
          <div class="card-body p-0">
            <div class="table-responsive"><table class="table table-sm mb-0">
              <thead><tr><th>Bill ID</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
              <tbody><?php foreach ($bills as $b): ?>
                <tr><td>#<?= str_pad((string)$b['id'],5,'0',STR_PAD_LEFT) ?></td><td><?= formatMoney((float)$b['total_amount']) ?></td><td><span class="badge <?= statusBadgeClass($b['status']) ?>"><?= esc($b['status']) ?></span></td><td><?= formatDate($b['created_at']) ?></td></tr>
              <?php endforeach; if (!$bills): ?><tr><td colspan="4" class="text-muted text-center py-3">No bills.</td></tr><?php endif; ?></tbody>
            </table></div>
          </div>
        </div>
      </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$search = clean($_GET['search'] ?? '');
$sql = "SELECT u.*, p.id AS pid, p.patient_id AS patient_code, p.blood_group FROM patients p JOIN users u ON p.user_id = u.id WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (u.name LIKE ? OR p.patient_id LIKE ? OR u.email LIKE ?)';
    $params = array_fill(0, 3, '%' . $search . '%');
}
$sql .= ' ORDER BY u.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$pageTitle = 'Manage Patients';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-4"><i class="fa-solid fa-users me-2"></i>Manage Patients</h4>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex gap-2">
      <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name, ID, or email..." value="<?= esc($search) ?>">
      <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (!$patients): ?>
      <div class="empty-state"><i class="fa-solid fa-users"></i>No patients found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead><tr><th>Patient ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($patients as $p): ?>
            <tr>
              <td><?= esc($p['patient_code']) ?></td>
              <td><?= esc($p['name']) ?></td>
              <td><?= esc($p['email']) ?></td>
              <td><?= esc($p['phone']) ?></td>
              <td><span class="badge <?= statusBadgeClass($p['status']) ?>"><?= esc(ucfirst($p['status'])) ?></span></td>
              <td><a href="?id=<?= $p['pid'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
