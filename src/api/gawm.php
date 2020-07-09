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
        "relationships" => 3,
        "objects" => 3,
        "motives" => 3,       
        "wildcards" => 3
    );
    draw_player_cards($data, $new_player, $draws);
}

function draw_player_cards(&$data, &$new_player, $draws)
{
    foreach ($draws as $deck => $count) 
    {
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
    
    // draw aliases
    draw_player_cards($data, $new_player, array("aliases" => 2) );
    
    // TODO: optional rule, draw details with alias during set up
    // draw_player_details($data, $new_player);
    
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
    
    // during the extra scene, only the victim is active
    if (is_extrascene($data))
    {
        return $data["victim"]["player_id"]==$player_id;
    }
    if (is_firstbreak($data))
    {
        return $player_id==0;
    }
    
    // normally, players are active during their scene
    $i = array_search($player_id, array_keys($data["players"]));
    
    if ($data["act"]==3)
    {   
        // in the 3rd act, 2 scenes per player
        return ($data["scene"]%count($data["players"]))==$i;
    }
    else
    {
        return $data["scene"]==$i;
    }
}

function play_detail(&$data, $player_id, $detail_type, $detail_card)
{
    if ($player_id !=0 && !array_key_exists($player_id,$data["players"]))
        throw new Exception('Invalid Player Id: '.$player_id);

    if ($player_id==0)
        $player = &$data["victim"];
    else
        $player = &$data["players"][$player_id];
        
    if (!array_key_exists($detail_type, $player["hand"]))
        throw new Exception('Invalid Detail Type: '.$detail_type);
    
    if (isset($player["hand"]["aliases"]) && $detail_type!="aliases")
        throw new Exception('An alias must be played first if any are held.');
        
    if (!is_detail_active($data, $detail_type))
        throw new Exception('Invalid Detail for Act');
        
    if (!is_player_active($data, $player_id))
        throw new Exception('Invalid Player ('.$player_id.') for Scene: '.$data["scene"]);
    
    $deck_from = &$player["hand"][$detail_type];
    if (!in_array($detail_card,$deck_from))
        throw new Exception('Detail Not Held');
    
    // skip this for victim, not a real player
    if ($player_id!=0)
    {
        // acts 1-3, but treat scenes past player count as in the next act
        $act = $data["act"];
        if ($data["scene"]>=count($data["players"]))
            $act += 1;
            
        // 4 acts, 3 detail cards held
        $c = 0;
        foreach($player["hand"] as $from)
            $c += count($from);
        $r = 4-$act;
        if ($act>0 && $c <= 3*$r)
            throw new Exception('Insufficent Details ('.$c.') for Remaining Acts: '.$r);
    }
    
    // make sure deck type exists in play
    if (!array_key_exists($detail_type,$player["play"]))
    {
        $player["play"][$detail_type] = array();
    }
        
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
    // tidy up the json, remove the empty deck type from the hand
    unset($player["hand"][$detail_type]);
    
    // custom victim details step
    if ($player_id==0)
    {
        // draw 2nd of remaining detail
        $opposite = array(
            "murder_cause" => "murder_discovery",
            "murder_discovery" => "murder_cause"
        );
        $other = $opposite[$detail_type];
        if (isset($player["hand"][$other]))
        {
            draw_player_cards($data, $player, array($other => 2) );
        }
    }
}

function twist_detail(&$data, $player_id, $detail_type, $detail_card)
{
    if (!is_twist($data))
        throw new Exception('Invalid Twist');
        
    if (!array_key_exists($player_id,$data["players"]))
        throw new Exception('Invalid Player Id: '.$player_id);

    $player = &$data["players"][$player_id];
        
    if (!array_key_exists($detail_type, $player["hand"]))
        throw new Exception('Invalid Detail Type');
    
    $deck_from = &$player["hand"][$detail_type];
    if (!in_array($detail_card,$deck_from))
        throw new Exception('Detail Not Held');
    
    // make sure deck type exists in twist
    if (!array_key_exists($detail_type,$player["twist"]))
    {
        $player["twist"][$detail_type] = array();
    }
        
    // move card from hand into twist
    $deck_to = &$player["twist"][$detail_type];
    array_push($deck_to, $detail_card);
    
    $key = array_search($detail_card, $deck_from);
    unset( $deck_from[$key] );
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
        if (isset($player["hand"]["aliases"]))
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
    
    // every player has 1 alias
    foreach( $data["players"] as &$player )
    {
        // draw any other details needed for Act I
        draw_player_details($data, $player);    
    }
    
    shuffle($data["tokens"]["innocence"]);
    shuffle($data["tokens"]["guilt"]);    

    $data["act"] = 1;
    $data["scene"] = 0;
}

function is_extrascene(&$data)
{
    return $data["act"]==1 && $data["scene"] == count($data["players"]);
}

function is_firstbreak(&$data)
{
    return $data["act"]==1 && $data["scene"] == count($data["players"])+1;
}

function is_twist(&$data)
{
    return $data["act"]==2 && $data["scene"] == count($data["players"]);
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
    $player["hand"]["aliases"]=array();
    while (count($data["cards"]["aliases"])>0)
    {
        array_push( $player["hand"]["aliases"], array_pop($data["cards"]["aliases"]) );
    }
    
    // TODO: return their innocence/guilt tokens to the pile
}

function setup_firstbreak(&$data)
{
    $data["victim"]["hand"]=array();
    
    $detail="murder_cause";
    $data["victim"]["hand"][$detail]=array();
    array_push( $data["victim"]["hand"][$detail], array_pop($data["cards"][$detail]) );
    $detail="murder_discovery";
    $data["victim"]["hand"][$detail]=array();
    array_push( $data["victim"]["hand"][$detail], array_pop($data["cards"][$detail]) );    
}

function setup_twist(&$data)
{
    // give every player has a twist "hand" to discard into
    foreach( $data["players"] as &$player )
    {
        // draw any other details needed for Act I
        $player["twist"]=array();
    }
}

function complete_twist(&$data)
{
    // for each player, 
    foreach( $data["players"] as &$player )
    {
        // draw details to replace discards
        $draws = array();
        foreach( $player["twist"] as $detail_type => $deck_from )
        {
            // count draws needed after discarding
            $draws[$detail_type] = count($deck_from) + count($player["hand"][$detail_type]);
            
            // put unused details back in the pack
            foreach($deck_from as $key => $id)
            {
                unset( $deck_from[$key] );
                array_unshift($data["cards"][$detail_type],$id);
            }
        }
        
        // draw backup
        draw_player_cards($data, $player, $draws);
        
        // clear out processed twist json
        unset($player["twist"]);
    }
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
            if (is_twist($data))
            {
                setup_twist($data);
            }
            if ($data["scene"] == count($data["players"])+1)
            {
                complete_twist($data);
                
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