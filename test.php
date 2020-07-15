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
        // json_encode will nicely format almost anything
        echo "Test failure with ".json_encode($result)." expected ".json_encode($expected_result)."\n";
        echo json_encode($data) ."\n";
        throw new Exception($error_message);
    }
    $test_count++;
}

function test_tally_votes()
{
    global $data;

    // 1 eligable vote
    $data = [
        "players" => [
            "1" => [],
            "2" => ["vote" => gawm_vote_innocent],
            "3" => [],
            "4" => [],
        ],
        "scene" => 1,
        "act" => 0
    ];
    $tally = tally_votes($data);
    test($tally[gawm_vote_innocent],1,"Expected to count 1 innocent vote");

    // 1 eligable vote, active player disagrees (should be ignored)
    $data = [
        "players" => [
            "1" => ["vote" => gawm_vote_guilty],
            "2" => ["vote" => gawm_vote_innocent],
            "3" => [],
            "4" => [],
        ],
        "scene" => 1,
        "act" => 0
    ];
    $tally = tally_votes($data);
    test($tally[gawm_vote_innocent],1,"Expected to count 1 innocent vote");
    test($tally[gawm_vote_guilty],0,"Expected to count 0 guilty votes");

    // 2 eligable votes, active player tie-breaks
    $data = [
        "players" => [
            "1" => ["vote" => gawm_vote_guilty],
            "2" => ["vote" => gawm_vote_innocent],
            "3" => ["vote" => gawm_vote_guilty],
            "4" => [],
        ],
        "scene" => 1,
        "act" => 0
    ];
    $tally = tally_votes($data);
    test($tally[gawm_vote_innocent],1,"Expected to count 1 innocent vote");
    test($tally[gawm_vote_guilty],2,"Expected to count 2 guilty votes");
}

function test_innocence_and_guilt_ranking()
{
    global $data;

    // The tokens are set so all kinds of tie break are tested, bar flipping a coin
    // This outcome is also really possible
    // Unplayed tiles:  I: 1 2 2 3 3 3   G: 0
    // Fun fact: The most innocent player was the guilty party, so they got away with murder!
    $data = [
        "players" => [
            1 => ["tokens" => [
                "innocence" => [0,0,1,1], //innoc score: 2, wins most innocence tokens tie break
                "guilt" => [0,3]          //guilt score: 1,
            ]],
            2 => ["tokens" => [
                "innocence" => [0,0,2],   //innoc score: 2
                "guilt" => [0,1,3,3]      //guilt score: 5
            ]],
            3 => ["tokens" => [
                "innocence" => [1],      //innoc score: 1
                "guilt" => [0,1,2,3]     //guilt score: 5, wins least innocence tokens tie break
            ]],
            4 => ["tokens" => [
                "innocence" => [3],      //innoc score: 3
                "guilt" => [0,1,1,2,2,2] //guilt score: 5, wins most guilt tokens tie break
            ]]
        ]
    ];

    $sequence = gawm_list_players_by_most_innocent($data);
    test($sequence, [4, 1, 2, 3], "Expected innocence rank sequence to be 4, 1, 2, 3");
    $sequence = gawm_list_players_by_most_guilty($data);
    test($sequence, [4, 3, 2, 1], "Expected guilt rank sequence to be 4, 3, 2, 1");
}

function vote_scene( &$data )
{
    $inactive_players = array_filter(
        array_keys($data["players"]),
        function($id){global $data; return !gawm_is_player_active($data,$id);}
    );
    test( count($inactive_players), count($data["players"])-1, "All bar 1 players should be active in the scene." );
    gawm_vote($data, current($inactive_players), gawm_vote_guilty);
}

function play_scenes( &$data, $player_ids, $detail )
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
            vote_scene($data);
            gawm_next_scene($data);

            // token gifting
            test(isset($data["players"][$player_id]["unassigned_token"]),true,"after the scene ends the play should have an unassigned token");
            $other_players = array_filter( $player_ids,
                function($id)use($player_id){return $id!=$player_id;}
            );
            gawm_give_token($data, $player_id, current($other_players));
            test(isset($data["players"][$player_id]["unassigned_token"]),false,"after giving a token, the player should have one");
        }
    }
}

function test_playthrough($c)
{
    global $data;

    $data = gawm_new_game();
    test(gawm_is_setup($data), true, "New game should start in Setup");

    $player_ids = [];
    for ($i=0;$i!=$c;$i=$i+1)
        array_push( $player_ids, gawm_add_player($data,"player-".($i+1)));

    test(count($data["players"]), $c, "Expected ".$c." players.");
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

    test(count($data["players"][$active_player]["play"]), 0,"Victim Player should now have 0 detail types in play.");
    test(count($data["players"][$active_player]["tokens"]["guilt"]), 0, "Victim Player should have 0 Tokens.");
    test(count($data["players"][$active_player]["tokens"]["innocence"]), 0, "Victim Player should have 0 Tokens.");
    test(count($data["victim"]["play"]), 2,"The victim should have 2 detail types in play (alias, +1).");

    // active player in extra scene should select their new alias and a new detail
    gawm_play_detail(
        $data, $active_player, "aliases",
        current($data["players"][$active_player]["hand"]["aliases"])
    );
    gawm_play_detail(
        $data, $active_player, "relationships",
        current($data["players"][$active_player]["hand"]["relationships"])
    );
    vote_scene($data);
    gawm_next_scene($data);
    gawm_give_token($data,$active_player,0);
    test( gawm_is_firstbreak($data), true, "First break should follow Extra Scene");
    test(count($data["victim"]["play"]), 2,"The victim should have 2 detail types in play (alias, +1).");

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
    test( isset($data["most_innocent"]), true, "The Most Innocent must exist in the Last Break" );
    test( gawm_is_player_active($data, $data["most_innocent"]), true, "The Most Innocent should be the active player in the Last Break" );

    gawm_next_scene($data);

    test(gawm_is_epilogue($data), true, "Epilogue (Act 4) should follow Last Break.");
    test($data["scene"], 0, "Epilogue starts with Scene 0.");
}

echo "Testing... ";

test_tally_votes();
test_innocence_and_guilt_ranking();
test_playthrough(4);
test_playthrough(5);
test_playthrough(6);

echo "Passed ".$test_count." tests.\n";
?>
