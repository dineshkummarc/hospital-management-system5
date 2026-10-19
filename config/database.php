<?php
/**
 * Database connection configuration.
 *
 * Edit the constants below to match your local XAMPP / MySQL setup.
 * By default XAMPP's MySQL root user has no password.
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'hms_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO connection (created once per request).
 * Uses prepared statements + exceptions on error throughout the app.
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Never expose raw DB errors to the user (see functions.php logError()).
            error_log('DB Connection Error: ' . $e->getMessage());
            die('A system error occurred. Please try again later or contact the administrator.');
        }
    }

    return $pdo;
}
