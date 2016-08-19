<?php

define('DB_CONNECTION', 'mysql');
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_DATABASE', '');
define('DB_USERNAME', '');
define('DB_PASSWORD', '');

function get_pdo_connection() {

    $dbh = new PDO(DB_CONNECTION.':dbname='.DB_DATABASE.';host='.DB_HOST, DB_USERNAME, DB_PASSWORD);

    return $dbh;
}

?>
