<?php

define('DB_CONNECTION', 'mysql');
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_DATABASE', 'gifster');
define('DB_USERNAME', 'homestead');
define('DB_PASSWORD', 'secret');

function get_pdo_connection() {

    $dbh = new PDO(
        DB_CONNECTION.':dbname='.DB_DATABASE.';host='.DB_HOST,
        DB_USERNAME,
        DB_PASSWORD,
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
    );

    return $dbh;
}

?>
