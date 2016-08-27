<?php

require('./util_funcs.php');
require('./allow_cross_origin.php');

$query = $_GET['query'];

$tables_and_related_parts = explode('+', $query);
/*
 * $query_schema example:
 * [
 *     ['table' => 'users', 'filters' => ['id' => '1,2,3', 'name' => 'John']]
 *     ['table' => 'cart_orders', 'join_by' => ['id', 'userid'], 'filters' => [...]]
 * ]
*/
$query_schema = [];
foreach ($tables_and_related_parts as $table_and_related_parts) {

    if (strpos($table_and_related_parts, '(') !== false) { // from syntax "+users(userid=id)"
        // joined table
        $table = explode('(', $table_and_related_parts)[0];
        preg_match('/^([^(]+)\(([^)]+)\)\.?(.+)?/', $table_and_related_parts, $matches);
        // e.g. products(id=userid).productname=bottle;status=3
        // $matches[1] = 'products';
        // $matches[2] = 'id=userid';
        // $matches[3] = 'productname=bottle;status=3';
        $table = $matches[1];
        $join_by = isset($matches[2]) ? explode('=', $matches[2]) : [];
        $filters = isset($matches[3]) ? _parse_filter_part_into_assoc_array($matches[3]) : [];
    } else {
        // first table in the query
        $parts = explode('.', $table_and_related_parts);
        $table = $parts[0];
        $join_by = null;
        $filters = isset($parts[1]) ? _parse_filter_part_into_assoc_array($parts[1]) : [];
    }

    $query_schema[] = [
        'table' => $table,
        'join_by' => $join_by,
        'filters' => $filters
    ];
}

/*
 * e.g. converts
 * "productname=bottle;status=3"
 * into
 * [productname => 'bottle', status => 3]
 */
function _parse_filter_part_into_assoc_array($filter_part) {
    $filter_pairs = explode(';', $filter_part);
    $filters_assoc = [];
    foreach ($filter_pairs as $filter_pair) {
        $parts = explode('=', $filter_pair);
        $key = $parts[0];
        $value = isset($parts[1]) ? $parts[1] : null;
        $filters_assoc[$key] = $value;
    }
    return $filters_assoc;
}

$requested_tables = array_column($query_schema, 'table');

$existing_tables = get_existing_tables();

foreach ($requested_tables as $table) {
    if (!in_array($table, $existing_tables)) {
        die("Nonexistent table '$table'.");
    }
}

$existing_columns = get_existing_table_columns(false, $requested_tables);
$existing_columns_by_tables = [];
foreach ($existing_columns as $row) {
    $table = $row['TABLE_NAME'];
    $column = $row['COLUMN_NAME'];
    $existing_columns_by_tables[$table][] = $column;
}

foreach ($query_schema as $item) {
    $table = $item['table'];
    $filters = $item['filters'];
    foreach ($filters as $key => $value) {
        if ($value === null) {
            die("Missing filter value for key '$key'.");
        }
        if (!in_array($key, $existing_columns_by_tables[$table])) {
            die("Nonexistent column '$key'.");
        }
    }
}

$first_table = array_shift($query_schema);
$joins = $query_schema;

foreach ($joins as $schema_item) {
    if ($schema_item['join_by'] !== null) {
        
    }
}

//var_dump($query_schema);
var_dump($requested_tables);

die();

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