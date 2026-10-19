<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo = getDB();

$search = clean($_GET['search'] ?? '');
$from = clean($_GET['from'] ?? '');
$to = clean($_GET['to'] ?? '');

$sql = "SELECT al.*, u.name AS user_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (al.action LIKE ? OR al.entity LIKE ? OR u.name LIKE ?)';
    $params = array_fill(0, 3, '%' . $search . '%');
}
if ($from !== '') { $sql .= ' AND DATE(al.timestamp) >= ?'; $params[] = $from; }
if ($to !== '') { $sql .= ' AND DATE(al.timestamp) <= ?'; $params[] = $to; }
$sql .= ' ORDER BY al.timestamp DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$pageTitle = 'Audit Logs';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-4"><i class="fa-solid fa-clipboard-list me-2"></i>Audit Logs</h4>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-center">
      <div class="col-md-5"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search by action, entity, or user..." value="<?= esc($search) ?>"></div>
      <div class="col-md-3"><input type="date" name="from" class="form-control form-control-sm" value="<?= esc($from) ?>"></div>
      <div class="col-md-3"><input type="date" name="to" class="form-control form-control-sm" value="<?= esc($to) ?>"></div>
      <div class="col-md-1"><button class="btn btn-outline-primary btn-sm w-100" type="submit"><i class="fa-solid fa-filter"></i></button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (!$logs): ?>
      <div class="empty-state"><i class="fa-solid fa-clipboard-list"></i>No audit log entries found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 align-middle">
          <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Entity</th><th>Entity ID</th><th>IP Address</th></tr></thead>
          <tbody>
          <?php foreach ($logs as $l): ?>
            <tr>
              <td class="small"><?= formatDateTime($l['timestamp']) ?></td>
              <td><?= esc($l['user_name'] ?: 'Unknown') ?></td>
              <td><?= esc($l['action']) ?></td>
              <td><?= esc($l['entity'] ?: '-') ?></td>
              <td><?= esc($l['entity_id'] ?? '-') ?></td>
              <td class="small text-muted"><?= esc($l['ip_address'] ?: '-') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
