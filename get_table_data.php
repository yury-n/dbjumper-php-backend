<?php

require('./pdo_connection.php');
require('./allow_cross_origin.php');

$dbh = get_pdo_connection();

$existing_tables = array_column($dbh->query('SHOW TABLES')->fetchAll(), 0);

$requested_table = $_GET['table'];

if (!in_array($requested_table, $existing_tables)) {
    die('Invalid table name');
}

$sth = $dbh->query('SELECT * FROM ' . $requested_table . ' LIMIT 10');
$rows = $sth->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);

?>
