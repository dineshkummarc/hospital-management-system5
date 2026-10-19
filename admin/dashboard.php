<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo = getDB();

$totalPatients = (int)$pdo->query("SELECT COUNT(*) c FROM patients")->fetch()['c'];
$totalDoctors = (int)$pdo->query("SELECT COUNT(*) c FROM doctors WHERE status='active'")->fetch()['c'];
$todayAppointments = (int)$pdo->query("SELECT COUNT(*) c FROM appointments WHERE appointment_date = CURDATE() AND status != 'Cancelled'")->fetch()['c'];
$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE payment_status='Success'")->fetch()['s'];
$pendingBills = (int)$pdo->query("SELECT COUNT(*) c FROM bills WHERE status IN ('Unpaid','Pending','Failed')")->fetch()['c'];
$occupiedBeds = (int)$pdo->query("SELECT COUNT(*) c FROM beds WHERE status='Occupied'")->fetch()['c'];
$availableBeds = (int)$pdo->query("SELECT COUNT(*) c FROM beds WHERE status='Available'")->fetch()['c'];

$recentAppointments = $pdo->query("SELECT a.*, pu.name AS patient_name, du.name AS doctor_name
                                    FROM appointments a
                                    JOIN patients p ON a.patient_id = p.id JOIN users pu ON p.user_id = pu.id
                                    JOIN doctors d ON a.doctor_id = d.id JOIN users du ON d.user_id = du.id
                                    ORDER BY a.created_at DESC LIMIT 8")->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-4"><i class="fa-solid fa-gauge me-2"></i>Administrator Dashboard</h4>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
      <div><div class="stat-value"><?= $totalPatients ?></div><div class="stat-label">Total Patients</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-user-doctor"></i></div>
      <div><div class="stat-value"><?= $totalDoctors ?></div><div class="stat-label">Active Doctors</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
      <div><div class="stat-value"><?= $todayAppointments ?></div><div class="stat-label">Today's Appointments</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
      <div><div class="stat-value"><?= formatMoney($totalRevenue) ?></div><div class="stat-label">Total Revenue</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
      <div><div class="stat-value"><?= $pendingBills ?></div><div class="stat-label">Pending Bills</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-solid fa-bed"></i></div>
      <div><div class="stat-value"><?= $occupiedBeds ?></div><div class="stat-label">Occupied Beds</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon"><i class="fa-regular fa-bed"></i></div>
      <div><div class="stat-value"><?= $availableBeds ?></div><div class="stat-label">Available Beds</div></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="fa-solid fa-clock-rotate-left me-2"></i>Recent Appointments</div>
  <div class="card-body p-0">
    <?php if (!$recentAppointments): ?>
      <div class="empty-state"><i class="fa-regular fa-calendar-xmark"></i>No appointments yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead><tr><th>Patient</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($recentAppointments as $a): ?>
            <tr>
              <td><?= esc($a['patient_name']) ?></td>
              <td>Dr. <?= esc($a['doctor_name']) ?></td>
              <td><?= formatDate($a['appointment_date']) ?></td>
              <td><?= formatTime($a['appointment_time']) ?></td>
              <td><span class="badge <?= statusBadgeClass($a['status']) ?>"><?= esc($a['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
