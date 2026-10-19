<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect(roleHomePage(currentRole()));
}
redirect('/hms/login.php');
