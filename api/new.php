<?php
require 'gawm.php';

// TODO: add rate-limiting

// create default game object
$data = new_game();

// convert back to json
$encoded = json_encode($data);

//temp, return to caller
// todo: write to DB, return UID
header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

echo $encoded;

?>