<?php
require_once 'api/gawm.php';

$test_count = 0;

function test( $result, $expected_result, $error_message )
{
    global $test_count;
    if ($result!=$expected_result)
        throw Exception($error_message);

    $test_count++;
}

echo "Testing... ";
$data = gawm_new_game();

$player_ids = [
    gawm_add_player($data,"player 1"),
    gawm_add_player($data,"player 2"),
    gawm_add_player($data,"player 3"),
    gawm_add_player($data,"player 4"),
];

test(count($data["players"]), 4, "Expected 4 players.");
test(gawm_is_detail_active($data, "aliases"), true, "aliases should be active in setup");
foreach( $player_ids as $player_id )
{
    test(gawm_is_player_active($data, $player_id), true, "players should be active in setup");
    test(gawm_player_has_details_left_to_play($data, $player_id), true, "players have details to play in setup");
    gawm_play_detail(
        $data, $player_id, "aliases", 
        current($data["players"][$player_id]["hand"]["aliases"])
    );    
}

echo "Passed ".$test_count." tests.\n";
?>