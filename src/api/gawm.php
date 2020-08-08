<?php

define( 'gawm_player_id_victim', '0' );
define( 'gawm_vote_guilty', '1' );
define( 'gawm_vote_innocent', '2' );
define( 'gawm_allow_active_player_unilatteral_voting', false );

require_once 'gawm_twist.php';
require_once 'gawm_setup.php';
require_once 'gawm_extrascene.php';
require_once 'gawm_firstbreak.php';
require_once 'gawm_epilogue.php';

$gawm_opposites = array(
    "murder_cause" => "murder_discovery",
    "murder_discovery" => "murder_cause",
    "innocence" => "guilt",
    "guilt" => "innocence",
    gawm_vote_guilty => gawm_vote_innocent,
    gawm_vote_innocent => gawm_vote_guilty,
);

function gawm_new_game()
{
    $data = null;
    setup_setup($data);
    return $data;
}

function valid_player_id(&$data, $player_id)
{
    if ($player_id==gawm_player_id_victim)
        return true;
        
    return array_key_exists($player_id,$data["players"]);
}

// modifies the game data such that the specified player has played the requested card
// if that is in any way against the rules/structure, an exception is thrown
function gawm_play_detail(&$data, $player_id, $detail_type, $detail_card, $target_array)
{
    if (count_unassigned_tokens($data)>0)
        throw new Exception('Cannot play details with unassigned token left.');

    if (!valid_player_id($data, $player_id))
        throw new Exception('Invalid Player Id: '.$player_id);

    if (!gawm_is_detail_active($data, $detail_type))
        throw new Exception('Invalid Detail for Act');
        
    if (!gawm_is_player_active($data, $player_id))
        throw new Exception('Invalid Player ('.$player_id.') for Scene: '.$data["scene"]);
    
    if (!gawm_player_has_details_left_to_play($data, $player_id))
        throw new Exception('Insufficent Details for Remaining Acts.');    
    
    // hold a reference to the source player 
    if (($player_id==gawm_player_id_victim))
        $player = &$data["victim"];
    else
        $player = &$data["players"][$player_id];
    
    if (!array_key_exists($detail_type, $player["hand"]))
        throw new Exception('Invalid Detail Type: '.$detail_type);

    if (isset($player["hand"]["aliases"]) && $detail_type!="aliases")
        throw new Exception('An alias must be played first if any are held.');

    $deck_from = &$player["hand"][$detail_type];
    if (!in_array($detail_card,$deck_from))
        throw new Exception('Detail Not Held '.$detail_type.$detail_card);
        
    // legacy code support, if targets are not and array, make an array of one
    if (is_array($target_array)==false)
        $target_array = [$target_array];
    
    // targets should be unique
    $target_array = array_unique($target_array);
    
    // check validitiy of card type to target count
    $expected_targets = ($detail_type=='relationships') ? 2 : 1;
    if ($expected_targets!=count($target_array))
        throw new Exception("Expected ".$expected_targets." for Detail Type ".$detail_type.", found ".count($target_array));
    
    foreach ($target_array as $target_id)
    {
        if (!valid_player_id($data, $target_id))
            throw new Exception('Invalid Player Id: '.$target_id);
        
        // check validitiy of card type to target
        // * murder detail cards only go to murder victim targets
        if ($detail_type=='murder_discovery' && $target_id!=gawm_player_id_victim)
            throw new Exception('Invalid Use of Murder Victim Cards');
        if ($detail_type=='murder_cause' && $target_id!=gawm_player_id_victim)
            throw new Exception('Invalid Use of Murder Victim Cards');
        // * aliases only go to self
        if ($detail_type=='aliases' && $target_id!=$player_id)
            throw new Exception('Invalid Use of Alias Cards');
        
        if ($target_id==gawm_player_id_victim) 
            $target = &$data["victim"];
        else
            $target = &$data["players"][$target_id];
        
        if (isset($target["play"]["motives"]) && $detail_type=="motives")
            throw new Exception('Each player can only have 1 motive.');

        // move card from hand into play
        $target["play"][$detail_type][] = $detail_card;
    }

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
        global $gawm_opposites;
        $other = $gawm_opposites[$detail_type];
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

function gawm_give_token(&$data, $player_id, $token, $target_id)
{
    if (!array_key_exists($player_id,$data["players"]))
        throw new Exception('Invalid Player Id: '.$player_id);

    $player = &$data["players"][$player_id];

    if (!isset($player["unassigned_token"]))
        throw new Exception('No unassigned_token found on Player Id: '.$player_id);

    if ($token != $player["unassigned_token"])
        throw new Exception('Mismatch with unassigned_token on Player Id: '.$player_id);

    // passing in the victim id as target is taken to imply discard (give to no one)
    if ($target_id!=gawm_player_id_victim)
    {
        if ($target_id==$player_id)
            throw new Exception('A player cannot award themself the free token');

        if (!array_key_exists($target_id,$data["players"]))
            throw new Exception('Invalid Target Id: '.$target_id);

        $target = &$data["players"][$target_id];

        array_push( $target["tokens"][$token], array_pop($data["tokens"][$token]) );
    }

    unset($player["unassigned_token"]);

    if (count_unassigned_tokens($data)==0)
    {
        gawm_begin_scene($data);
    }
}

function swap3(&$x, &$y) 
{
    $tmp=$x;
    $x=$y;
    $y=$tmp;
}

// moving objects between players is a free action, and can happen at any time
function gawm_move_detail(&$data, $player_id, $detail_type, $detail_card, $target_id)
{
    if ($detail_type!='objects')
        throw new Exception('Only objects can be freely moved between players: '.$detail_type);

    // FROM player 
    if (!valid_player_id($data, $player_id))
        throw new Exception('Invalid Player Id: '.$player_id);
        
    if (($player_id==gawm_player_id_victim))
        $player = &$data["victim"];
    else
        $player = &$data["players"][$player_id];
            
    if (!array_key_exists($detail_type, $player["play"]))
        throw new Exception('Invalid Detail Type: '.$detail_type);

    $deck_from = &$player["play"][$detail_type];
    if (!in_array($detail_card,$deck_from))
        throw new Exception('Detail Not In Play '.$detail_type.$detail_card);

    // TOO player 
        
    if (!valid_player_id($data, $target_id))
        throw new Exception('Invalid Player Id: '.$target_id);
    
    if ($target_id==gawm_player_id_victim) 
        $target = &$data["victim"];
    else
        $target = &$data["players"][$target_id];
    
    // move card from one players hand to the other
    $target["play"][$detail_type][] = $detail_card;
    
    // remove the detail using swap-last-pop method 
    $key = array_search($detail_card, $deck_from);
    swap3( $deck_from[$key], $deck_from[array_key_last($deck_from)] );
    array_pop($deck_from);

    if (count($deck_from)==0)
        unset($player["play"][$detail_type]);
}

function gawm_record_accused(&$data, $player_id, $target_id)
{
    if (!array_key_exists($player_id,$data["players"]))
        throw new Exception('Invalid Player Id: '.$player_id);

    if ($player_id!=$data["most_innocent"])      
        throw new Exception('Only the Most Innocent can decide The Accused.');

    if (!gawm_is_lastbreak($data))
        throw new Exception('The Accused can only be set during the Last Break.');
    
    if ($target_id==gawm_player_id_victim)
        throw new Exception('The Accused can not be the Victim.');
        
    if ($target_id==$data["most_innocent"])      
        throw new Exception('The Most Innocent can not Accuse Themselves.');
    
    $data["the_accused"]=$target_id;
}

function count_unassigned_tokens(&$data)
{
    return count( array_filter(
        $data["players"],
        function($p){ return isset($p["unassigned_token"]); }
    ));
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
            'Players must decide on innocence/guilt: '.
            ($tally[gawm_vote_innocent] . ':' . $tally[gawm_vote_guilty])
        );
    }

    // draw a token of the type according the the vote
    $token = $tally[gawm_vote_innocent] > $tally[gawm_vote_guilty] ?
        "innocence" : "guilt";
    array_push( $player["tokens"][$token], array_pop($data["tokens"][$token]) );

    global $gawm_opposites;
    $player["unassigned_token"]=$gawm_opposites[$token];

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
function gawm_request_next_scene(&$data, $player_id)
{
    // record who requested next scene:
    if (!isset($data['next']))
        $data['next']=[];
    if (!in_array($player_id,$data['next']))
        array_push($data['next'],$player_id);
    
    // check if everyone active has requested it:
    foreach(array_keys($data["players"]) as $id)
    {
        if (gawm_is_player_active($data,$id))
        {
            // exit early, report waiting for player to calling code:
            if (!in_array($id,$data['next']))
                return $id;
        }
    }
    
    // okay, good to go:
    gawm_go_next_scene($data);
    
    // done, clear concent:
    unset($data['next']);
    
    return '';
}


// check all active players have requested next scene
function gawm_go_next_scene(&$data)
{
    if (count_unassigned_tokens($data)>0)
        throw new Exception('Cannot advance to next scene with unassigned token left.');
        
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
        if (isset($data["the_accused"])==false)
            throw new Exception('Cannot advance to next scene without an Accusation of Guilt.');
            
        $data["act"]+=1;
        $data["scene"]=0;
    }
    else if (gawm_is_epilogue($data))
    {
        complete_epilogue($data);
    }

    // scene advanced, above, requires set up?
    if (count_unassigned_tokens($data)==0)
    {
        gawm_begin_scene($data);
    }
}

function gawm_begin_scene(&$data)
{
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
        $data["most_innocent"]=current(gawm_list_players_by_most_innocent($data));
    }
    else if (gawm_is_epilogue($data))
    {
        setup_epilogue($data);
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
    // all players are active in set up and twist phases
    if (gawm_is_setup($data) || gawm_is_twist($data))
    {
        return $player_id!=gawm_player_id_victim;
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
    if (gawm_is_lastbreak($data))
    {
        return $player_id==$data["most_innocent"];
    }
    if ($player_id==gawm_player_id_victim)
    {
        return false;
    }

    // game over, after the epilogue no one is active
    if ($data["act"]==5)
    {
        return false;
    }

    // normally, players are active during their scene
    $player_index = array_search($player_id, array_keys($data["players"]));
    $active_index = $data["scene"];
    
    if ($data["act"]==3)
    {
        // in the 3rd act, 2 scenes per player
        $active_index = $active_index%count($data["players"]);
    }
    else if (gawm_is_epilogue($data))
    {
        $active_index = $data["epilogue_order"][$active_index];
    }
    
    return $active_index ==$player_index;
}

function gawm_player_has_details_left_to_play(&$data, $player_id)
{
    if ($player_id==gawm_player_id_victim)
    {
        return isset($data['victim']['hand']) && count($data['victim']['hand'])>0;
    }
        
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

    // act 0 (set up), player gets 2 alias, and must play 1 & discard 1
    return count($player['hand'])>0;
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
    
    // (optionally) prevent active players breaking a tie where no one else voted
    if (gawm_allow_active_player_unilatteral_voting ||
        $result[gawm_vote_innocent]+$result[gawm_vote_guilty]>0)
    {
        // tie break if necessery
        if ($result[gawm_vote_innocent]==$result[gawm_vote_guilty])
        {
            $ap = &$data["players"][$active_player_id];
            if (array_key_exists("vote", $ap))
            {
                $result[$ap["vote"]]++;
            }
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

function gawm_get_player_names(&$data)
{
    $names = array();
    foreach ($data["players"] as $player) {
        $names[] = $player['name'];
    }
    return $names;
}

function gawm_list_players_by_most_innocent(&$data)
{
    $players = $data['players'];

    // Sort the array based on innocence scoring
    uasort($players, function($a, $b) {
        $a_points = array_sum($a['tokens']['innocence']);
        $a_count  = count($a['tokens']['innocence']);
        $b_points = array_sum($b['tokens']['innocence']);
        $b_count  = count($b['tokens']['innocence']);

        // Sort first by points, then by count
        if ($a_points < $b_points) {
            return 1;
        } elseif ($a_points == $b_points) {
            if ($a_count < $b_count) {
                return 1;
            } elseif ($a_count == $b_count) {
                // If tied, randomly shuffle positions
                return [-1,1][rand(0,1)];
            }
        }
        return -1;
    });

    // Index 0 is the ID of the most innocent player
    return array_keys($players);
}

function gawm_list_players_by_most_guilty(&$data)
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
        if ($a_points < $b_points) {
            return 1;
        } elseif ($a_points == $b_points) {
            if ($a_guilt_count < $b_guilt_count) {
                return 1;
            } elseif ($a_guilt_count == $b_guilt_count) {
                if ($a_innocence_count > $b_innocence_count) {
                    return 1;
                } elseif ($a_innocence_count == $b_innocence_count) {
                    // If tied, randomly shuffle positions
                    return [-1,1][rand(0,1)];
                }
            }
        }
        return -1;
    });

    // Index 0 is the ID of the guilty player
    return array_keys($players);
}

// fog of war logic
// in which we remove data going back to the client for a given player
function redact_for_player($data, $player_id)
{
    // redact any "hidden" information 
    // (face down tokens, cards in other players hands)

    // cards left in the deck? cients dont need to know...
    unset($data["cards"]);
    // how the tokens got shuffled?
    unset($data["tokens"]);
    
    // suplementals for victim
    if (isset($data['victim']))
    {
        $data['victim']['active'] = gawm_is_player_active($data, gawm_player_id_victim);
        $data['victim']['details_left_to_play'] = gawm_player_has_details_left_to_play($data, gawm_player_id_victim);
    }
    
    if (isset($data["players"])==false)
        throw new Exception( "Malformed game, contains no players: ". json_encode($data) );
    
    // hide details of other players hands
    foreach( $data["players"] as $id => &$player )
    {
        // inject sumplemental / derived info to simplify js and reduce duplication
        $player['active'] = gawm_is_player_active($data, $id);
        $player['details_left_to_play'] = gawm_player_has_details_left_to_play($data, $id);
        
        $redact = [];
        
        // tokens are redacted, until the epilogue
        if (gawm_is_lastbreak($data) && count_unassigned_tokens($data)==0)
        {
            // during the last break, the innocence tokens should be shown, 
            // and the guilt tokens remain hidden
            foreach($player["tokens"]["guilt"] as $key => $value)
                $player["tokens"]["guilt"][$key] = -1;
        }
        else if (!gawm_is_epilogue_inprogress_or_complete($data))
        {
            array_push($redact, "tokens");
        }
        
        if ($id!=$player_id)
        {
            array_push($redact, "hand");
            unset($player["vote"]);
        }
            
        foreach($redact as $t)
            foreach($player[$t] as &$deck)
                foreach($deck as $key => $value)
                    $deck[$key] = -1;
    }
    
    return $data;
}

?>
