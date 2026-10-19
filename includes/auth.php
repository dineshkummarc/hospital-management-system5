<?php
/**
 * Session bootstrap + authentication / authorization helpers.
 *
 * Every protected page starts with:
 *     require_once __DIR__ . '/../includes/auth.php';
 *     requireRole('patient');   // or 'doctor' / 'admin'
 *
 * This guarantees: a session is active, the user is logged in, and the
 * user's role matches the page's role -- otherwise the request is
 * redirected/blocked, which is how we prevent direct URL access to
 * unauthorized pages.
 */

// Harden session cookie settings before starting the session.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/functions.php';

/** Is any user currently logged in? */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/** The logged-in user's role, or null. */
function currentRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

/** The logged-in user's id, or null. */
function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Enforce that the current user is logged in AND has the given role.
 * Anything else (not logged in, wrong role, deactivated account) is
 * redirected away -- this is what stops direct URL access to another
 * role's pages.
 */
function requireRole(string $role): void
{
    if (!isLoggedIn()) {
        setFlash('warning', 'Please log in to continue.');
        redirect('/hms/login.php');
    }

    if (currentRole() !== $role) {
        setFlash('danger', 'You are not authorized to access that page.');
        redirect(roleHomePage(currentRole()));
    }
}

/** Where should each role land after login / when denied access elsewhere? */
function roleHomePage(?string $role): string
{
    switch ($role) {
        case 'patient': return '/hms/patient/dashboard.php';
        case 'doctor':  return '/hms/doctor/dashboard.php';
        case 'admin':   return '/hms/admin/dashboard.php';
        default:        return '/hms/login.php';
    }
}

/**
 * Look up the patients.id / doctors.id row for the currently logged-in
 * user. Pages use this instead of trusting any id passed in the URL.
 */
function currentPatientRecord(PDO $pdo): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM patients WHERE user_id = ?');
    $stmt->execute([currentUserId()]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function currentDoctorRecord(PDO $pdo): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM doctors WHERE user_id = ?');
    $stmt->execute([currentUserId()]);
    $row = $stmt->fetch();
    return $row ?: null;
}
