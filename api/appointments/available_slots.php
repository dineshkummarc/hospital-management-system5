<?php
/**
 * GET /api/appointments/available_slots.php?doctor_id=&date=&exclude=
 * Returns JSON: { slots: [ { time: 'HH:MM:SS', available: bool }, ... ] }
 * Used by the booking + reschedule pages so the browser only ever offers
 * genuinely free slots (real-time double-booking prevention).
 */
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$doctorId = (int)($_GET['doctor_id'] ?? 0);
$date = clean($_GET['date'] ?? '');
$excludeAppointmentId = isset($_GET['exclude']) ? (int)$_GET['exclude'] : null;

if ($doctorId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid doctor or date.']);
    exit;
}

// Disallow booking in the past.
if (strtotime($date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['slots' => []]);
    exit;
}

try {
    $pdo = getDB();
    $slots = getStandardTimeSlots();
    $result = [];

    foreach ($slots as $time) {
        $available = isSlotAvailable($pdo, $doctorId, $date, $time, $excludeAppointmentId);

        // If it's today, don't offer times that have already passed.
        if ($available && $date === date('Y-m-d') && strtotime($time) <= time()) {
            $available = false;
        }

        $result[] = ['time' => $time, 'available' => $available];
    }

    echo json_encode(['slots' => $result]);
} catch (Throwable $e) {
    logError('available_slots', $e);
    http_response_code(500);
    echo json_encode(['error' => 'Unable to fetch availability right now.']);
}
