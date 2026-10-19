<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('doctor');

$pdo = getDB();
$doctor = currentDoctorRecord($pdo);

$viewPatientId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($viewPatientId) {
    // Access control: a doctor may only view a patient they have (or have had)
    // an appointment with -- never an arbitrary patient id from the URL.
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM appointments WHERE doctor_id = ? AND patient_id = ?');
    $stmt->execute([$doctor['id'], $viewPatientId]);
    if ((int)$stmt->fetch()['c'] === 0) {
        setFlash('danger', 'You do not have access to that patient record.');
        redirect('patients.php');
    }

    $stmt = $pdo->prepare('SELECT u.*, p.patient_id AS patient_code, p.blood_group, p.emergency_contact
                            FROM patients p JOIN users u ON p.user_id = u.id WHERE p.id = ?');
    $stmt->execute([$viewPatientId]);
    $patientDetail = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE patient_id = ? AND doctor_id = ? ORDER BY appointment_date DESC");
    $stmt->execute([$viewPatientId, $doctor['id']]);
    $patientAppointments = $stmt->fetchAll();

    // Full history across ALL doctors -- SRS requires doctors to see a
    // patient's history for an assigned appointment, which includes prior
    // diagnoses from other doctors for continuity of care.
    $stmt = $pdo->prepare("SELECT mr.*, u.name AS doctor_name FROM medical_records mr
                            JOIN doctors d ON mr.doctor_id = d.id JOIN users u ON d.user_id = u.id
                            WHERE mr.patient_id = ? ORDER BY mr.created_at DESC");
    $stmt->execute([$viewPatientId]);
    $records = $stmt->fetchAll();

    $pageTitle = 'Patient: ' . $patientDetail['name'];
    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/navbar.php';
    ?>
    <a href="patients.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="fa-solid fa-arrow-left me-1"></i>Back to Patients</a>

    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header"><i class="fa-solid fa-id-card me-2"></i>Patient Details</div>
          <div class="card-body">
            <h5 class="fw-bold"><?= esc($patientDetail['name']) ?></h5>
            <p class="text-muted mb-1">Patient ID: <?= esc($patientDetail['patient_code']) ?></p>
            <p class="mb-1"><i class="fa-solid fa-phone me-2"></i><?= esc($patientDetail['phone']) ?></p>
            <p class="mb-1"><i class="fa-regular fa-envelope me-2"></i><?= esc($patientDetail['email']) ?></p>
            <p class="mb-1"><i class="fa-solid fa-droplet me-2"></i>Blood Group: <?= esc($patientDetail['blood_group'] ?: '-') ?></p>
            <p class="mb-1"><i class="fa-solid fa-cake-candles me-2"></i>DOB: <?= formatDate($patientDetail['date_of_birth']) ?></p>
            <p class="mb-0"><i class="fa-solid fa-user-shield me-2"></i>Emergency: <?= esc($patientDetail['emergency_contact'] ?: '-') ?></p>
          </div>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="card mb-3">
          <div class="card-header"><i class="fa-solid fa-calendar-check me-2"></i>Appointments with You</div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>Time</th><th>Status</th><th>Reason</th></tr></thead>
                <tbody>
                <?php foreach ($patientAppointments as $a): ?>
                  <tr>
                    <td><?= formatDate($a['appointment_date']) ?></td>
                    <td><?= formatTime($a['appointment_time']) ?></td>
                    <td><span class="badge <?= statusBadgeClass($a['status']) ?>"><?= esc($a['status']) ?></span></td>
                    <td class="small text-muted"><?= esc($a['reason'] ?: '-') ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-file-medical me-2"></i>Medical History</span>
            <a href="medical_records.php?patient_id=<?= $viewPatientId ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus me-1"></i>Add Record</a>
          </div>
          <div class="card-body">
            <?php if (!$records): ?>
              <div class="empty-state py-3"><i class="fa-regular fa-file-lines"></i>No medical records yet.</div>
            <?php else: ?>
              <?php foreach ($records as $r): ?>
                <div class="border-bottom pb-2 mb-2">
                  <div class="d-flex justify-content-between">
                    <strong>Dr. <?= esc($r['doctor_name']) ?></strong>
                    <span class="text-muted small"><?= formatDate($r['created_at']) ?></span>
                  </div>
                  <div class="small"><?= esc($r['diagnosis']) ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// ---- Patient list (distinct patients this doctor has seen) ----
$search = clean($_GET['search'] ?? '');
$sql = "SELECT DISTINCT p.id, p.patient_id, u.name, u.phone, u.email
        FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN users u ON p.user_id = u.id
        WHERE a.doctor_id = ?";
$params = [$doctor['id']];
if ($search !== '') {
    $sql .= ' AND (u.name LIKE ? OR p.patient_id LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY u.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$pageTitle = 'My Patients';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-4"><i class="fa-solid fa-users me-2"></i>My Patients</h4>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex gap-2">
      <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name or patient ID..." value="<?= esc($search) ?>">
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
          <thead><tr><th>Patient ID</th><th>Name</th><th>Phone</th><th>Email</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($patients as $p): ?>
            <tr>
              <td><?= esc($p['patient_id']) ?></td>
              <td><?= esc($p['name']) ?></td>
              <td><?= esc($p['phone']) ?></td>
              <td><?= esc($p['email']) ?></td>
              <td><a href="?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
