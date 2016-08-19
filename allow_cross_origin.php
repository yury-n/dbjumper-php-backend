<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods", "GET,HEAD,OPTIONS,POST,PUT");
header("Access-Control-Allow-Headers", "Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    die('OK');
}

?>
