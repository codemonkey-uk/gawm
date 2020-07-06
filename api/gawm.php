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
    $data["act"] = 0;
    $data["scene"] = 0;
    
    return $data;
}

function draw_player_details(&$data, &$new_player)
{
    $draws = array(
        "aliases" => 2,
        "relationships" => 3,
        "objects" => 3,
        "motives" => 3,       
        "wildcards" => 3
    );
    
    foreach ($draws as $deck => $count) 
    {
        if (!array_key_exists($deck,$new_player["play"]))
        {
            $new_player["play"][$deck] = array();
        }
        if (!array_key_exists($deck,$new_player["hand"]))
        {
            $new_player["hand"][$deck] = array();
        }   
        while (count($new_player["hand"][$deck])<$count)
        {
            array_push( $new_player["hand"][$deck], array_pop($data["cards"][$deck]) );
        }
    }
}

function add_player(&$data)
{
    $new_player = array();   
    
    $new_player["hand"] = array();
    $new_player["play"] = array();
    
    draw_player_details($data, $new_player);
    
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

function is_detail_active(&$data, $detail_type)
{
    // players should always be able to play their alias
    // in practice this happens at two points: 
    // - during set up, and during the extra scene
    if ($detail_type=="aliases")
    {
        return true;
    }
    
    // Motives cannot be played until after the murder, starting in Act II
    if ($data["act"]==1)
    {
        return $detail_type!="motives";
    }
    else if ($data["act"]>1)
    {
        return true;
    }
    
    return false;
}

function is_player_active(&$data, $player_id)
{
    // all players are active in set up (act 0)
    if ($data["act"]==0)
    {
        return true;
    }
    
    // during the extra scene, the who was the victim is active
    if (is_extrascene($data))
    {
        return $data["victim"]["player_id"]==$player_id;
    }
    
    // normally, players are active during their scene
    $i = array_search($player_id, array_keys($data["players"]));
    return $data["scene"]==$i;
}

function play_detail(&$data, $player_id, $detail_type, $detail_card)
{
    if (!array_key_exists($player_id,$data["players"]))
        throw new Exception('Invalid Player Id');

    $player = &$data["players"][$player_id];
    if (!array_key_exists($detail_type, $player["hand"]))
        throw new Exception('Invalid Detail Type');
    
    if (count($player["hand"]["aliases"])>0 && $detail_type!="aliases")
        throw new Exception('An alias must be played first if any are held.');
        
    if (!is_detail_active($data, $detail_type))
        throw new Exception('Invalid Detail for Act');
        
    if (!is_player_active($data, $player_id))
        throw new Exception('Invalid Player for Scene');
    
    $deck_from = &$player["hand"][$detail_type];
    if (!in_array($detail_card,$deck_from))
        throw new Exception('Detail Not Held');
    
    // 4 acts, 3 detail cards held
    $c = 0;
    foreach($player["hand"] as $from)
        $c += count($from);
    $r = 4-$data["act"];
    if ($data["act"]>0 && $c <= 3*$r)
        throw new Exception('Insufficent Details ('.$c.') for Remaining Acts: '.$r);
    
    // move card from hand into play
    $deck_to = &$player["play"][$detail_type];
    array_push($deck_to, $detail_card);
    
    $key = array_search($detail_card, $deck_from);
    unset( $deck_from[$key] );

    // put unused details back in the deck
    foreach($deck_from as $key => $id)
    {
        unset( $deck_from[$key] );
        array_unshift($data["cards"][$detail_type],$id);
    }
}

function complete_setup(&$data)
{
    // at least 4 players
    if (count($data["players"])<4)
    {
        http_response_code(400);
        throw new Exception('Must have 4 Players to Complete Setup.');
    }
 
    // every player has 1 alias
    foreach( $data["players"] as $player )
    {
        // 0 alias in hand
        if (count($player["hand"]["aliases"])!=0)
        {
            http_response_code(400);
            throw new Exception('Alias detail still in hand.');
        }
        // 1 alias in play
        if (count($player["play"]["aliases"])!=1)
        {
            http_response_code(400);
            throw new Exception('Alias detail missing from play.');
        }
    }

    shuffle($data["tokens"]["innocence"]);
    shuffle($data["tokens"]["guilt"]);    

    $data["act"] = 1;
    $data["scene"] = 0;
}

function is_extrascene(&$data)
{
    return $data["scene"] == count($data["players"]);
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
    
    // give the player who lost their alias, the whole alias deck to choose from
    while (count($data["cards"]["aliases"])>0)
    {
        array_push( $player["hand"]["aliases"], array_pop($data["cards"]["aliases"]) );
    }
    
    // TODO: return their innocence/guilt tokens to the pile
}

function is_firstbreak(&$data)
{
    return $data["scene"] == count($data["players"])+1;
}

function setup_firstbreak(&$data)
{
    $data["victim"]["hand"]=array();
    
    $detail="murder_cause";
    array_push( $new_player["hand"][$detail], array_pop($data["cards"][$detail]) );
    $detail="murder_discovery";
    array_push( $new_player["hand"][$detail], array_pop($data["cards"][$detail]) );    
}

function end_scene(&$data)
{
    switch ($data["act"])
    {
        case 0:
            complete_setup($data);
            break;
        case 1:
            // TODO: check detail selected?
            $data["scene"]+=1;
            if (is_extrascene($data))
            {
                setup_extrascene($data);
            }
            if (is_firstbreak($data))
            {
                setup_firstbreak($data);
            }
            if ($data["scene"] > count($data["players"])+1)
            {
                // Move to Act II
                $data["act"]+=1;
                $data["scene"]=0;
            }
            break;
        case 2:
            $data["scene"]+=1;
            if ($data["scene"] == count($data["players"]))
            {
                // TODO: Second Break
            }
            if ($data["scene"] == count($data["players"])+1)
            {
                // Move to Act III
                $data["act"]+=1;
                $data["scene"]=0;
            }
            break;    
        case 3:
            $data["scene"]+=1;
            if ($data["scene"] == 2*count($data["players"]))
            {
                // TODO: Last Break
            }
            if ($data["scene"] == 2*count($data["players"])+1)
            {
                // Move to Epilogue
                $data["act"]+=1;
                $data["scene"]=0;
            }
            break; 
        case 4:
            if ($data["scene"]+1<count($data["players"]))
            {
                $data["scene"]+=1;
            }
            break;
    }
}

?>