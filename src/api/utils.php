<?php

function new_gawm_data()
{
    // get component list
    $data = build_components();

    // shuffle the cards
    foreach ($data["cards"] as &$deck) {
        shuffle($deck);
    }

    // add players & add state
    $data["players"] = array();
    $data["notes"] = array();
    $data["act"] = 0;
    $data["scene"] = 0;
        
    return $data;
}

function build_components()
{
    $components = [
        "tokens" => [
            "guilt" => [],
            "innocence" => []
        ],
        "cards" => [
            "aliases" => range(0,15),
            "relationships" => range(0,29),
            "objects" => range(0,29),
            "motives" => range(0,29),
            "wildcards" => range(0,29),
            "murder_discovery" => range(0,5),
            "murder_cause" => range(0,9)
        ]
    ];

    return $components;
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

function player_has_details_left_to_play(&$data, $player_id)
{
    $player = &$data["players"][$player_id];

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
        return false;

    return true;
}

function tally_votes(&$data)
{
    $result = [];
    foreach( $data["players"] as $player )
    {
        if (array_key_exists("vote",$player))
            $result[$player["vote"]]++;
    }
    return result;
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

function find_most_innocent_player(&$data)
{
    $top_score = -1;
    $top_count = -1;
    $top_player_id = null;

    foreach($data['players'] as $player_id => $player) {
        $score = array_sum($player['tokens']['innocence']);
        $count = count($player['tokens']['innocence']);
        $is_top = false;
        // Better than top score
        $is_top = $score > $top_score;
        // Same as top score
        if ($score == $top_score) {
            // ...and more tokens
            $is_top = $count > $top_count;
            // ...and same number of tokens AND wins a coin flip
            // Not actually fair in the case of 3+ players drawing...
            if (!$is_top) {
                $is_top = ($count == $top_count && rand(0,1));
            }
        }

        if ($is_top) {
            $top_score = $score;
            $top_count = $count;
            $top_player_id = $player_id;
        }
    }

    return $top_player_id;
}

function find_guilty_player(&$data)
{
    $top_score = -100;
    $top_innocence_count = -1;
    $top_guilt_count = 100;
    $top_player_id = null;

    foreach($data['players'] as $player_id => $player) {
        $score = array_sum($player['tokens']['innocence'])-array_sum($player['tokens']['guilt']);
        $innocence_count = count($player['tokens']['innocence']);
        $guilt_count = count($player['tokens']['guilt']);
        $is_top = false;
        // Better than top score
        $is_top = $score > $top_score;
        // Same as top score
        if ($score == $top_score) {
            // ...and more innocence tokens
            $is_top = $innocence_count > $top_innocence_count;
            // ...and the same number of innocence tokens
            if ($innocence_count == $top_innocence_count) {
                $is_top = $guilt_count < $top_guilt_count;
                // ...and the same number of guilt tokens AND wins a coin flip
                // Not actually fair in the case of 3+ players drawing...
                if (!$is_top) {
                    $is_top = ($guilt_count == $top_guilt_count && rand(0,1));
                }
            }
        }

        if ($is_top) {
            $top_score = $score;
            $top_innocence_count = $innocence_count;
            $top_guilt_count = $guilt_count;
            $top_player_id = $player_id;
        }
    }

    return $top_player_id;
}

// fog of war logic
// in which we remove data going back to the client for a given player
function redact_for_player($data, $player_id)
{
    // cards left in the deck? cients dont need to know...
    unset($data["cards"]);
    // how the tokens got shuffled? 
    unset($data["tokens"]);    
    
    // todo (later) remove / reduce other players hands 
    // (keeping for now to make testing easier)
    return $data;
}

?>