<?php
require 'gawm.php';

// Takes raw data from the request
$json = file_get_contents('php://input');
$encoded = $json;

// Converts it into a PHP object
$data = json_decode($json, true);

if ($data["state"] == "setup" && count( $data["players"] )<6)
{
    add_player($data);
}
else
{
    http_response_code(400);
}

// convert back to json
$encoded = json_encode($data);

//temp, return to caller
// todo: write to DB, return UID
header('Content-Type: application/json');
echo $encoded;

?>