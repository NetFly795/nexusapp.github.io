<?php
define('CC_HOST', '127.0.0.1');
define('CC_DB',   'connected_snsd39_asdfnk93');
define('CC_USER', 'connected_snsd39_asdfnk93');
define('CC_PASS', ''); // Set locally; do not commit real credentials
define('CC_APP',  'Connected Center');

function ccDB(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host='.CC_HOST.';dbname='.CC_DB.';charset=utf8mb4',
            CC_USER, CC_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}
?>
