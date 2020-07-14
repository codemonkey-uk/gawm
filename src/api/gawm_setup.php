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

// modifies data, returns player_id
// name string sanatising, should be done in API layer
function gawm_add_player(&$data, $player_name)
{
    // only add players, up to 6, in act 0 (setup)
    if (!gawm_is_setup($data))
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
    while (array_key_exists($player_id,$data["players"]) || $player_id==gawm_player_id_victim)
        $player_id = uniqid();

    $data["players"][$player_id] = $new_player;

    // each player put four guilt tokens and four innocence tokens,
    // numbered "0" to "3", in a central pile
    $data["tokens"]["innocence"] = array_merge($data["tokens"]["innocence"], range(0,3));
    $data["tokens"]["guilt"] = array_merge($data["tokens"]["guilt"], range(0,3));

    return $player_id;
}

function setup_setup(&$data)
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
}

function complete_setup(&$data)
{
    // at least 4 players
    if (count($data["players"])<4)
    {
        throw new Exception('Must have 4 Players to Complete Setup.');
    }

    // every player has 1 alias
    foreach( $data["players"] as $player )
    {
        // 0 alias in hand
        if (isset($player["hand"]["aliases"]))
        {
            throw new Exception('Alias detail still in hand.');
        }
        // 1 alias in play
        if (count($player["play"]["aliases"])!=1)
        {
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
?>
