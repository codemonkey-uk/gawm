<?php

// Takes raw data from the request
$json = file_get_contents('php://input');

// Converts it into a PHP object
$data = json_decode($json, true);

if ($data["state"] == "setup" && count( $data["players"] )<6)
{
    $new_player = array();   
    
    $draws = array(
        "alias" => 2,
        "rels" => 3,
        "object" => 3,
        "motives" => 3,       
        "wildcards" => 3
    );

    foreach ($draws as $deck => $count) 
    {
        $new_player[$deck] = array();
        for ($i=0;$i!=$count;++$i)
        {
            array_push( $new_player[$deck], array_pop($data["cards"][$deck]) );
        }
    }
    
    // create unique id for the new player
    $player_id = uniqid();
    while (array_key_exists($player_id,$data["players"]))
        $player_id = uniqid();    
    $data["players"][$player_id] = $new_player;
    
    // each player put four guilt tokens and four innocence tokens, 
    // numbered “0” to “3”, in a central pile
    $data["tokens"]["innocence"] = array_merge($data["tokens"]["innocence"], range(0,3));
    $data["tokens"]["guilt"] = array_merge($data["tokens"]["guilt"], range(0,3));    
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