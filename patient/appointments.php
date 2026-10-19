<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('patient');

$pdo = getDB();
$patient = currentPatientRecord($pdo);
$errors = [];

// ---- Handle cancel ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    verifyCsrf();
    $apptId = (int)($_POST['appointment_id'] ?? 0);

    try {
        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND patient_id = ?');
        $stmt->execute([$apptId, $patient['id']]);
        $appt = $stmt->fetch();

        if (!$appt) {
            setFlash('danger', 'Appointment not found.');
        } elseif (in_array($appt['status'], ['Completed', 'Cancelled'])) {
            setFlash('warning', 'This appointment can no longer be cancelled.');
        } else {
            $stmt = $pdo->prepare('UPDATE appointments SET status = "Cancelled" WHERE id = ?');
            $stmt->execute([$apptId]);
            logAudit($pdo, currentUserId(), 'Cancelled appointment', 'appointments', $apptId);
            createNotification($pdo, currentUserId(), 'Appointment Cancelled', 'Your appointment on ' . formatDate($appt['appointment_date']) . ' has been cancelled.', 'appointment');
            setFlash('success', 'Appointment cancelled.');
        }
    } catch (Throwable $e) {
        logError('cancel_appointment', $e);
        setFlash('danger', 'Unable to cancel appointment right now. Please try again.');
    }
    redirect('appointments.php');
}

// ---- Handle reschedule ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reschedule') {
    verifyCsrf();
    $apptId = (int)($_POST['appointment_id'] ?? 0);
    $newDate = clean($_POST['appointment_date'] ?? '');
    $newTime = clean($_POST['appointment_time'] ?? '');

    try {
        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND patient_id = ?');
        $stmt->execute([$apptId, $patient['id']]);
        $appt = $stmt->fetch();

        if (!$appt) {
            setFlash('danger', 'Appointment not found.');
        } elseif (in_array($appt['status'], ['Completed', 'Cancelled'])) {
            setFlash('warning', 'This appointment can no longer be rescheduled.');
        } elseif ($newDate === '' || $newTime === '') {
            setFlash('danger', 'Please choose a new date and an available time slot.');
        } elseif (!isSlotAvailable($pdo, (int)$appt['doctor_id'], $newDate, $newTime, $apptId)) {
            setFlash('danger', 'Unable to book appointment. Please select another available time slot.');
        } else {
            $stmt = $pdo->prepare('UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = "Rescheduled" WHERE id = ?');
            $stmt->execute([$newDate, $newTime, $apptId]);
            logAudit($pdo, currentUserId(), 'Rescheduled appointment', 'appointments', $apptId);
            createNotification($pdo, currentUserId(), 'Appointment Rescheduled', 'Your appointment has been moved to ' . formatDate($newDate) . ' at ' . formatTime($newTime) . '.', 'appointment');
            setFlash('success', 'Appointment rescheduled successfully.');
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            setFlash('danger', 'Unable to book appointment. Please select another available time slot.');
        } else {
            logError('reschedule_appointment', $e);
            setFlash('danger', 'Unable to reschedule right now. Please try again.');
        }
    } catch (Throwable $e) {
        logError('reschedule_appointment', $e);
        setFlash('danger', 'Unable to reschedule right now. Please try again.');
    }
    redirect('appointments.php');
}

// ---- List with status filter ----
$statusFilter = clean($_GET['status'] ?? '');
$sql = "SELECT a.*, u.name AS doctor_name, d.specialization FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id JOIN users u ON d.user_id = u.id
        WHERE a.patient_id = ?";
$params = [$patient['id']];
if ($statusFilter !== '') {
    $sql .= ' AND a.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY a.appointment_date DESC, a.appointment_time DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'My Appointments';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="fa-solid fa-calendar-check me-2"></i>My Appointments</h4>
  <a href="book_appointment.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Book New</a>
</div>

<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex gap-2 align-items-center">
      <label class="small text-muted mb-0">Filter by status:</label>
      <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <option value="">All</option>
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
          <thead><tr><th>Doctor</th><th>Specialization</th><th>Date</th><th>Time</th><th>Status</th><th>Reason</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($appointments as $a): ?>
            <tr>
              <td>Dr. <?= esc($a['doctor_name']) ?></td>
              <td><?= esc($a['specialization']) ?></td>
              <td><?= formatDate($a['appointment_date']) ?></td>
              <td><?= formatTime($a['appointment_time']) ?></td>
              <td><span class="badge <?= statusBadgeClass($a['status']) ?>"><?= esc($a['status']) ?></span></td>
              <td class="small text-muted"><?= esc($a['reason'] ?: '-') ?></td>
              <td class="text-nowrap">
                <?php if (!in_array($a['status'], ['Completed', 'Cancelled'])): ?>
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reschedModal"
                          onclick="openReschedule(<?= $a['id'] ?>, <?= $a['doctor_id'] ?>, 'Dr. <?= esc($a['doctor_name']) ?>')">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                  </button>
                  <form method="POST" class="d-inline" data-confirm="Are you sure you want to cancel this appointment?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark"></i></button>
                  </form>
                <?php else: ?>
                  <span class="text-muted small">-</span>
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

<!-- Reschedule Modal -->
<div class="modal fade" id="reschedModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" data-loading>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reschedule">
        <input type="hidden" name="appointment_id" id="reschedApptId">
        <input type="hidden" name="appointment_time" id="reschedTime">
        <div class="modal-header">
          <h5 class="modal-title">Reschedule with <span id="reschedDoctorName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">New Date</label>
            <input type="date" name="appointment_date" id="reschedDate" class="form-control" required min="<?= date('Y-m-d') ?>">
          </div>
          <div class="mb-2">
            <label class="form-label">Available Time Slots</label>
            <div id="reschedSlotContainer" class="d-flex flex-wrap gap-2">
              <span class="text-muted small">Select a date to see availability.</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" id="confirmReschedBtn" disabled>Confirm Reschedule</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let currentReschedDoctorId = null;
let currentApptId = null;

function openReschedule(apptId, doctorId, doctorName) {
  currentApptId = apptId;
  currentReschedDoctorId = doctorId;
  document.getElementById('reschedApptId').value = apptId;
  document.getElementById('reschedDoctorName').textContent = doctorName;
  document.getElementById('reschedDate').value = '';
  document.getElementById('reschedTime').value = '';
  document.getElementById('confirmReschedBtn').disabled = true;
  document.getElementById('reschedSlotContainer').innerHTML = '<span class="text-muted small">Select a date to see availability.</span>';
}

document.getElementById('reschedDate').addEventListener('change', function () {
  const date = this.value;
  const container = document.getElementById('reschedSlotContainer');
  container.innerHTML = '<span class="text-muted small">Loading availability...</span>';
  document.getElementById('reschedTime').value = '';
  document.getElementById('confirmReschedBtn').disabled = true;

  fetch(`/hms/api/appointments/available_slots.php?doctor_id=${currentReschedDoctorId}&date=${date}&exclude=${currentApptId}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) { container.innerHTML = '<span class="text-danger small">' + data.error + '</span>'; return; }
      if (!data.slots.length) { container.innerHTML = '<span class="text-muted small">No slots for this date.</span>'; return; }
      container.innerHTML = '';
      data.slots.forEach(s => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-sm btn-outline-secondary slot-btn';
        b.textContent = new Date('1970-01-01T' + s.time).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
        b.dataset.time = s.time;
        if (!s.available) { b.disabled = true; b.classList.add('text-decoration-line-through'); }
        container.appendChild(b);
      });
      initSlotPicker('reschedSlotContainer', 'reschedTime');
      container.addEventListener('click', () => {
        document.getElementById('confirmReschedBtn').disabled = !document.getElementById('reschedTime').value;
      });
    })
    .catch(() => { container.innerHTML = '<span class="text-danger small">Unable to load availability.</span>'; });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
