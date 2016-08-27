<?php

require('./util_funcs.php');
require('./allow_cross_origin.php');

$rows = get_existing_table_columns(true);

$results = [];

foreach ($rows as $row) {

    $table = $row['TABLE_NAME'];
    $column = $row['COLUMN_NAME'];
    $is_indexed = ($row['COLUMN_KEY'] != '');

    $results[$table][$is_indexed ? 'indexed_columns' : 'nonindexed_columns'][] = $column;
}

echo json_encode($results);

?>
