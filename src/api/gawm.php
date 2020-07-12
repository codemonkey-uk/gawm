<?php

function gawm_new_game()
{
    // component list
    $data = [
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

// modifies data, returns player_id
// name string sanatising, should be done in API layer
function gawm_add_player(&$data, $player_name)
{
    // only add players, up to 6, in act 0 (setup)
    if ($data["act"] > 0) 
    {
        throw new Exception('Trying to add players outside setup step.');
    }
    
    if (count($data["players"] ) > 6)
    {
        throw new Exception('Trying to add a 7th player.');
    }
    
    $new_player = [
        'name' => $player_name,
        'hand' => [],
        'play' => [],
        'tokens' => [
            'guilt' => [],
            'innocence' => []
        ]
    ];
    
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
    // numbered "0" to "3", in a central pile
    $data["tokens"]["innocence"] = array_merge($data["tokens"]["innocence"], range(0,3));
    $data["tokens"]["guilt"] = array_merge($data["tokens"]["guilt"], range(0,3));  
    
    return $player_id;
}

// modifies the game data such that the specified player has played the requested card
// if that is in any way against the rules/structure, an exception is thrown
function gawm_play_detail(&$data, $player_id, $detail_type, $detail_card)
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
        
    if (!gawm_is_detail_active($data, $detail_type))
        throw new Exception('Invalid Detail for Act');
        
    if (!gawm_is_player_active($data, $player_id))
        throw new Exception('Invalid Player ('.$player_id.') for Scene: '.$data["scene"]);
    
    $deck_from = &$player["hand"][$detail_type];
    if (!in_array($detail_card,$deck_from))
        throw new Exception('Detail Not Held');
    
    // skip this for victim, not a real player
    if ($player_id!=0)
    {
        if (!gawm_player_has_details_left_to_play($data, $player_id))
            throw new Exception('Insufficent Details for Remaining Acts: ');
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

// advances gamestate to the next scene, if appropriate 
// throws an exception if more steps need to be taken before moving on
function gawm_next_scene(&$data)
{
    $player_ids = array_keys($data["players"]);
    $player_count = count($data["players"]);

    switch ($data["act"])
    {
        case 0:
            complete_setup($data);
            break;
        case 1:

            if ($data["scene"] < $player_count)
            {
                $player_id = $player_ids[$data["scene"]];
                if (gawm_player_has_details_left_to_play($data, $player_id))
                {
                    throw new Exception('Player '.$player_id.' has Details still to Play.');
                }
            }
            
            $data["scene"]+=1;
            if (gawm_is_extrascene($data))
            {
                setup_extrascene($data);
            }
            if (gawm_is_firstbreak($data))
            {
                setup_firstbreak($data);
            }
            if ($data["scene"] > $player_count+1)
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
            if ($data["scene"] == $player_count+1)
            {
                complete_twist($data);
                
                // Move to Act III
                $data["act"]+=1;
                $data["scene"]=0;
            }
            break;    
        case 3:
            $data["scene"]+=1;
            if ($data["scene"] == 2*$player_count)
            {
                // TODO: Last Break
            }
            if ($data["scene"] == 2*$player_count+1)
            {
                // Move to Epilogue
                $data["act"]+=1;
                $data["scene"]=0;
            }
            break; 
        case 4:
            if ($data["scene"]+1<$player_count)
            {
                $data["scene"]+=1;
            }
            break;
    }
}

// draws until a player has 3 of each detail
function draw_player_details(&$data, &$player)
{
    $draws = array(
        "relationships" => 3,
        "objects" => 3,
        "motives" => 3,       
        "wildcards" => 3
    );
    draw_player_cards($data, $player, $draws);
}

// draws cards into the players hand 
// until they have the amounts specified by draws argument
function draw_player_cards(&$data, &$player, $draws)
{
    foreach ($draws as $deck => $count) 
    {
        if (!array_key_exists($deck,$player["hand"]))
        {
            $player["hand"][$deck] = array();
        }   
        while (count($player["hand"][$deck])<$count)
        {
            array_push( $player["hand"][$deck], array_pop($data["cards"][$deck]) );
        }
    }
}

function gawm_is_detail_active(&$data, $detail_type)
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

function gawm_is_player_active(&$data, $player_id)
{
    // all players are active in set up (act 0)
    if ($data["act"]==0)
    {
        return true;
    }
    
    // during the extra scene, only the victim is active
    if (gawm_is_extrascene($data))
    {
        return $data["victim"]["player_id"]==$player_id;
    }
    if (gawm_is_firstbreak($data))
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

function gawm_player_has_details_left_to_play(&$data, $player_id)
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

function gawm_is_extrascene(&$data)
{
    return $data["act"]==1 && $data["scene"] == count($data["players"]);
}

function gawm_is_firstbreak(&$data)
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
    $players = $data['players'];

    // Sort the array based on innocence scoring
    uasort($players, function($a, $b) {
        $a_points = array_sum($a['tokens']['innocence']);
        $a_count  = count($a['tokens']['innocence']);
        $b_points = array_sum($b['tokens']['innocence']);
        $b_count  = count($b['tokens']['innocence']);

        // Sort first by points, then by count
        if ($a_points > $b_points) {
            return 1;
        } elseif ($a_points == $b_points) {
            if ($a_count > $b_count) {
                return 1;
            } elseif ($a_count == $b_count) {
                // If tied, randomly shuffle positions
                return [-1,1][rand(0,1)];
            }
        }
        return -1;
    });

    // Maybe even show the players the whole list?
    //return array_keys($players);

    return array_key_last($players);
}

function find_guilty_player(&$data)
{
    $players = $data['players'];

    // Sort the array based on guilt scoring
    uasort($players, function($a, $b) {
        $a_points = array_sum($a['tokens']['guilt'])-array_sum($a['tokens']['innocence']);
        $a_innocence_count  = count($a['tokens']['innocence']);
        $a_guilt_count  = count($a['tokens']['guilt']);
        $b_points = array_sum($b['tokens']['guilt'])-array_sum($b['tokens']['innocence']);
        $b_innocence_count  = count($b['tokens']['innocence']);
        $b_guilt_count  = count($b['tokens']['guilt']);

        // Sort first by points, then by guilt count, then by smallest innocence count
        if ($a_points > $b_points) {
            return 1;
        } elseif ($a_points == $b_points) {
            if ($a_guilt_count > $b_guilt_count) {
                return 1;
            } elseif ($a_guilt_count == $b_guilt_count) {
                if ($a_innocence_count < $b_innocence_count) {
                    return 1;
                } elseif ($a_innocence_count == $b_innocence_count) {
                    // If tied, randomly shuffle positions
                    return [-1,1][rand(0,1)];
                }
            }
        }
        return -1;
    });

    // Maybe even show the players the whole list?
    //return array_keys($players);

    return array_key_last($players);
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