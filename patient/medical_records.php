<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('patient');

$pdo = getDB();
$patient = currentPatientRecord($pdo);

// Only ever query records for the CURRENT patient's id (never trust a URL
// parameter here) -- this is what stops a patient from viewing someone
// else's medical history.
$stmt = $pdo->prepare("SELECT mr.*, u.name AS doctor_name, d.specialization
                        FROM medical_records mr
                        JOIN doctors d ON mr.doctor_id = d.id
                        JOIN users u ON d.user_id = u.id
                        WHERE mr.patient_id = ?
                        ORDER BY mr.created_at DESC");
$stmt->execute([$patient['id']]);
$records = $stmt->fetchAll();

// Pull prescriptions for all these records in one query.
$prescriptionsByRecord = [];
if ($records) {
    $ids = array_column($records, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM prescriptions WHERE medical_record_id IN ($placeholders) ORDER BY created_at ASC");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $p) {
        $prescriptionsByRecord[$p['medical_record_id']][] = $p;
    }
}

$pageTitle = 'Medical Records';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<h4 class="fw-bold mb-4"><i class="fa-solid fa-file-medical me-2"></i>My Medical History</h4>

<?php if (!$records): ?>
  <div class="card"><div class="card-body"><div class="empty-state"><i class="fa-regular fa-file-lines"></i>No medical records yet.</div></div></div>
<?php else: ?>
  <div class="d-flex flex-column gap-3">
    <?php foreach ($records as $r): ?>
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-regular fa-calendar me-2"></i><?= formatDate($r['created_at']) ?></span>
          <span class="text-muted small">Dr. <?= esc($r['doctor_name']) ?> &bull; <?= esc($r['specialization']) ?></span>
        </div>
        <div class="card-body">
          <h6 class="fw-bold">Diagnosis</h6>
          <p><?= nl2br(esc($r['diagnosis'])) ?></p>
          <?php if ($r['notes']): ?>
            <h6 class="fw-bold">Clinical Notes</h6>
            <p class="text-muted"><?= nl2br(esc($r['notes'])) ?></p>
          <?php endif; ?>

          <?php if (!empty($prescriptionsByRecord[$r['id']])): ?>
            <h6 class="fw-bold mt-3">Prescription</h6>
            <div class="table-responsive">
              <table class="table table-sm table-bordered mb-0">
                <thead><tr><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Instructions</th></tr></thead>
                <tbody>
                <?php foreach ($prescriptionsByRecord[$r['id']] as $p): ?>
                  <tr>
                    <td><?= esc($p['medicine_name']) ?></td>
                    <td><?= esc($p['dosage']) ?></td>
                    <td><?= esc($p['frequency']) ?></td>
                    <td><?= esc($p['duration']) ?></td>
                    <td><?= esc($p['instructions'] ?: '-') ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
