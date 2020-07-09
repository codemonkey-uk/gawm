<?php
require 'gawm.php';
require 'db.php';

// Takes raw data from the request
$json = file_get_contents('php://input');

// Converts it into a PHP object
$request = json_decode($json, true);
$game_id = $request["game_id"];

$data = null;
$link = load_for_edit($game_id, $data);

// only add players, up to 6, in act 0 (setup)
if ($data["act"] == 0 && count( $data["players"] )<6)
{
    add_player($data);
    complete_edit($link, $game_id, $data);
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