<?php
require_once 'gawm.php';
require_once 'db.php';

function sanitise_player_name($player_name)
{
    // input filtering on player name
    $player_name = trim($player_name);
    if ($player_name == '')
    {
        throw new Exception('No player name supplied');
    }
    $player_name = htmlentities($player_name);
    $player_name = substr($player_name, 0, 40);
    
    return $player_name;
}

function _api_new($player_name, $hashids)
{
    $player_name = sanitise_player_name($player_name);
    
    $data = gawm_new_game();
    $player_id = gawm_add_player($data, $player_name);
    $game_id = save_new_game($data);
    
    return [
        'game' => redact_for_player($data, $player_id),
        'game_id' => $hashids->encode($game_id),
        'player_id' => $player_id
    ];
}

function _api_add_player(&$data, $player_name)
{
    $player_name = sanitise_player_name($player_name);

    $player_id = gawm_add_player($data, $player_name);

    return [
        'game' => redact_for_player($data, $player_id),
        'player_id' => $player_id
    ];   
}

function _api_play_detail(&$data, $player_id, $detail_type, $detail, $targets)
{
    gawm_play_detail($data, $player_id, $detail_type, $detail, $targets);
    
    return [
        'game' => redact_for_player($data, $player_id),
        'player_id' => $player_id
    ];
}

function _api_twist_detail(&$data, $player_id, $detail_type, $detail)
{
    gawm_twist_detail($data, $player_id, $detail_type, $detail);

    return [
        'game' => redact_for_player($data, $player_id),
        'player_id' => $player_id
    ]; 
}

function _api_move_detail(&$data, $player_id, $detail_type, $detail, $targets)
{
    if (is_array($targets))
        $target = current($targets);

    gawm_move_detail($data, $player_id, $detail_type, $detail, $target);
    
    return [
        'game' => redact_for_player($data, $player_id),
        'player_id' => $player_id
    ]; 
 
}

function _api_vote(&$data, $player_id, $detail)
{
    $vote_value = intval($detail);

    gawm_vote($data, $player_id, $detail);

    return [
        'game' => redact_for_player($data, $player_id),
        'player_id' => $player_id
    ]; 
}

function _api_give_token(&$data, $player_id, $detail)
{
    gawm_give_token($data, $player_id, $detail);

    return [
        'game' => redact_for_player($data, $player_id),
        'player_id' => $player_id
    ]; 
}

function _api_record_accused(&$data, $player_id, $detail)
{
    gawm_record_accused($data, $player_id, $detail);

    return [
        'game' => redact_for_player($data, $player_id),
        'player_id' => $player_id
    ]; 
}

function _api_get($game_id, $player_id)
{
    return [
        'game' => redact_for_player(
            load_and_release($game_id), 
            $player_id
        ),
        'player_id' => $player_id
    ]; 
}

function _api_next(&$data, $player_id)
{
    $id = gawm_request_next_scene($data, $player_id);
    
    return [
        'game' => redact_for_player($data, $player_id),
        'player_id' => $player_id,
        'pending_id' => $id
    ]; 
}

function _api_edit_note(&$data, $player_id, $detail_type, $detail, $note)
{
    // Minimal input filtering on the plain text
    $note = htmlentities($note);
    
    // Strip note to max length of 1024.
    $note = substr($note, 0, 1024);

    $data['notes'][$detail_type][$detail] = $note;

    return [
        'game' => redact_for_player($data, $player_id),
        'player_id' => $player_id
    ]; 
}

function _api_rename_player(&$data, $player_id, $player_name)
{
    $player_name = sanitise_player_name($player_name);
    gawm_rename_player($data, $player_id, $player_name);
    
    return [
        'game' => redact_for_player($data, $player_id),
        'player_id' => $player_id
    ];
}
?>
