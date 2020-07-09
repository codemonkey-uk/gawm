<?php
require 'gawm.php';

// Takes raw data from the request
$json = file_get_contents('php://input');

// Converts it into a PHP object
$data = json_decode($json, true);

// execute any end-scene mechanics, advance to next scene/act
end_scene($data);

// convert back to json
$encoded = json_encode($data);

//temp, return to caller
// todo: write to DB, return UID
header('Content-Type: application/json');
echo $encoded;

?>