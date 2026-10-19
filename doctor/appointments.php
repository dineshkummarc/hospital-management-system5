<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('doctor');

$pdo = getDB();
$doctor = currentDoctorRecord($pdo);

// ---- Update appointment status ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    verifyCsrf();
    $apptId = (int)($_POST['appointment_id'] ?? 0);
    $newStatus = clean($_POST['status'] ?? '');
    $allowed = ['Confirmed', 'Completed', 'Cancelled'];

    try {
        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND doctor_id = ?');
        $stmt->execute([$apptId, $doctor['id']]);
        $appt = $stmt->fetch();

        if (!$appt) {
            setFlash('danger', 'Appointment not found.');
        } elseif (!in_array($newStatus, $allowed)) {
            setFlash('danger', 'Invalid status.');
        } elseif ($appt['status'] === 'Completed') {
            setFlash('warning', 'This appointment is already marked completed.');
        } else {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $apptId]);

            $stmt = $pdo->prepare('SELECT user_id FROM patients WHERE id = ?');
            $stmt->execute([$appt['patient_id']]);
            $patientUserId = $stmt->fetchColumn();

            if ($newStatus === 'Confirmed' && $patientUserId) {
                createNotification($pdo, (int)$patientUserId, 'Appointment Confirmed', 'Your appointment on ' . formatDate($appt['appointment_date']) . ' has been confirmed by the doctor.', 'appointment');
            }

            if ($newStatus === 'Cancelled' && $patientUserId) {
                createNotification($pdo, (int)$patientUserId, 'Appointment Cancelled', 'Your appointment on ' . formatDate($appt['appointment_date']) . ' was cancelled by the doctor.', 'appointment');
            }

            if ($newStatus === 'Completed') {
                // Auto-generate the bill, unless one already exists for this appointment.
                $stmt = $pdo->prepare('SELECT id FROM bills WHERE appointment_id = ?');
                $stmt->execute([$apptId]);
                if (!$stmt->fetch()) {
                    $billId = generateBillForAppointment($pdo, $apptId);
                    if ($patientUserId) {
                        createNotification($pdo, (int)$patientUserId, 'Bill Generated', 'A bill has been generated for your recent consultation.', 'billing');
                    }
                    logAudit($pdo, currentUserId(), 'Auto-generated bill', 'bills', $billId);
                }
            }

            logAudit($pdo, currentUserId(), 'Updated appointment status to ' . $newStatus, 'appointments', $apptId);
            $pdo->commit();
            setFlash('success', 'Appointment marked as ' . $newStatus . '.');

            if ($newStatus === 'Completed') {
                redirect('medical_records.php?appointment_id=' . $apptId);
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        logError('update_appointment_status', $e);
        setFlash('danger', 'Unable to update appointment right now. Please try again.');
    }
    redirect('appointments.php');
}

// ---- List with filters ----
$statusFilter = clean($_GET['status'] ?? '');
$dateFilter = clean($_GET['date_filter'] ?? 'upcoming'); // today | upcoming | past | all

$sql = "SELECT a.*, u.name AS patient_name, p.patient_id AS patient_code FROM appointments a
        JOIN patients p ON a.patient_id = p.id JOIN users u ON p.user_id = u.id
        WHERE a.doctor_id = ?";
$params = [$doctor['id']];

if ($dateFilter === 'today') {
    $sql .= ' AND a.appointment_date = CURDATE()';
} elseif ($dateFilter === 'upcoming') {
    $sql .= ' AND a.appointment_date >= CURDATE()';
} elseif ($dateFilter === 'past') {
    $sql .= ' AND a.appointment_date < CURDATE()';
}

if ($statusFilter !== '') {
    $sql .= ' AND a.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY a.appointment_date ASC, a.appointment_time ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'My Appointments';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-4"><i class="fa-solid fa-calendar-check me-2"></i>My Appointments</h4>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
      <select name="date_filter" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <option value="today" <?= $dateFilter === 'today' ? 'selected' : '' ?>>Today</option>
        <option value="upcoming" <?= $dateFilter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
        <option value="past" <?= $dateFilter === 'past' ? 'selected' : '' ?>>Past</option>
        <option value="all" <?= $dateFilter === 'all' ? 'selected' : '' ?>>All</option>
      </select>
      <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <?php foreach (['Scheduled','Confirmed','Completed','Cancelled','Rescheduled'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (!$appointments): ?>
      <div class="empty-state"><i class="fa-regular fa-calendar-xmark"></i>No appointments found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($appointments as $a): ?>
            <tr>
              <td><?= formatDate($a['appointment_date']) ?></td>
              <td><?= formatTime($a['appointment_time']) ?></td>
              <td><?= esc($a['patient_name']) ?> <span class="text-muted small">(<?= esc($a['patient_code']) ?>)</span></td>
              <td class="small text-muted"><?= esc($a['reason'] ?: '-') ?></td>
              <td><span class="badge <?= statusBadgeClass($a['status']) ?>"><?= esc($a['status']) ?></span></td>
              <td class="text-nowrap">
                <a href="patients.php?id=<?= $a['patient_id'] ?>" class="btn btn-sm btn-outline-secondary" title="View Patient"><i class="fa-solid fa-user"></i></a>
                <?php if (!in_array($a['status'], ['Completed', 'Cancelled'])): ?>
                  <?php if ($a['status'] === 'Scheduled'): ?>
                    <form method="POST" class="d-inline">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="update_status">
                      <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                      <input type="hidden" name="status" value="Confirmed">
                      <button type="submit" class="btn btn-sm btn-outline-primary" title="Confirm"><i class="fa-solid fa-check"></i></button>
                    </form>
                  <?php endif; ?>
                  <form method="POST" class="d-inline" data-confirm="Mark this appointment as completed? This will generate a bill.">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                    <input type="hidden" name="status" value="Completed">
                    <button type="submit" class="btn btn-sm btn-outline-success" title="Mark Completed"><i class="fa-solid fa-check-double"></i></button>
                  </form>
                  <form method="POST" class="d-inline" data-confirm="Cancel this appointment?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                    <input type="hidden" name="status" value="Cancelled">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel"><i class="fa-solid fa-xmark"></i></button>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
