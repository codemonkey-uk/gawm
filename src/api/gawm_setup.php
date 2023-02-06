<?php
//
// Implementation of functions implementing Setup (Act 0)
//
// Public, called by API:
// - gawm_is_setup - returns true if it is Act 0
// - gawm_add_player - allows players to be added to the game
//
// Implementation, used during Next Scene:
// - setup_setup - to be called at the start of the scene,
// - complete_setup - to be called at the start of the scene
//

function gawm_is_setup(&$data)
{
    return $data["act"]==0;
}

// returns a unique player name
function disambiguate_player_name($player_name_list, $player_name)
{
    while (in_array($player_name,$player_name_list)) {
        // Look for number at the end of the name
        if (preg_match('/[0-9]+$/', $player_name, $matches, PREG_OFFSET_CAPTURE)) {
            // Increment the number
            [$number, $offset] = $matches[0];
            $player_name = substr($player_name,0,$offset).++$number;
        } else {
            $player_name .= " 2";
        }
    }
    
    return $player_name;
}

function gawm_rename_player(&$data, $player_id, $player_name)
{
    if (!array_key_exists($player_id,$data["players"]))
        throw new Exception('Invalid Player Id: '.$player_id);
    
    if ($player_name=="")
        throw new Exception('Invalid Player Rename: '.$player_id);
    
    // rename to same name is fine
    if ($data["players"][$player_id]['name']==$player_name)
        return;
    
    // but do not allow duplicate names via renaming
    $data["players"][$player_id]['name'] = 
        disambiguate_player_name(gawm_get_player_names($data), $player_name);
}

// modifies data, returns player_id
// name string sanatising, should be done in API layer
function gawm_add_player(&$data, $player_name, $rules = gawm_default_rules)
{
    // only add players, up to 6, in act 0 (setup)
    if (!gawm_is_setup($data))
    {
        throw new Exception('Trying to add players outside setup step.');
    }

    if (count($data["players"] ) >= 6)
    {
        throw new Exception('Trying to add a 7th player.');
    }

    $new_player = [
        'name' => disambiguate_player_name(gawm_get_player_names($data), $player_name),
        'hand' => [],
        'play' => [],
        'tokens' => [
            'guilt' => [],
            'innocence' => []
        ]
    ];

    // draw aliases
    draw_player_cards($data, $new_player, $rules["new_player_draw"] );

    // TODO: optional rule, draw details with alias during set up
    // draw_player_details($data, $new_player);

    // create unique id for the new player
    $player_id = uniqid();
    while (array_key_exists($player_id,$data["players"]) || $player_id==gawm_player_id_victim)
        $player_id = uniqid();

    $data["players"][$player_id] = $new_player;

    // each player put four guilt tokens and four innocence tokens,
    // numbered "0" to "3", in a central pile
    $data["tokens"]["innocence"] = array_merge($data["tokens"]["innocence"], $rules["new_player_tokens"]);
    $data["tokens"]["guilt"] = array_merge($data["tokens"]["guilt"], $rules["new_player_tokens"]);

    return $player_id;
}

function setup_setup(&$data, $rules = gawm_default_rules)
{
    // component list
    $data = [
        "tokens" => [
            "guilt" => [],
            "innocence" => []
        ],
        "cards" => $rules["cards"]
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
}

function complete_setup(&$data)
{
    // at least 4 players
    if (count($data["players"])<4)
    {
        throw new Exception('At least 4 Players required to Complete Setup.');
    }

    // every player has 1 alias
    foreach( $data["players"] as $id => $player )
    {
        // 0 alias in hand
        if (isset($player["hand"]["aliases"]))
        {
            throw new Exception($id.' has an alias detail still in hand.');
        }
        // 1 alias in play
        if (count($player["play"]["aliases"])!=1)
        {
            throw new Exception($id.' has an alias detail missing from play.');
        }
    }

    // give players detail cards
    foreach( $data["players"] as &$player )
    {
        // draw any other details needed for Act I
        draw_player_details($data, $player);
    }

    shuffle($data["tokens"]["innocence"]);
    shuffle($data["tokens"]["guilt"]);

    $data["act"] = 1;
    $data["scene"] = 0;

    // the murder victim
    // (this is redacted for clients until the extrascene)
    // create victim object, and record which player it was
    $data["victim"]=array();
    $data["victim"]["player_id"]=array_rand($data["players"]);  
}
?>
