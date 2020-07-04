<?php

function new_game()
{
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
    
    return $data;
}

function add_player(&$data)
{
    $new_player = array();   
    
    $draws = array(
        "alias" => 2,
        "rels" => 3,
        "object" => 3,
        "motives" => 3,       
        "wildcards" => 3
    );
    
    $new_player["hand"] = array();
    $new_player["play"] = array();
    
    foreach ($draws as $deck => $count) 
    {
        $new_player["play"][$deck] = array();
        $new_player["hand"][$deck] = array();
        for ($i=0;$i!=$count;++$i)
        {
            array_push( $new_player["hand"][$deck], array_pop($data["cards"][$deck]) );
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
    
    return $player_id;
}

function play_detail(&$data, $player_id, $detail_type, $detail_card)
{

    if (!array_key_exists($player_id,$data["players"]))
        throw new Exception('Invalid Player Id');

    $player = $data["players"]["player_id"];
    if (!array_key_exists($detail_type, $player["hand"]))
        throw new Exception('Invalid Detail Type');

    $deck_from = $player["hand"][$detail_type];
    if (!in_array($detail_card,$deck_from))
        throw new Exception('Detail Not Held');
    
    // move card from hand into play
    $deck_to = $player["play"][$detail_type];
    array_push($deck_to, $detail_type);

    $key = array_search($detail_type, $deck_from);
    unset( $deck_from[$key] );

    // TODO, return other details of same type to table deck
    // TODO, support playing cards into other peoples hands
}

?>