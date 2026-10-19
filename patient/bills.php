<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('patient');

$pdo = getDB();
$patient = currentPatientRecord($pdo);

// ---- Handle Test Payment ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    verifyCsrf();
    $billId = (int)($_POST['bill_id'] ?? 0);
    $simulateFailure = isset($_POST['simulate_failure']);

    try {
        $stmt = $pdo->prepare('SELECT * FROM bills WHERE id = ? AND patient_id = ?');
        $stmt->execute([$billId, $patient['id']]);
        $bill = $stmt->fetch();

        if (!$bill) {
            setFlash('danger', 'Bill not found.');
        } elseif ($bill['status'] === 'Paid') {
            setFlash('warning', 'This bill has already been paid.');
        } else {
            $pdo->beginTransaction();

            $txnId = generateTransactionId();
            $paymentStatus = $simulateFailure ? 'Failed' : 'Success';

            $stmt = $pdo->prepare('INSERT INTO payments (bill_id, transaction_id, amount, payment_method, payment_status)
                                    VALUES (?, ?, ?, "Test Payment", ?)');
            $stmt->execute([$billId, $txnId, $bill['total_amount'], $paymentStatus]);

            if ($paymentStatus === 'Success') {
                $stmt = $pdo->prepare('UPDATE bills SET status = "Paid" WHERE id = ?');
                $stmt->execute([$billId]);
                createNotification($pdo, currentUserId(), 'Payment Successful', 'Payment of ' . formatMoney((float)$bill['total_amount']) . ' received. Transaction ID: ' . $txnId, 'payment');
                setFlash('success', 'Payment successful! Transaction ID: ' . $txnId);
            } else {
                $stmt = $pdo->prepare('UPDATE bills SET status = "Failed" WHERE id = ?');
                $stmt->execute([$billId]);
                setFlash('danger', 'Unable to process payment. Please try again.');
            }

            logAudit($pdo, currentUserId(), 'Test payment (' . $paymentStatus . ')', 'bills', $billId);
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        logError('pay_bill', $e);
        setFlash('danger', 'Unable to process payment right now. Please try again.');
    }
    redirect('bills.php');
}

// ---- List bills ----
$stmt = $pdo->prepare("SELECT b.*, u.name AS doctor_name FROM bills b
                        LEFT JOIN appointments a ON b.appointment_id = a.id
                        LEFT JOIN doctors d ON a.doctor_id = d.id
                        LEFT JOIN users u ON d.user_id = u.id
                        WHERE b.patient_id = ? ORDER BY b.created_at DESC");
$stmt->execute([$patient['id']]);
$bills = $stmt->fetchAll();

// Payments for receipts (keyed by bill id, most recent successful one)
$paymentsByBill = [];
if ($bills) {
    $ids = array_column($bills, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE bill_id IN ($placeholders) ORDER BY paid_at DESC");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $p) {
        if (!isset($paymentsByBill[$p['bill_id']])) {
            $paymentsByBill[$p['bill_id']] = $p;
        }
    }
}

$pageTitle = 'My Bills';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-4"><i class="fa-solid fa-file-invoice-dollar me-2"></i>My Bills</h4>

<div class="card">
  <div class="card-body p-0">
    <?php if (!$bills): ?>
      <div class="empty-state"><i class="fa-regular fa-file-invoice"></i>No bills yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead><tr><th>Bill ID</th><th>Doctor</th><th>Consultation</th><th>Treatment</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($bills as $b): ?>
            <tr>
              <td>#<?= str_pad((string)$b['id'], 5, '0', STR_PAD_LEFT) ?></td>
              <td><?= $b['doctor_name'] ? 'Dr. ' . esc($b['doctor_name']) : '-' ?></td>
              <td><?= formatMoney((float)$b['consultation_fee']) ?></td>
              <td><?= formatMoney((float)$b['treatment_charges']) ?></td>
              <td class="fw-bold"><?= formatMoney((float)$b['total_amount']) ?></td>
              <td><span class="badge <?= statusBadgeClass($b['status']) ?>"><?= esc($b['status']) ?></span></td>
              <td><?= formatDate($b['created_at']) ?></td>
              <td class="text-nowrap">
                <?php if (in_array($b['status'], ['Unpaid', 'Pending', 'Failed'])): ?>
                  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#payModal"
                          onclick="openPay(<?= $b['id'] ?>, '<?= esc(formatMoney((float)$b['total_amount'])) ?>')">
                    <i class="fa-solid fa-credit-card me-1"></i>Pay Now
                  </button>
                <?php elseif ($b['status'] === 'Paid' && isset($paymentsByBill[$b['id']])): ?>
                  <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#receiptModal-<?= $b['id'] ?>">
                    <i class="fa-solid fa-receipt me-1"></i>Receipt
                  </button>
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

<!-- Receipt modals -->
<?php foreach ($bills as $b): if ($b['status'] === 'Paid' && isset($paymentsByBill[$b['id']])): $p = $paymentsByBill[$b['id']]; ?>
  <div class="modal fade" id="receiptModal-<?= $b['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa-solid fa-receipt me-2"></i>Payment Receipt</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <table class="table table-sm">
            <tr><th>Transaction ID</th><td><?= esc($p['transaction_id']) ?></td></tr>
            <tr><th>Bill ID</th><td>#<?= str_pad((string)$b['id'], 5, '0', STR_PAD_LEFT) ?></td></tr>
            <tr><th>Amount Paid</th><td><?= formatMoney((float)$p['amount']) ?></td></tr>
            <tr><th>Payment Method</th><td><?= esc($p['payment_method']) ?></td></tr>
            <tr><th>Status</th><td><span class="badge <?= statusBadgeClass('Paid') ?>">Success</span></td></tr>
            <tr><th>Date &amp; Time</th><td><?= formatDateTime($p['paid_at']) ?></td></tr>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
<?php endif; endforeach; ?>

<!-- Payment modal -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" data-loading>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="pay">
        <input type="hidden" name="bill_id" id="payBillId">
        <div class="modal-header">
          <h5 class="modal-title">Test Payment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning small mb-3">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            This is a <strong>demo test-payment mechanism</strong>. No real transaction or money movement occurs.
            The architecture is designed so a real payment gateway can be plugged in later.
          </div>
          <p>Amount to pay: <strong id="payAmount"></strong></p>
          <div class="mb-2">
            <label class="form-label">Card Number (demo only, not validated)</label>
            <input type="text" class="form-control" placeholder="4111 1111 1111 1111" maxlength="19">
          </div>
          <div class="row">
            <div class="col-6 mb-2"><label class="form-label">Expiry</label><input type="text" class="form-control" placeholder="MM/YY"></div>
            <div class="col-6 mb-2"><label class="form-label">CVV</label><input type="text" class="form-control" placeholder="123" maxlength="3"></div>
          </div>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="simulate_failure" id="simFail">
            <label class="form-check-label small text-muted" for="simFail">Simulate a failed payment (for testing)</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-lock me-1"></i>Pay Now (Test)</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openPay(billId, amountText) {
  document.getElementById('payBillId').value = billId;
  document.getElementById('payAmount').textContent = amountText;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
