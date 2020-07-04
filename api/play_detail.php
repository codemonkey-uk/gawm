<?php
require 'gawm.php';

// Takes raw data from the request
$json = file_get_contents('php://input');
$encoded = $json;

// Converts it into a PHP object
$data = json_decode($json, true);

// TODO: check detail type can be played on current state

// TODO get these from GET
$player_id = $data["play_detail"]["player_id"];
$detail_type = $data["play_detail"]["detail_type"];
$detail_card = $data["play_detail"]["detail_card"];

try {
    play_detail($data, $player_id, $detail_type, $detail_card);
} catch (Exception $e) {
    http_response_code(400);
    echo 'Caught exception: ',  $e->getMessage(), "\n";
    return;
}

// convert back to json
$encoded = json_encode($data);

//temp, return to caller
// todo: write to DB, return UID
header('Content-Type: application/json');
echo $encoded;

?>