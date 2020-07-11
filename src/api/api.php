<?php
require_once 'gawm.php';
require_once 'db.php';

// TODO: auto-add the first player ... needs $player_name set up
function _api_new()
{
    // get component list
    $data = gawm_new_game();
    $game_id = save_new_game($data);
    $player_id = 0;
    
    // Todo: I think I want ALL reponses to come back in this format...
    return [
        'game' => redact_for_player($data, $player_id),
        'game_id' => $game_id,
        'player_id' => $player_id
    ];
}

function _api_add_player(&$data, $player_name)
{
    // Minimal input filtering on player name
    $player_name = trim($player_name);
    if ($player_name == '')
    {
        throw new Exception('No player name supplied');
    }
    $player_name = htmlentities($player_name);
    $player_name = substr($player_name, 0, 40);
    
    $player_id = gawm_add_player($data, $player_name);
    
    // todo: how are we going to tell clients which player is them?
    return $data;
}

function _api_next(&$data)
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
            if (is_extrascene($data))
            {
                setup_extrascene($data);
            }
            if (is_firstbreak($data))
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
    
    return $data;
}

function _api_play_detail(&$data, $player_id, $detail_type, $detail_card)
{
    gawm_play_detail($data, $player_id, $detail_type, $detail_card);
    return $data;
}

function _api_twist_detail(&$data, $player_id, $detail_type, $detail_card)
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
    
    return $data;
}

function _api_vote(&$data, $player_id, $vote_value)
{
    $vote_value = intval($vote_value);

    if (is_twist($data))
        throw new Exception('Invalid Scene for Votes');
        
    if (!array_key_exists($player_id,$data["players"]))
        throw new Exception('Invalid Player Id: '.$player_id);

    if ($vote_value!=1 && $vote_value!=2)
        throw new Exception('Invalid Vote Value: '.$vote_value);

    $player = &$data["players"][$player_id];
    $player["vote"]=$vote_value;
    
    return $data;
}

function _api_edit_note(&$data, $player_id, $detail_type, $detail_card, $note)
{
    // TODO: Ensure player has the card in-hand before allowing edit_note
    
    // Minimal input filtering on the plain text
    $note = htmlentities($note);
    // Strip note to max length of 1024.
    // TODO: Since this number will also be used in the UI, move it somewhere
    $note = substr($note, 0, 1024);

    $data['notes'][$detail_type][$detail_card] = $note;
    
    return $data;
}

?>