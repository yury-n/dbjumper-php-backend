<?php

require('./util_funcs.php');
require('./allow_cross_origin.php');

/*
 * GENERATE $query_schema FROM $_GET['query']
 */

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
    if (!empty($join_by) && count($join_by) == 1) {
        // if only one columnname specified in join part
        // it means it is the same columnname in the both tables we join
        $join_by[1] = $join_by[0];
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

/*
 * ASSIGN SHORT ALIASES TO JOINED TABLES, IF ALLOWABLE
 */
$short_aliases = array_map(function($table_schema) {
    return substr($table_schema['table'], 0, 1);
}, $query_schema);

$aliases_have_dupes = (count($short_aliases) != count(array_unique($short_aliases)));

$can_use_short_aliases = !$aliases_have_dupes;

if ($can_use_short_aliases) {
    foreach ($query_schema as $index => $table_schema) {
        $query_schema[$index]['table_alias'] = $short_aliases[$index];
    }
}
// first table shouldn't have an alias
unset($query_schema[0]['table_alias']);


/*
 * INTRODUCE FIRST TABLE/JOINED TABLES VARS FOR CONVENIENCE
 */
$first_table_schema = $query_schema[0];
$joined_tables_schemas = array_slice($query_schema, 1);


/*
 * REQUESTED TABLES VALIDATION
 */
$requested_tables = array_column($query_schema, 'table');
$existing_tables = get_existing_tables();
foreach ($requested_tables as $table) {
    if (!in_array($table, $existing_tables)) {
        die("Nonexistent table '$table'.");
    }
}

/*
 * FETCH ALL EXISTING COLUMNS FOR THE REQUESTED TABLES
 */
$existing_columns = get_existing_table_columns(false, $requested_tables);
$existing_columns_by_tables = [];
foreach ($existing_columns as $row) {
    $table = $row['TABLE_NAME'];
    $column = $row['COLUMN_NAME'];
    $existing_columns_by_tables[$table][] = $column;
}

/*
 * VALIDATE COLUMNS USED IN FILTERS
 */
foreach ($query_schema as $table_schema) {
    $table = $table_schema['table'];
    $filters = $table_schema['filters'];
    foreach ($filters as $key => $value) {
        if ($value === null) {
            die("Missing filter value for key '$key'.");
        }
        if (!in_array($key, $existing_columns_by_tables[$table])) {
            die("Nonexistent column '$key'.");
        }
    }
}

/*
 * VALIDATE COLUMNS USED IN JOINS
 */
$first_table = $first_table_schema['table'];

foreach ($joined_tables_schemas as $table_schema) {

    $joined_table = $table_schema['table'];
    $join_by = $table_schema['join_by'];

    if (empty($join_by)) {

        die("Joined tables should define keys to join by.");

    } else {

        $first_table_key = $join_by[0];
        $joined_table_key = $join_by[1];

        if (!in_array($first_table_key, $existing_columns_by_tables[$first_table])) {
            die("Nonexistent column '$first_table_key' used in join statement.");
        }

        if (!in_array($joined_table_key, $existing_columns_by_tables[$joined_table])) {
            die("Nonexistent column '$joined_table_key' used in join statement.");
        }
    }
}

/*
 * ATTACH COLUMNS TO FETCH TO THE $schema
 * IF COLUMN IS USED IN JOIN PLACE IT FIRST
 */
$first_table = $first_table_schema['table'];
$first_table_schema['columns'] = $existing_columns_by_tables[$first_table];

foreach ($joined_tables_schemas as $index => $table_schema) {
    $joined_table = $table_schema['table'];
    $join_by = $table_schema['join_by'];
    $joined_table_key = $join_by[1];
    $joined_tables_schemas[$index]['columns'] = $existing_columns_by_tables[$joined_table];
    usort($joined_tables_schemas[$index]['columns'], function($a, $b) use ($joined_table_key) {
        if ($a == $joined_table_key) {
            return -1;
        } else if ($b == $joined_table_key) {
            return 1;
        } else {
            return 0;
        }
    });
}

/*
 * BUILD SQL QUERY
 */

$pdo_args = [];
$pdo_query = "SELECT \n";
$first_table = $first_table_schema['table'];
foreach ($first_table_schema['columns'] as $index => $column) {
    $is_last_column = ($index == count($first_table_schema['columns']) - 1);
    $pdo_query .= "$first_table.$column as $column" . (!$is_last_column ? ', ' : "\n");
}
foreach ($joined_tables_schemas as $table_schema) {
    $joined_table = $table_schema['table'];
    $alias = !empty($table_schema['table_alias']) ? $table_schema['table_alias'] : $joined_table;
    $columns = array_map(function($column) use ($alias) {
        return ($alias ? $alias . '.' : '') . $column;
    }, $table_schema['columns']);
    $pdo_query .= ', ' . implode(', ', $columns) . "\n";
}
$pdo_query .= "FROM $first_table \n";
foreach ($joined_tables_schemas as $table_schema) {
    $joined_table = $table_schema['table'];
    $joined_table_alias = !empty($table_schema['table_alias']) ? $table_schema['table_alias'] : $joined_table;
    $join_by = $table_schema['join_by'];
    $first_table_key = $join_by[0];
    $joined_table_key = $join_by[1];
    $pdo_query .= "LEFT JOIN $joined_table as $joined_table_alias " .
                  "ON $first_table.$first_table_key = $joined_table_alias.$joined_table_key \n";
}
$pdo_query .= ' WHERE 1=1 ';
foreach ($query_schema as $table_schema) {
    $table = $table_schema['table'];
    $alias = !empty($table_schema['table_alias']) ? $table_schema['table_alias'] : $table;
    foreach ($table_schema['filters'] as $key => $value) {
        if (strpos($value, ',') === false) {
            $pdo_query .= "AND $alias.$key = ? ";
            $pdo_args[] = $value;
        } else {
            $values = explode(',', $value);
            $qMarks = str_repeat('?,', count($values) - 1) . '?';
            $pdo_query .= "AND $alias.$key IN ($qMarks) ";
            $pdo_args = array_merge($pdo_args, $values);
        }
    }
}
$pdo_query .= "\n LIMIT 20";

$sth = get_pdo_connection()->prepare($pdo_query);
$sth->execute($pdo_args);

$rows = $sth->fetchAll(PDO::FETCH_ASSOC);

//echo $pdo_query; die();

echo json_encode($rows);

?>