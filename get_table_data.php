<?php

require('./pdo_connection.php');
require('./allow_cross_origin.php');

$dbh = get_pdo_connection();

$existing_tables = array_column($dbh->query('SHOW TABLES')->fetchAll(), 0);

$query = $_GET['query'];
$exploded_query = explode('.', $query);
$requested_table = $exploded_query[0];

if (!in_array($requested_table, $existing_tables)) {
    die('Invalid table name.');
}

$pdo_query = 'SELECT * FROM ' . $requested_table;
if (!isset($exploded_query[1])) {
    $sth = $dbh->query($pdo_query . ' LIMIT 10');
} else {
    $pdo_query .= ' WHERE 1=1 ';
    $filtering = $exploded_query[1];

    $sth = $dbh->query('
        SELECT 
            COLUMN_NAME
        FROM 
            INFORMATION_SCHEMA.COLUMNS 
        WHERE
            TABLE_NAME = "'. $requested_table . '"
            AND TABLE_SCHEMA = "' . DB_DATABASE . '"'
    );
    $existing_columns = array_column($sth->fetchAll(), 0);

    $filtering_pairs = explode(';', $filtering);
    $pdo_args = [];
    foreach ($filtering_pairs as $filtering_pair) {
        list($key, $value) = explode('=', $filtering_pair);
        if (!in_array($key, $existing_columns)) {
            die('Invalid column name.');
        }
        if (!isset($value)) {
            die('Invalid query. Missing filter value.');
        }
        if (strpos($value, ',') === false) {
            $pdo_query .= "AND $key = ? ";
            $pdo_args[] = $value;
        } else {
            $values = explode(',', $value);
            $qMarks = str_repeat('?,', count($values) - 1) . '?';
            $pdo_query .= "AND $key IN ($qMarks) ";
            $pdo_args = array_merge($pdo_args, $values);
        }
    }
    $sth = $dbh->prepare($pdo_query);
    $sth->execute($pdo_args);
}

$rows = $sth->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);

?>