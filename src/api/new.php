<?php
require 'gawm.php';
require 'db.php';

// TODO: add rate-limiting

// create default game object
$game = new_game();

// write to db
$game_id = save_new_game($game);

//temp, return BOTH id, and full JSON to caller
$http_result = array();
$http_result["game"]=$game;
$http_result["game_id"]=$game_id;

header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

echo json_encode( $http_result );
?>