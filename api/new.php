<?php

// TODO: add rate-limiting

// Get the contents of the components file 
$strJsonFileContents = file_get_contents("components.json");

// convert to array 
$array = json_decode($strJsonFileContents, true);

// shuffle the cards
foreach ($array["cards"] as &$deck) {
    shuffle($deck);
}

// add players & add state
$array["players"] = array();
$array["state"] = "setup";

// convert back to json
$encoded = json_encode($array);

//temp, return to caller
// todo: write to DB, return UID
header('Content-type: text/html');
echo $encoded;

?>