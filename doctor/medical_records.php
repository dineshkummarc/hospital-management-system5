<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('doctor');

$pdo = getDB();
$doctor = currentDoctorRecord($pdo);
$errors = [];

$appointmentId = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : null;
$patientIdParam = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;

// ---- Handle new record submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_record') {
    verifyCsrf();

    $postAppointmentId = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
    $postPatientId = (int)($_POST['patient_id'] ?? 0);
    $diagnosis = clean($_POST['diagnosis'] ?? '');
    $notes = clean($_POST['notes'] ?? '');
    $medicines = $_POST['medicine_name'] ?? [];
    $dosages = $_POST['dosage'] ?? [];
    $frequencies = $_POST['frequency'] ?? [];
    $durations = $_POST['duration'] ?? [];
    $instructions = $_POST['instructions'] ?? [];

    if ($diagnosis === '') $errors[] = 'Diagnosis is required.';

    // Verify this doctor is actually allowed to write for this patient
    // (must have at least one appointment with them).
    if ($postPatientId <= 0) {
        $errors[] = 'Invalid patient.';
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) c FROM appointments WHERE doctor_id = ? AND patient_id = ?');
        $stmt->execute([$doctor['id'], $postPatientId]);
        if ((int)$stmt->fetch()['c'] === 0) {
            $errors[] = 'You are not authorized to add records for this patient.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO medical_records (patient_id, doctor_id, appointment_id, diagnosis, notes)
                                    VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$postPatientId, $doctor['id'], $postAppointmentId, $diagnosis, $notes ?: null]);
            $recordId = (int)$pdo->lastInsertId();

            for ($i = 0; $i < count($medicines); $i++) {
                $med = clean($medicines[$i] ?? '');
                if ($med === '') continue; // skip empty rows
                $stmt = $pdo->prepare('INSERT INTO prescriptions (medical_record_id, medicine_name, dosage, frequency, duration, instructions)
                                        VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $recordId,
                    $med,
                    clean($dosages[$i] ?? ''),
                    clean($frequencies[$i] ?? ''),
                    clean($durations[$i] ?? ''),
                    clean($instructions[$i] ?? '') ?: null,
                ]);
            }

            $stmt = $pdo->prepare('SELECT user_id FROM patients WHERE id = ?');
            $stmt->execute([$postPatientId]);
            $patientUserId = $stmt->fetchColumn();
            if ($patientUserId) {
                createNotification($pdo, (int)$patientUserId, 'New Medical Record', 'Dr. ' . $_SESSION['name'] . ' added a new diagnosis to your medical history.', 'medical_record');
            }

            logAudit($pdo, currentUserId(), 'Added medical record', 'medical_records', $recordId);
            $pdo->commit();
            setFlash('success', 'Medical record saved successfully.');
            redirect('patients.php?id=' . $postPatientId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            logError('add_medical_record', $e);
            $errors[] = 'Unable to save the medical record right now. Please try again.';
        }
    }
}

// ---- Resolve the patient context for the form (from appointment_id or patient_id) ----
$formPatient = null;
$formAppointment = null;

if ($appointmentId) {
    $stmt = $pdo->prepare('SELECT a.*, u.name, p.patient_id AS patient_code, p.id AS pid FROM appointments a
                            JOIN patients p ON a.patient_id = p.id JOIN users u ON p.user_id = u.id
                            WHERE a.id = ? AND a.doctor_id = ?');
    $stmt->execute([$appointmentId, $doctor['id']]);
    $formAppointment = $stmt->fetch();
    if ($formAppointment) {
        $formPatient = ['id' => $formAppointment['pid'], 'name' => $formAppointment['name'], 'patient_code' => $formAppointment['patient_code']];
    }
} elseif ($patientIdParam) {
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM appointments WHERE doctor_id = ? AND patient_id = ?');
    $stmt->execute([$doctor['id'], $patientIdParam]);
    if ((int)$stmt->fetch()['c'] > 0) {
        $stmt = $pdo->prepare('SELECT p.id, u.name, p.patient_id AS patient_code FROM patients p JOIN users u ON p.user_id = u.id WHERE p.id = ?');
        $stmt->execute([$patientIdParam]);
        $formPatient = $stmt->fetch();
    }
}

// ---- List of records this doctor has authored ----
$stmt = $pdo->prepare("SELECT mr.*, u.name AS patient_name, p.patient_id AS patient_code FROM medical_records mr
                        JOIN patients p ON mr.patient_id = p.id JOIN users u ON p.user_id = u.id
                        WHERE mr.doctor_id = ? ORDER BY mr.created_at DESC LIMIT 50");
$stmt->execute([$doctor['id']]);
$myRecords = $stmt->fetchAll();

$pageTitle = 'Medical Records';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-4"><i class="fa-solid fa-file-medical me-2"></i>Medical Records</h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger"><?= esc($err) ?></div>
<?php endforeach; ?>

<?php if ($formPatient): ?>
  <div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-notes-medical me-2"></i>New Record for <?= esc($formPatient['name']) ?> (<?= esc($formPatient['patient_code']) ?>)</div>
    <div class="card-body">
      <form method="POST" data-loading>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_record">
        <input type="hidden" name="patient_id" value="<?= $formPatient['id'] ?>">
        <?php if ($formAppointment): ?>
          <input type="hidden" name="appointment_id" value="<?= $formAppointment['id'] ?>">
          <p class="text-muted small">Linked to appointment on <?= formatDate($formAppointment['appointment_date']) ?> at <?= formatTime($formAppointment['appointment_time']) ?></p>
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label">Diagnosis</label>
          <textarea name="diagnosis" class="form-control" rows="2" required></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Clinical Notes (optional)</label>
          <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>

        <label class="form-label">Prescription</label>
        <div id="prescriptionRows">
          <div class="row g-2 mb-2 presc-row">
            <div class="col-md-3"><input type="text" name="medicine_name[]" class="form-control form-control-sm" placeholder="Medicine name"></div>
            <div class="col-md-2"><input type="text" name="dosage[]" class="form-control form-control-sm" placeholder="Dosage e.g. 500mg"></div>
            <div class="col-md-2"><input type="text" name="frequency[]" class="form-control form-control-sm" placeholder="Frequency"></div>
            <div class="col-md-2"><input type="text" name="duration[]" class="form-control form-control-sm" placeholder="Duration"></div>
            <div class="col-md-3"><input type="text" name="instructions[]" class="form-control form-control-sm" placeholder="Instructions"></div>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="addPrescRow()"><i class="fa-solid fa-plus me-1"></i>Add Medicine</button>
        <br>
        <button type="submit" class="btn btn-primary">Save Medical Record</button>
        <a href="patients.php?id=<?= $formPatient['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
      </form>
    </div>
  </div>
  <script>
  function addPrescRow() {
    const container = document.getElementById('prescriptionRows');
    const row = container.querySelector('.presc-row').cloneNode(true);
    row.querySelectorAll('input').forEach(i => i.value = '');
    container.appendChild(row);
  }
  </script>
<?php elseif ($appointmentId || $patientIdParam): ?>
  <div class="alert alert-warning">Unable to load that patient. Please select a patient from your patient list.</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">Records You've Authored</div>
  <div class="card-body p-0">
    <?php if (!$myRecords): ?>
      <div class="empty-state"><i class="fa-regular fa-file-lines"></i>You haven't added any medical records yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead><tr><th>Date</th><th>Patient</th><th>Diagnosis</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($myRecords as $r): ?>
            <tr>
              <td><?= formatDate($r['created_at']) ?></td>
              <td><?= esc($r['patient_name']) ?> <span class="text-muted small">(<?= esc($r['patient_code']) ?>)</span></td>
              <td class="small"><?= esc(mb_strimwidth($r['diagnosis'], 0, 80, '...')) ?></td>
              <td><a href="patients.php?id=<?= $r['patient_id'] ?>" class="btn btn-sm btn-outline-primary">View Patient</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
