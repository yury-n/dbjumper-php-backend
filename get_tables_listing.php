<?php

require('./util_funcs.php');

$rows = get_existing_table_columns(true);

$results = [];

foreach ($rows as $row) {

    $table = $row['TABLE_NAME'];
    $column = $row['COLUMN_NAME'];
    $is_indexed = ($row['COLUMN_KEY'] != '');

    if (!isset($results[$table]['indexed_columns'])) {
        $results[$table]['indexed_columns'] = [];
    }
    if (!isset($results[$table]['nonindexed_columns'])) {
        $results[$table]['nonindexed_columns'] = [];
    }

    $column_group = ($is_indexed ? 'indexed_columns' : 'nonindexed_columns');

    $results[$table][$column_group][] = $column;
}

echo json_encode($results);

?>
