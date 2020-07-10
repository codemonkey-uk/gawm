<?php
require 'gawm.php';
require 'db.php';

// Takes raw data from the request
$json = file_get_contents('php://input');

// Converts it into a PHP object
$request = json_decode($json, true);

$game_id = $request["game_id"];
$player_id = $request["player_id"];
// $detail_type = $request["detail_type"];
$vote_value = $request["detail_card"];

$data = null;
$link = load_for_edit($game_id, $data);

try {
    record_vote($data, $player_id, $vote_value);
    complete_edit($link, $game_id, $data);
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