<?php

require('./util_funcs.php');

$requested_table = $_GET['table'];

$existing_tables = get_existing_tables();

if (!in_array($requested_table, $existing_tables)) {
    die('Nonexistent table.');
}

$results = get_pdo_connection()->query('SHOW FULL COLUMNS FROM ' . $requested_table)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);

?>
