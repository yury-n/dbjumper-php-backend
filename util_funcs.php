<?php

define('DB_CONNECTION', 'mysql');
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_DATABASE', 'gifster');
define('DB_USERNAME', 'homestead');
define('DB_PASSWORD', 'secret');

function get_pdo_connection() {
    static $dbh;

    if (!isset($dbh)) {
        $dbh = new PDO(
            DB_CONNECTION.':dbname=' . DB_DATABASE . ';host=' . DB_HOST,
            DB_USERNAME,
            DB_PASSWORD,
            array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
        );
    }

    return $dbh;
}

function get_existing_tables() {
    return array_column(get_pdo_connection()->query('SHOW TABLES')->fetchAll(), 0);
}

function get_existing_table_columns($with_keys = false, $for_tables = []) {

    $sth = get_pdo_connection()->query('
        SELECT 
            TABLE_NAME, COLUMN_NAME' . ($with_keys ? ', COLUMN_KEY' : '') . ' 
        FROM 
            INFORMATION_SCHEMA.COLUMNS 
        WHERE 
            TABLE_SCHEMA = "' . DB_DATABASE . '"'
        . (!empty($for_tables) ? ' AND TABLE_NAME IN ("' . implode('","', $for_tables) . '")' : '')
    );

    return $sth->fetchAll(PDO::FETCH_ASSOC);
}

?>
