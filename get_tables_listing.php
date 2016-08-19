<?php

require('./pdo_connection.php');
require('./allow_cross_origin.php');

$dbh = get_pdo_connection();
$sth = $dbh->query('
  SELECT 
      TABLE_NAME, COLUMN_NAME, COLUMN_KEY 
  FROM 
      INFORMATION_SCHEMA.COLUMNS 
  WHERE 
      TABLE_SCHEMA = "' . DB_DATABASE . '"'
);
$rows = $sth->fetchAll(PDO::FETCH_ASSOC);

$results = [];
foreach ($rows as $row) {

    $table = $row['TABLE_NAME'];
    $column = $row['COLUMN_NAME'];
    $is_indexed = ($row['COLUMN_KEY'] != '');

    $results[$table][$is_indexed ? 'indexed_columns' : 'nonindexed_columns'][] = $column;
}

echo json_encode($results);

?>
