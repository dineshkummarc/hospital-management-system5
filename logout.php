<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    try {
        $pdo = getDB();
        logAudit($pdo, currentUserId(), 'Logout', 'users', currentUserId());
    } catch (Throwable $e) {
        logError('logout_audit', $e);
    }
}

$_SESSION = [];
session_destroy();
redirect('/hms/login.php');
