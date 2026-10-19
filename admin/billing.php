<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo = getDB();

$statusFilter = clean($_GET['status'] ?? '');
$from = clean($_GET['from'] ?? '');
$to = clean($_GET['to'] ?? '');

$sql = "SELECT b.*, u.name AS patient_name, p.patient_id AS patient_code FROM bills b
        JOIN patients p ON b.patient_id = p.id JOIN users u ON p.user_id = u.id WHERE 1=1";
$params = [];
if ($statusFilter !== '') { $sql .= ' AND b.status = ?'; $params[] = $statusFilter; }
if ($from !== '') { $sql .= ' AND DATE(b.created_at) >= ?'; $params[] = $from; }
if ($to !== '') { $sql .= ' AND DATE(b.created_at) <= ?'; $params[] = $to; }
$sql .= ' ORDER BY b.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();

$totalBilled = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) s FROM bills")->fetch()['s'];
$totalPaid = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE payment_status='Success'")->fetch()['s'];
$totalPending = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) s FROM bills WHERE status IN ('Unpaid','Pending','Failed')")->fetch()['s'];

$pageTitle = 'Billing Overview';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-4"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Billing Overview</h4>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card"><div class="stat-label">Total Billed</div><div class="stat-value"><?= formatMoney($totalBilled) ?></div></div>
  </div>
  <div class="col-md-4">
    <div class="stat-card"><div class="stat-label">Total Collected</div><div class="stat-value"><?= formatMoney($totalPaid) ?></div></div>
  </div>
  <div class="col-md-4">
    <div class="stat-card"><div class="stat-label">Pending Amount</div><div class="stat-value"><?= formatMoney($totalPending) ?></div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-center">
      <div class="col-md-3">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (['Unpaid','Paid','Failed','Pending'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3"><input type="date" name="from" class="form-control form-control-sm" value="<?= esc($from) ?>"></div>
      <div class="col-md-3"><input type="date" name="to" class="form-control form-control-sm" value="<?= esc($to) ?>"></div>
      <div class="col-md-3"><button class="btn btn-outline-primary btn-sm w-100" type="submit">Filter</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (!$bills): ?>
      <div class="empty-state"><i class="fa-regular fa-file-invoice"></i>No bills found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead><tr><th>Bill ID</th><th>Patient</th><th>Consultation</th><th>Treatment</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($bills as $b): ?>
            <tr>
              <td>#<?= str_pad((string)$b['id'], 5, '0', STR_PAD_LEFT) ?></td>
              <td><?= esc($b['patient_name']) ?> <span class="text-muted small">(<?= esc($b['patient_code']) ?>)</span></td>
              <td><?= formatMoney((float)$b['consultation_fee']) ?></td>
              <td><?= formatMoney((float)$b['treatment_charges']) ?></td>
              <td class="fw-bold"><?= formatMoney((float)$b['total_amount']) ?></td>
              <td><span class="badge <?= statusBadgeClass($b['status']) ?>"><?= esc($b['status']) ?></span></td>
              <td><?= formatDate($b['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
