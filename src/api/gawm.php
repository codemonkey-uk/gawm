<?php

define( 'gawm_player_id_victim', '0' );
define( 'gawm_vote_guilty', '1' );
define( 'gawm_vote_innocent', '2' );

require_once 'gawm_twist.php';
require_once 'gawm_setup.php';
require_once 'gawm_extrascene.php';
require_once 'gawm_firstbreak.php';

function gawm_new_game()
{
    $data = null;
    setup_setup($data);
    return $data;
}

// modifies the game data such that the specified player has played the requested card
// if that is in any way against the rules/structure, an exception is thrown
function gawm_play_detail(&$data, $player_id, $detail_type, $detail_card)
{
    if ($player_id !=gawm_player_id_victim && !array_key_exists($player_id,$data["players"]))
        throw new Exception('Invalid Player Id: '.$player_id);

    if ($player_id==gawm_player_id_victim)
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
    if ($player_id!=gawm_player_id_victim)
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
    if ($player_id==gawm_player_id_victim)
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

function gawm_vote(&$data, $player_id, $vote_value)
{
    if ($vote_value!=gawm_vote_guilty && $vote_value!=gawm_vote_innocent)
        throw new Exception('Invalid Vote Value: '.$vote_value);
        
    if (gawm_is_twist($data))
        throw new Exception('Invalid Scene for Votes');
        
    if (!array_key_exists($player_id,$data["players"]))
        throw new Exception('Invalid Player Id: '.$player_id);

    $player = &$data["players"][$player_id];
    $player["vote"]=$vote_value;
}

function gawm_is_epilogue(&$data)
{
    return $data["act"]==4;
}

// a normal scene is one where a detail is played and a token is awarded
// the extra scene is a normal scene,

function is_normal_scene(&$data)
{
    return !(
        gawm_is_setup($data) ||
        gawm_is_firstbreak($data) ||
        gawm_is_twist($data) ||
        gawm_is_lastbreak($data) ||
        gawm_is_epilogue($data));
}

function active_player_id(&$data)
{
    // check which players are active
    $active_players = array_filter(
        array_keys($data["players"]), 
        function($id){global $data; return gawm_is_player_active($data,$id);}
    );

    return current($active_players);
}

function gawm_scene_count($act, $player_count)
{
    switch ($act)
    {
        case 0:
            return 1;
        case 1:
            return $player_count+2;
        case 2:
            return $player_count+1;
        case 3:
            return 2*$player_count+1;
        case 4:
            return $player_count;
    }
}

function complete_normalscene(&$data)
{
    // details played
    $player_id = active_player_id($data);
    $player = &$data["players"][$player_id];
    
    if (gawm_player_has_details_left_to_play($data, $player_id))
    {
        throw new Exception('Player '.$player_id.' has Details still to Play in act '.$data["act"].' scene '.$data["scene"]);
    }
    // outcome agreed
    $tally = tally_votes($data);
    if ($tally[gawm_vote_innocent]==$tally[gawm_vote_guilty])
    {
        throw new Exception(
            'Players must agree on innocence/guilt: '.
            ($tally[gawm_vote_innocent] . ':' . $tally[gawm_vote_guilty])
        );
    }
    
    // draw a token of the type according the the vote
    $token = $tally[gawm_vote_innocent] > $tally[gawm_vote_guilty] ?
        "innocence" : "guilt";
    array_push( $player["tokens"][$token], array_pop($data["tokens"][$token]) );
    
    // TODO: support giving of 2nd token?
    
    clear_votes($data);
    
    $data["scene"]+=1;
    if ($data["scene"] == gawm_scene_count($data["act"], count($data["players"])))
    {
        $data["act"]+=1;
        $data["scene"]=0;        
    }
}

// advances gamestate to the next scene, if appropriate 
// throws an exception if more steps need to be taken before moving on
function gawm_next_scene(&$data)
{
    // normal scene, "can progress" checks
    if (is_normal_scene($data))
    {
        complete_normalscene($data);
    }
    else if (gawm_is_setup($data))
    {
        complete_setup($data);
    }
    else if (gawm_is_firstbreak($data))
    {
        complete_firstbreak($data);
    }
    else if (gawm_is_twist($data))
    {
        complete_twist($data);
    }
    else if (gawm_is_lastbreak($data))
    {
        // complete_lastbreak($data);
        $data["act"]+=1;
        $data["scene"]=0;           
    }
    else if (gawm_is_epilogue($data))
    {
        // complete_epilogue($data);
    }
    
    // scene advanced, above, requires set up?
    
    if (gawm_is_extrascene($data))
    {
        setup_extrascene($data);
    }
    else if (gawm_is_firstbreak($data))
    {
        setup_firstbreak($data);
    }
    else if (gawm_is_twist($data))
    {
        setup_twist($data);
    }
    else if (gawm_is_lastbreak($data))
    {
        // TODO: setup_lastbreak($data);
        $data["most_innocent"]=find_most_innocent_player($data);
    }
    else if (gawm_is_epilogue($data))
    {
        // TODO: setup_epilogue($data);
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
    if (gawm_is_setup($data))
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
        return $player_id==gawm_player_id_victim;
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

    // acts 1-3, 
    // but in act 3, treat scenes past player count as in the next act
    $act = $data["act"];
    if ($act==3 && $data["scene"]>=count($data["players"]))
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
    // active player, ignored except for breaks ties
    $active_player_id = active_player_id($data);
    
    // tally up votes from active players
    $result = [gawm_vote_innocent => 0, gawm_vote_guilty => 0];
    foreach( $data["players"] as $player_id => $player )
    {
        if ($player_id!=$active_player_id)
        {
            if (array_key_exists("vote", $player))
            {
                $result[$player["vote"]]++;
            }
        }
    }
    
    // tie break if necessery
    if ($result[gawm_vote_innocent]==$result[gawm_vote_guilty])
    {
        $ap = &$data["players"][$active_player_id];
        if (array_key_exists("vote", $ap))
        {
            $result[$ap["vote"]]++;
        }
        
    }
    
    return $result;
}

function clear_votes(&$data)
{
    foreach( $data["players"] as &$player )
    {
        if (array_key_exists("vote",$player))
        {
            unset($player["vote"]);
        }
    }
}

function gawm_is_lastbreak(&$data)
{
    return $data["act"]==3 && $data["scene"] == 2*count($data["players"]);
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