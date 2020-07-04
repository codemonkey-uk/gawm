<?php

// Takes raw data from the request
$json = file_get_contents('php://input');

// Converts it into a PHP object
$data = json_decode($json, true);

function complete_setup()
{
    global $data;
    // at least 4 players
    if (count($data["players"])<4)
    {
        http_response_code(400);
        return;
    }
    
    /*
    // every player has 1 alias
    foreach( $data["players"] as $player )
    {
        // 0 alias in hand
        if (count($player["hand"]["alias"])!=0)
        {
            http_response_code(400);
            return;
        }
        // 1 alias in play
        if (count($player["play"]["alias"])!=1)
        {
            http_response_code(400);
            return;
        }
    }
    */
    
    shuffle($data["tokens"]["innocence"]);
    shuffle($data["tokens"]["guilt"]);    
    $data["state"] = "Act1";
    $data["scene"] = 1;
}

function act1_next_scene()
{
    global $data;
    
    if ($data["scene"] < count($data["players"]))
    {
        // end-scene state, collect votes
        // need to assign innocence/guilt token
        $data["scene"] = $data["scene"]+1;
        return;
    }
    
    $data["state"] = "ExtraScene";
    $data["scene"] = 1;
    
    // Random select victim from players, set Victim Alias
    // Give victim player all remaining alias
    // ExtraScene cannot progress until they discard down to 1
}

switch ($data["state"])
{
    case "setup":
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