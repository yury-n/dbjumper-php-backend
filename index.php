<?php

require('./allow_cross_origin.php');

if (!isset($_GET['action'])) {
    die('Specify action.');
}

$action = $_GET['action'];

switch ($action) {
    case 'get_tables_listing':
    case 'get_table_data':
    case 'get_table_meta':
        require($action . '.php');
        break;
    default:
        die('Unknown action.');
}


?>
