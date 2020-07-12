<?php
require_once 'api/gawm.php';

// warnings as errors during tests
// https://stackoverflow.com/questions/10520390/stop-script-execution-upon-notice-warning

function errHandle($errNo, $errStr, $errFile, $errLine) {
    $msg = "$errStr in $errFile on line $errLine";
    if ($errNo == E_NOTICE || $errNo == E_WARNING) {
        throw new ErrorException($msg, $errNo);
    } else {
        echo $msg;
    }
}

set_error_handler('errHandle');

$test_count = 0;
$data = null;

function test( $result, $expected_result, $error_message )
{
    global $test_count, $data;
    if ($result!=$expected_result)
    {
        echo "Test failure with ".$result." expected ".$expected_result."\n";
        echo json_encode($data) ."\n";
        throw new Exception($error_message);
    }
    $test_count++;
}

function play_scenes( &$data, $player_ids, $detail)
{
    foreach( $player_ids as $player_id )
    {
        test(gawm_is_player_active($data, $player_id), true, "players should be active in setup");
        test(gawm_player_has_details_left_to_play($data, $player_id), true, "players have details to play in setup");
        gawm_play_detail(
            $data, $player_id, $detail, 
            current($data["players"][$player_id]["hand"][$detail])
        );

        if ($data["act"]>0)
        {
            $inactive_players = array_filter(
                array_keys($data["players"]), 
                function($id){global $data; return !gawm_is_player_active($data,$id);}
            );
            test( count($inactive_players), count($player_ids)-1, "All bar 1 players should be active in the scene." );
            gawm_vote($data, current($inactive_players), gawm_vote_guilty);
            gawm_next_scene($data);
        }
    }
}

echo "Testing... ";
$data = gawm_new_game();
test(gawm_is_setup($data), true, "New game should start in Setup");

$player_ids = [
    gawm_add_player($data,"player 1"),
    gawm_add_player($data,"player 2"),
    gawm_add_player($data,"player 3"),
    gawm_add_player($data,"player 4"),
];

test(count($data["players"]), 4, "Expected 4 players.");
test(gawm_is_detail_active($data, "aliases"), true, "aliases should be active in setup");
test(gawm_is_detail_active($data, "objects"), false, "objects should not be active in setup");
test(gawm_is_detail_active($data, "relationships"), false, "relationships should not be active in setup");
test(gawm_is_detail_active($data, "motives"), false, "motives should not be active in setup");
test(gawm_is_detail_active($data, "wildcards"), false, "wildcards should not be active in setup");

play_scenes($data, $player_ids,"aliases");

// advance from setup to act I
gawm_next_scene($data);
test($data["act"], 1, "Act 1 should follow set up.");
test($data["scene"], 0, "Act 1 starts with Scene 0.");

test(gawm_is_detail_active($data, "motives"), false, "motives should not be active in act I");
test(gawm_is_detail_active($data, "objects"), true, "objects should be active in act I");
test(gawm_is_detail_active($data, "relationships"), true, "relationships should be active in act I");
test(gawm_is_detail_active($data, "wildcards"), true, "wildcards should be active in act I");

// play out act I
play_scenes($data, $player_ids,"relationships");

test(gawm_is_extrascene($data), true, "Extra Scene expected.");
test(isset($data["victim"]), true, "The victim should have been selected");
test(isset($data["victim"]["player_id"]), true, "The victim should have a player_id");

// check which players are active
$active_players = array_filter(
    array_keys($data["players"]), 
    function($id){global $data; return gawm_is_player_active($data,$id);}
);

test(count($active_players), 1, "Expected 1 active player in extra scene");
$active_player = current($active_players);

// active player in extra scene should select their new alias and a new detail
gawm_play_detail(
    $data, $active_player, "aliases", 
    current($data["players"][$active_player]["hand"]["aliases"])
);
gawm_play_detail(
    $data, $active_player, "relationships", 
    current($data["players"][$active_player]["hand"]["relationships"])
);
 
gawm_next_scene($data);
test( gawm_is_firstbreak($data), true, "First break should follow Extra Scene");

// make play_detail calls for the muder details
gawm_play_detail(
    $data, gawm_player_id_victim, "murder_cause", 
    current($data["victim"]["hand"]["murder_cause"])
);
gawm_play_detail(
    $data, gawm_player_id_victim, "murder_discovery", 
    current($data["victim"]["hand"]["murder_discovery"])
);
test(count($data["victim"]["play"]), 4,"The victim should have 4 detail types in play (alias, 2x murder, +1).");

gawm_next_scene($data);

// advance from first break to act II
test($data["act"], 2, "Act II should follow first break.");
test($data["scene"], 0, "Act II starts with Scene 0.");
test(gawm_is_detail_active($data, "motives"), true, "motives should now be active in Act II");

// play out act II
play_scenes($data, $player_ids,"objects");
test( gawm_is_twist($data), true, "Twist should follow player scenes in Act II");

// every player twists 1 motive
foreach( $player_ids as $player_id )
{
    gawm_twist_detail(
        $data, $player_id, "motives", 
        current($data["players"][$player_id]["hand"]["motives"])
    );
}

gawm_next_scene($data);
test($data["act"], 3, "Act III should follow Twist.");
test($data["scene"], 0, "Act III starts with Scene 0.");

// play out act III
play_scenes($data, $player_ids,"wildcards");
play_scenes($data, $player_ids,"motives");

// test is last break
test( gawm_is_lastbreak($data), true, "Last Break should follow 2x player scenes in Act III");
gawm_next_scene($data);

test(gawm_is_epilogue($data), true, "Epilogue (Act 4) should follow Act III.");
test($data["scene"], 0, "Epilogue starts with Scene 0.");

echo "Passed ".$test_count." tests.\n";
?>