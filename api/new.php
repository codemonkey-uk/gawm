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
echo $encoded;

?>