<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('patient');

$pdo = getDB();
$patient = currentPatientRecord($pdo);
if (!$patient) {
    setFlash('danger', 'Patient profile not found.');
    redirect('/hms/logout.php');
}
$patientId = $patient['id'];

// ---- Stats (all real queries, nothing hard-coded) ----
$stmt = $pdo->prepare("SELECT COUNT(*) c FROM appointments WHERE patient_id = ?");
$stmt->execute([$patientId]);
$totalAppointments = (int)$stmt->fetch()['c'];

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM bills WHERE patient_id = ? AND status IN ('Unpaid','Pending','Failed')");
$stmt->execute([$patientId]);
$outstandingBillsCount = (int)$stmt->fetch()['c'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) s FROM bills WHERE patient_id = ? AND status IN ('Unpaid','Pending','Failed')");
$stmt->execute([$patientId]);
$outstandingAmount = (float)$stmt->fetch()['s'];

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM medical_records WHERE patient_id = ?");
$stmt->execute([$patientId]);
$totalRecords = (int)$stmt->fetch()['c'];

// ---- Upcoming appointment ----
$stmt = $pdo->prepare("SELECT a.*, u.name AS doctor_name, d.specialization
                        FROM appointments a
                        JOIN doctors d ON a.doctor_id = d.id
                        JOIN users u ON d.user_id = u.id
                        WHERE a.patient_id = ? AND a.status IN ('Scheduled','Confirmed','Rescheduled')
                        AND (a.appointment_date > CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time >= CURTIME()))
                        ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 1");
$stmt->execute([$patientId]);
$upcoming = $stmt->fetch();

// ---- Recent medical records ----
$stmt = $pdo->prepare("SELECT mr.*, u.name AS doctor_name FROM medical_records mr
                        JOIN doctors d ON mr.doctor_id = d.id JOIN users u ON d.user_id = u.id
                        WHERE mr.patient_id = ? ORDER BY mr.created_at DESC LIMIT 5");
$stmt->execute([$patientId]);
$recentRecords = $stmt->fetchAll();

$pageTitle = 'Patient Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-1">Welcome, <?= esc($_SESSION['name']) ?></h4>
<p class="text-muted mb-4">Patient ID: <strong><?= esc($patient['patient_id']) ?></strong></p>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
      <div><div class="stat-value"><?= $totalAppointments ?></div><div class="stat-label">Appointments</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
      <div><div class="stat-value"><?= $outstandingBillsCount ?></div><div class="stat-label">Outstanding Bills</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
      <div><div class="stat-value"><?= formatMoney($outstandingAmount) ?></div><div class="stat-label">Amount Due</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-file-medical"></i></div>
      <div><div class="stat-value"><?= $totalRecords ?></div><div class="stat-label">Medical Records</div></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="fa-solid fa-calendar-day me-2"></i>Upcoming Appointment</div>
      <div class="card-body">
        <?php if ($upcoming): ?>
          <h6 class="fw-bold mb-1">Dr. <?= esc($upcoming['doctor_name']) ?></h6>
          <p class="text-muted mb-2"><?= esc($upcoming['specialization']) ?></p>
          <p class="mb-1"><i class="fa-regular fa-calendar me-2"></i><?= formatDate($upcoming['appointment_date']) ?> at <?= formatTime($upcoming['appointment_time']) ?></p>
          <span class="badge <?= statusBadgeClass($upcoming['status']) ?>"><?= esc($upcoming['status']) ?></span>
          <div class="mt-3">
            <a href="appointments.php" class="btn btn-sm btn-outline-primary">Manage Appointments</a>
          </div>
        <?php else: ?>
          <div class="empty-state py-3">
            <i class="fa-regular fa-calendar-xmark"></i>
            No upcoming appointments.
            <div class="mt-2"><a href="book_appointment.php" class="btn btn-sm btn-primary">Book One Now</a></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="fa-solid fa-file-medical me-2"></i>Recent Medical Records</div>
      <div class="card-body">
        <?php if ($recentRecords): ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($recentRecords as $r): ?>
              <li class="list-group-item px-0">
                <div class="d-flex justify-content-between">
                  <strong>Dr. <?= esc($r['doctor_name']) ?></strong>
                  <span class="text-muted small"><?= formatDate($r['created_at']) ?></span>
                </div>
                <div class="text-muted small"><?= esc($r['diagnosis']) ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
          <a href="medical_records.php" class="btn btn-sm btn-outline-primary mt-3">View All Records</a>
        <?php else: ?>
          <div class="empty-state py-3">
            <i class="fa-regular fa-file-lines"></i>
            No medical records yet.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
