<?php
/**
 * Shared helper functions used across the whole application.
 * Included by includes/auth.php, which every protected page includes first.
 */

require_once __DIR__ . '/../config/database.php';

/* ---------------------------------------------------------------------
 * Input handling / security helpers
 * ------------------------------------------------------------------- */

/** Trim + strip tags from a raw input value. */
function clean(string $value): string
{
    return trim(strip_tags($value));
}

/** Escape a value for safe HTML output (prevents stored/reflected XSS). */
function esc(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Generate (or reuse) a CSRF token for the current session. */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden CSRF input field. */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . esc(csrfToken()) . '">';
}

/** Validate a submitted CSRF token; call at the top of every POST handler. */
function verifyCsrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die('Security check failed. Please go back, refresh the page, and try again.');
    }
}

/* ---------------------------------------------------------------------
 * Flash messages (survive exactly one redirect)
 * ------------------------------------------------------------------- */

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/* ---------------------------------------------------------------------
 * Formatting helpers
 * ------------------------------------------------------------------- */

function formatDate(?string $date): string
{
    if (!$date) return '-';
    return date('d M Y', strtotime($date));
}

function formatDateTime(?string $dt): string
{
    if (!$dt) return '-';
    return date('d M Y, h:i A', strtotime($dt));
}

function formatTime(?string $time): string
{
    if (!$time) return '-';
    return date('h:i A', strtotime($time));
}

function formatMoney(float $amount): string
{
    return 'Rs. ' . number_format($amount, 2);
}

function statusBadgeClass(string $status): string
{
    $map = [
        'Scheduled'   => 'bg-info text-dark',
        'Confirmed'   => 'bg-primary',
        'Completed'   => 'bg-success',
        'Cancelled'   => 'bg-danger',
        'Rescheduled' => 'bg-warning text-dark',
        'Unpaid'      => 'bg-danger',
        'Paid'        => 'bg-success',
        'Failed'      => 'bg-danger',
        'Pending'     => 'bg-warning text-dark',
        'active'      => 'bg-success',
        'inactive'    => 'bg-secondary',
        'Available'   => 'bg-success',
        'Occupied'    => 'bg-danger',
    ];
    return $map[$status] ?? 'bg-secondary';
}

/* ---------------------------------------------------------------------
 * Error logging (never show raw errors to the user)
 * ------------------------------------------------------------------- */

function logError(string $context, Throwable $e): void
{
    error_log(sprintf('[%s] %s: %s in %s:%d', date('Y-m-d H:i:s'), $context, $e->getMessage(), $e->getFile(), $e->getLine()));
}

/* ---------------------------------------------------------------------
 * ID / reference generators
 * ------------------------------------------------------------------- */

function generatePatientId(PDO $pdo): string
{
    $stmt = $pdo->query('SELECT COUNT(*) AS c FROM patients');
    $next = (int)$stmt->fetch()['c'] + 1;
    return 'PT-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}

function generateTransactionId(): string
{
    return 'TXN' . date('YmdHis') . strtoupper(bin2hex(random_bytes(3)));
}

/* ---------------------------------------------------------------------
 * Notifications
 * ------------------------------------------------------------------- */

function createNotification(PDO $pdo, int $userId, string $title, string $message, string $type = 'general'): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $title, $message, $type]);
}

function getUnreadNotificationCount(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetch()['c'];
}

function getRecentNotifications(PDO $pdo, int $userId, int $limit = 8): array
{
    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/* ---------------------------------------------------------------------
 * Audit logging
 * ------------------------------------------------------------------- */

function logAudit(PDO $pdo, ?int $userId, string $action, ?string $entity = null, ?int $entityId = null): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $action, $entity, $entityId, $ip]);
}

/* ---------------------------------------------------------------------
 * Appointment slot availability (real-time double-booking prevention)
 * ------------------------------------------------------------------- */

/**
 * Returns true if the given doctor has no active (non-cancelled) appointment
 * at the given date+time. Optionally excludes one appointment id (used when
 * rescheduling, so the appointment being moved doesn't block itself).
 */
function isSlotAvailable(PDO $pdo, int $doctorId, string $date, string $time, ?int $excludeAppointmentId = null): bool
{
    $sql = "SELECT COUNT(*) AS c FROM appointments
            WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
            AND status NOT IN ('Cancelled')";
    $params = [$doctorId, $date, $time];

    if ($excludeAppointmentId !== null) {
        $sql .= ' AND id != ?';
        $params[] = $excludeAppointmentId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetch()['c'] === 0;
}

/** Standard bookable time slots, 30-minute increments, for a given day. */
function getStandardTimeSlots(): array
{
    $slots = [];
    for ($h = 9; $h < 17; $h++) {
        foreach (['00', '30'] as $m) {
            $slots[] = sprintf('%02d:%s:00', $h, $m);
        }
    }
    return $slots;
}

/* ---------------------------------------------------------------------
 * Billing helper: auto-generate a bill after a consultation is completed
 * ------------------------------------------------------------------- */

function generateBillForAppointment(PDO $pdo, int $appointmentId, float $treatmentCharges = 0.00): int
{
    $stmt = $pdo->prepare('SELECT a.patient_id, d.consultation_fee
                            FROM appointments a JOIN doctors d ON a.doctor_id = d.id
                            WHERE a.id = ?');
    $stmt->execute([$appointmentId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Appointment not found for billing.');
    }

    $total = (float)$row['consultation_fee'] + $treatmentCharges;

    $stmt = $pdo->prepare('INSERT INTO bills (patient_id, appointment_id, consultation_fee, treatment_charges, total_amount, status)
                            VALUES (?, ?, ?, ?, ?, "Unpaid")');
    $stmt->execute([$row['patient_id'], $appointmentId, $row['consultation_fee'], $treatmentCharges, $total]);

    return (int)$pdo->lastInsertId();
}
