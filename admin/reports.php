<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo = getDB();

$from = clean($_GET['from'] ?? date('Y-m-01'));
$to = clean($_GET['to'] ?? date('Y-m-d'));

// ---- Appointment Report ----
$stmt = $pdo->prepare("SELECT
    COUNT(*) AS total,
    SUM(status='Completed') AS completed,
    SUM(status='Cancelled') AS cancelled,
    SUM(status IN ('Scheduled','Confirmed','Rescheduled')) AS scheduled
    FROM appointments WHERE appointment_date BETWEEN ? AND ?");
$stmt->execute([$from, $to]);
$apptReport = $stmt->fetch();

// ---- Revenue Report ----
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) AS total_billed FROM bills WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$from, $to]);
$totalBilled = (float)$stmt->fetch()['total_billed'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total_paid FROM payments WHERE payment_status='Success' AND DATE(paid_at) BETWEEN ? AND ?");
$stmt->execute([$from, $to]);
$totalPaid = (float)$stmt->fetch()['total_paid'];

$pendingAmount = max(0, $totalBilled - $totalPaid);

// ---- Bed Occupancy Report ----
$totalBeds = (int)$pdo->query("SELECT COUNT(*) c FROM beds")->fetch()['c'];
$occupiedBeds = (int)$pdo->query("SELECT COUNT(*) c FROM beds WHERE status='Occupied'")->fetch()['c'];
$availableBeds = $totalBeds - $occupiedBeds;
$occupancyPct = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;

// Appointments per day for the chart
$stmt = $pdo->prepare("SELECT appointment_date, COUNT(*) c FROM appointments WHERE appointment_date BETWEEN ? AND ? GROUP BY appointment_date ORDER BY appointment_date");
$stmt->execute([$from, $to]);
$dailyAppointments = $stmt->fetchAll();

$pageTitle = 'Reports';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-4"><i class="fa-solid fa-chart-line me-2"></i>Hospital Reports</h4>

<div class="card mb-4">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-center">
      <div class="col-md-4"><label class="form-label small mb-0">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= esc($from) ?>"></div>
      <div class="col-md-4"><label class="form-label small mb-0">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= esc($to) ?>"></div>
      <div class="col-md-4"><button class="btn btn-primary btn-sm w-100 mt-4" type="submit">Apply Date Range</button></div>
    </form>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Appointment Report</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><th>Total Appointments</th><td><?= (int)$apptReport['total'] ?></td></tr>
          <tr><th>Completed</th><td><?= (int)$apptReport['completed'] ?></td></tr>
          <tr><th>Cancelled</th><td><?= (int)$apptReport['cancelled'] ?></td></tr>
          <tr><th>Scheduled/Upcoming</th><td><?= (int)$apptReport['scheduled'] ?></td></tr>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Revenue Report</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><th>Total Billed</th><td><?= formatMoney($totalBilled) ?></td></tr>
          <tr><th>Total Paid</th><td><?= formatMoney($totalPaid) ?></td></tr>
          <tr><th>Pending Amount</th><td><?= formatMoney($pendingAmount) ?></td></tr>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Bed Occupancy Report</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><th>Total Beds</th><td><?= $totalBeds ?></td></tr>
          <tr><th>Occupied</th><td><?= $occupiedBeds ?></td></tr>
          <tr><th>Available</th><td><?= $availableBeds ?></td></tr>
          <tr><th>Occupancy %</th><td><?= $occupancyPct ?>%</td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Appointments Over Time</div>
      <div class="card-body"><canvas id="apptChart" height="100"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Bed Occupancy</div>
      <div class="card-body"><canvas id="bedChart" height="150"></canvas></div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
const apptLabels = <?= json_encode(array_map(fn($r) => date('d M', strtotime($r['appointment_date'])), $dailyAppointments)) ?>;
const apptData = <?= json_encode(array_map(fn($r) => (int)$r['c'], $dailyAppointments)) ?>;

new Chart(document.getElementById('apptChart'), {
  type: 'line',
  data: { labels: apptLabels, datasets: [{ label: 'Appointments', data: apptData, borderColor: '#0f6e5f', backgroundColor: 'rgba(15,110,95,0.15)', fill: true, tension: 0.3 }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('bedChart'), {
  type: 'doughnut',
  data: {
    labels: ['Occupied', 'Available'],
    datasets: [{ data: [<?= $occupiedBeds ?>, <?= $availableBeds ?>], backgroundColor: ['#dc3545', '#0f6e5f'] }]
  },
  options: { plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
