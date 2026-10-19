<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('patient');

$pdo = getDB();
$patient = currentPatientRecord($pdo);
$errors = [];

// ---- Handle booking submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book') {
    verifyCsrf();

    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $date = clean($_POST['appointment_date'] ?? '');
    $time = clean($_POST['appointment_time'] ?? '');
    $reason = clean($_POST['reason'] ?? '');

    if ($doctorId <= 0 || $date === '' || $time === '') {
        $errors[] = 'Please choose a doctor, date, and an available time slot.';
    } elseif (strtotime($date) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Please select a valid, current or future date.';
    }

    if (!$errors) {
        try {
            // Re-check availability at the moment of insert (in addition to
            // the UNIQUE KEY at the database level) -- this is the
            // application-level half of the double-booking prevention.
            if (!isSlotAvailable($pdo, $doctorId, $date, $time)) {
                $errors[] = 'Unable to book appointment. Please select another available time slot.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status)
                                        VALUES (?, ?, ?, ?, ?, "Scheduled")');
                $stmt->execute([$patient['id'], $doctorId, $date, $time, $reason ?: null]);
                $appointmentId = (int)$pdo->lastInsertId();

                logAudit($pdo, currentUserId(), 'Booked appointment', 'appointments', $appointmentId);
                createNotification($pdo, currentUserId(), 'Appointment Booked', 'Your appointment on ' . formatDate($date) . ' at ' . formatTime($time) . ' has been scheduled.', 'appointment');

                // Notify the doctor too.
                $stmt = $pdo->prepare('SELECT user_id FROM doctors WHERE id = ?');
                $stmt->execute([$doctorId]);
                $doctorUserId = $stmt->fetchColumn();
                if ($doctorUserId) {
                    createNotification($pdo, (int)$doctorUserId, 'New Appointment', 'A new appointment has been booked for ' . formatDate($date) . ' at ' . formatTime($time) . '.', 'appointment');
                }

                setFlash('success', 'Appointment booked successfully!');
                redirect('appointments.php');
            }
        } catch (PDOException $e) {
            // The UNIQUE KEY(doctor_id, appointment_date, appointment_time) is
            // the last line of defence in case of a race condition.
            if ($e->getCode() === '23000') {
                $errors[] = 'Unable to book appointment. Please select another available time slot.';
            } else {
                logError('book_appointment', $e);
                $errors[] = 'Unable to book appointment right now. Please try again.';
            }
        } catch (Throwable $e) {
            logError('book_appointment', $e);
            $errors[] = 'Unable to book appointment right now. Please try again.';
        }
    }
}

// ---- Doctor search / filter ----
$search = clean($_GET['search'] ?? '');
$specialization = clean($_GET['specialization'] ?? '');

$sql = "SELECT d.*, u.name, u.email FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.status = 'active'";
$params = [];
if ($search !== '') {
    $sql .= ' AND u.name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($specialization !== '') {
    $sql .= ' AND d.specialization = ?';
    $params[] = $specialization;
}
$sql .= ' ORDER BY u.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

$specializations = $pdo->query("SELECT DISTINCT specialization FROM doctors WHERE status='active' ORDER BY specialization")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Book Appointment';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-4"><i class="fa-solid fa-calendar-plus me-2"></i>Book an Appointment</h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger"><?= esc($err) ?></div>
<?php endforeach; ?>

<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-2">
      <div class="col-md-5">
        <input type="text" name="search" class="form-control" placeholder="Search doctor by name..." value="<?= esc($search) ?>">
      </div>
      <div class="col-md-4">
        <select name="specialization" class="form-select">
          <option value="">All Specializations</option>
          <?php foreach ($specializations as $spec): ?>
            <option value="<?= esc($spec) ?>" <?= $specialization === $spec ? 'selected' : '' ?>><?= esc($spec) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <button class="btn btn-outline-primary w-100" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3">
  <?php if (!$doctors): ?>
    <div class="empty-state"><i class="fa-solid fa-user-doctor"></i>No doctors match your search.</div>
  <?php endif; ?>

  <?php foreach ($doctors as $doc): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card h-100">
        <div class="card-body">
          <h6 class="fw-bold mb-0">Dr. <?= esc($doc['name']) ?></h6>
          <div class="text-muted small mb-2"><?= esc($doc['specialization']) ?> &bull; <?= esc($doc['qualification']) ?></div>
          <div class="small mb-1"><i class="fa-regular fa-clock me-1"></i><?= esc($doc['availability']) ?></div>
          <div class="small mb-3"><i class="fa-solid fa-indian-rupee-sign me-1"></i>Consultation fee: <?= formatMoney((float)$doc['consultation_fee']) ?></div>
          <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#bookModal"
                  onclick="selectDoctor(<?= $doc['id'] ?>, '<?= esc($doc['name']) ?>')">
            <i class="fa-solid fa-calendar-plus me-1"></i>Book Appointment
          </button>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Booking Modal -->
<div class="modal fade" id="bookModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" data-loading>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="book">
        <input type="hidden" name="doctor_id" id="modalDoctorId">
        <input type="hidden" name="appointment_time" id="modalTime">
        <div class="modal-header">
          <h5 class="modal-title">Book with <span id="modalDoctorName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Appointment Date</label>
            <input type="date" name="appointment_date" id="modalDate" class="form-control" required min="<?= date('Y-m-d') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Available Time Slots</label>
            <div id="slotContainer" class="d-flex flex-wrap gap-2">
              <span class="text-muted small">Select a date to see availability.</span>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Reason for Visit (optional)</label>
            <textarea name="reason" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="confirmBookBtn" disabled>Confirm Booking</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function selectDoctor(id, name) {
  document.getElementById('modalDoctorId').value = id;
  document.getElementById('modalDoctorName').textContent = name;
  document.getElementById('modalTime').value = '';
  document.getElementById('confirmBookBtn').disabled = true;
  document.getElementById('slotContainer').innerHTML = '<span class="text-muted small">Select a date to see availability.</span>';
}

document.getElementById('modalDate').addEventListener('change', function () {
  const doctorId = document.getElementById('modalDoctorId').value;
  const date = this.value;
  const container = document.getElementById('slotContainer');
  container.innerHTML = '<span class="text-muted small">Loading availability...</span>';
  document.getElementById('modalTime').value = '';
  document.getElementById('confirmBookBtn').disabled = true;

  fetch(`/hms/api/appointments/available_slots.php?doctor_id=${doctorId}&date=${date}`)
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
      initSlotPicker('slotContainer', 'modalTime');
      container.addEventListener('click', () => {
        document.getElementById('confirmBookBtn').disabled = !document.getElementById('modalTime').value;
      });
    })
    .catch(() => { container.innerHTML = '<span class="text-danger small">Unable to load availability.</span>'; });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
