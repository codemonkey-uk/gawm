<?php

// TODO: add rate-limiting

// Get the contents of the components file 
$json = file_get_contents("components.json");

// convert to array 
$data = json_decode($json, true);

// shuffle the cards
foreach ($data["cards"] as &$deck) {
    shuffle($deck);
}

// add players & add state
$data["players"] = array();
$data["state"] = "setup";

// convert back to json
$encoded = json_encode($data);

//temp, return to caller
// todo: write to DB, return UID
header('Content-Type: application/json');
echo $encoded;

?>