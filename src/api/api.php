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

function _api_play_detail(&$data, $player_id, $detail_type, $detail_card)
{
    gawm_play_detail($data, $player_id, $detail_type, $detail_card);
    return $data;
}

function _api_twist_detail(&$data, $player_id, $detail_type, $detail_card)
{
    gawm_twist_detail($data, $player_id, $detail_type, $detail_card);
    return $data;
}

function _api_vote(&$data, $player_id, $vote_value)
{
    $vote_value = intval($vote_value);

    gawm_vote($data, $player_id, $vote_value);
    
    return $data;
}

function _api_next(&$data)
{
    gawm_next_scene($data);
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