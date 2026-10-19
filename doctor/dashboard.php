<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('doctor');

$pdo = getDB();
$doctor = currentDoctorRecord($pdo);
if (!$doctor) {
    setFlash('danger', 'Doctor profile not found.');
    redirect('/hms/logout.php');
}
$doctorId = $doctor['id'];

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE() AND status NOT IN ('Cancelled')");
$stmt->execute([$doctorId]);
$todayCount = (int)$stmt->fetch()['c'];

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM appointments WHERE doctor_id = ? AND appointment_date > CURDATE() AND status IN ('Scheduled','Confirmed','Rescheduled')");
$stmt->execute([$doctorId]);
$upcomingCount = (int)$stmt->fetch()['c'];

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) c FROM appointments WHERE doctor_id = ?");
$stmt->execute([$doctorId]);
$patientCount = (int)$stmt->fetch()['c'];

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM appointments WHERE doctor_id = ? AND status IN ('Scheduled','Confirmed') AND appointment_date <= CURDATE()");
$stmt->execute([$doctorId]);
$pendingCount = (int)$stmt->fetch()['c'];

$stmt = $pdo->prepare("SELECT a.*, u.name AS patient_name, p.patient_id AS patient_code
                        FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN users u ON p.user_id = u.id
                        WHERE a.doctor_id = ? AND a.appointment_date = CURDATE() AND a.status NOT IN ('Cancelled')
                        ORDER BY a.appointment_time ASC");
$stmt->execute([$doctorId]);
$todayAppointments = $stmt->fetchAll();

$pageTitle = 'Doctor Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-1">Welcome, Dr. <?= esc($_SESSION['name']) ?></h4>
<p class="text-muted mb-4"><?= esc($doctor['specialization']) ?></p>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
      <div><div class="stat-value"><?= $todayCount ?></div><div class="stat-label">Today's Appointments</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-calendar-week"></i></div>
      <div><div class="stat-value"><?= $upcomingCount ?></div><div class="stat-label">Upcoming</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
      <div><div class="stat-value"><?= $patientCount ?></div><div class="stat-label">Total Patients</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
      <div><div class="stat-value"><?= $pendingCount ?></div><div class="stat-label">Pending Consultations</div></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="fa-solid fa-calendar-day me-2"></i>Today's Appointments</div>
  <div class="card-body p-0">
    <?php if (!$todayAppointments): ?>
      <div class="empty-state"><i class="fa-regular fa-calendar-xmark"></i>No appointments scheduled for today.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead><tr><th>Time</th><th>Patient</th><th>Patient ID</th><th>Reason</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($todayAppointments as $a): ?>
            <tr>
              <td><?= formatTime($a['appointment_time']) ?></td>
              <td><?= esc($a['patient_name']) ?></td>
              <td><?= esc($a['patient_code']) ?></td>
              <td class="small text-muted"><?= esc($a['reason'] ?: '-') ?></td>
              <td><span class="badge <?= statusBadgeClass($a['status']) ?>"><?= esc($a['status']) ?></span></td>
              <td><a href="patients.php?id=<?= $a['patient_id'] ?>" class="btn btn-sm btn-outline-primary">View Patient</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
