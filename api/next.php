<?php

// Takes raw data from the request
$json = file_get_contents('php://input');

// Converts it into a PHP object
$data = json_decode($json, true);

switch ($data["act"])
{
    case "0":
        complete_setup();
        break;
    case "Act1":
        act1_next_scene();
}

// convert back to json
$encoded = json_encode($data);

//temp, return to caller
// todo: write to DB, return UID
header('Content-Type: application/json');
echo $encoded;

?>