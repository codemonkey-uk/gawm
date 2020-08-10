<?php
//
// Implementation of functions implementing Extra Scene
//
// Public, called by API:
// - gawm_is_extrascene - returns true if it is Act 0
//
// Implementation, used during Next Scene:
// - setup_extrascene - to be called at the start of the scene,
// - complete_extrascene - to be called at the end of the scene
//

function gawm_is_extrascene(&$data)
{
    return $data["act"]==1 && $data["scene"] == count($data["players"]);
}

function setup_extrascene(&$data)
{
    // select victim
    $player_id = array_rand($data["players"]);
    $player = &$data["players"][$player_id];

    // create victim object, and record which player it was
    $data["victim"]=array();
    $data["victim"]["player_id"]=$player_id;

    // move all played details from the player to the victim container
    $data["victim"]["play"]=$player["play"];
    $player["play"]=array();

    // re-draw to replace other details played in Act I
    draw_player_details($data,$player);

    // give the player who lost their alias the whole alias deck to choose from
    $player["hand"]["aliases"]=array();
    while (count($data["cards"]["aliases"])>0)
    {
        array_push( $player["hand"]["aliases"], array_pop($data["cards"]["aliases"]) );
    }

    // return their innocence/guilt tokens to the pile
    foreach( $player["tokens"] as $token_type => &$token_array )
    {
        while (count($token_array)>0)
        {
            array_push( $data["tokens"][$token_type], array_pop($token_array) );
        }
    }
    
    // move player notes to victim player
    if (array_key_exists("player",$data["notes"]))
    {
        $data["notes"]["player"][gawm_player_id_victim] = $data["notes"]["player"][$player_id];
        unset($data["notes"]["player"][$player_id]);
    }
}

function complete_extrascene(&$data)
{
    // kept for consistancy, nothing extra to do here
}

?>
