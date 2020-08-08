<?php
require_once '../api/gawm.php';

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
    if ($result !== $expected_result)
    {
        // json_encode will nicely format almost anything
        throw new Exception(
            "Test failure with ".json_encode($result).
            " expected ".json_encode($expected_result)."\n".
            $error_message."\n"
        );
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
        ],
    ];

    $sequence = gawm_list_players_by_most_innocent($data);
    test($sequence, [4, 1, 2, 3], "Expected innocence rank sequence to be 4, 1, 2, 3");
    $sequence = gawm_list_players_by_most_guilty($data);
    test($sequence, [4, 3, 2, 1], "Expected guilt rank sequence to be 4, 3, 2, 1");
}

function test_redact()
{
    global $data;

    $data_template = [
        "cards" => [],
        "tokens" => [],
        "players" => [
            1 => [
                "hand" => ["aliases" => [1,2]],
                "tokens" => ["guilt" => [1,2],"innocence" => [1,2]],
                "vote" => "guilt"
            ],
            2 => [
                "hand" => ["aliases" => [1,2]],
                "tokens" => ["guilt" => [1,2],"innocence" => [1,2]],
                "vote" => "guilt",
                "unassigned_token" => "guilt",
            ]
        ],
        "act" => 1,
        "scene" => 1,
        "victim" => []
    ];

    $data = $data_template;
    $redacted = redact_for_player($data, 1);
    
    // hands for other players are redacted
    test($redacted["players"][1]["hand"], ["aliases" => [ 1, 2]], "Expected p1 hand to be intact.");
    test($redacted["players"][2]["hand"], ["aliases" => [-1,-1]], "Expected p2 hand to be redacted.");
    
    // tokens are delt face down, redacted for all
    test($redacted["players"][1]["tokens"], ["guilt" => [-1,-1],"innocence" => [-1,-1]], "Expected p1 tokens to be redacted.");
    test($redacted["players"][2]["tokens"], ["guilt" => [-1,-1],"innocence" => [-1,-1]], "Expected p2 tokens to be redacted.");
    
    // other players vote status is redacted
    test(isset($redacted["players"][1]["vote"]),true,"player 1 vote should be intact.");
    test(isset($redacted["players"][2]["vote"]),false,"player 2 vote should be redacted.");
    
    // last break redactions differ
    $data = $data_template;
    $data["act"] = 3;
    $data["scene"] = 4;
    $data["most_innocent"] = 1;

    test(gawm_is_lastbreak($data),true,"expected to be last break");
    $redacted = redact_for_player($data, 1);

    // innocence tokens are redacted until the last one is assigned...
    test($redacted["players"][1]["tokens"], ["guilt" => [-1,-1],"innocence" => [-1,-1]], "Expected p1 innocence tokens to be redacted.");
    test($redacted["players"][2]["tokens"], ["guilt" => [-1,-1],"innocence" => [-1,-1]], "Expected p2 innocence tokens to be redacted.");

    unset($data["players"][2]["unassigned_token"]);
    $redacted = redact_for_player($data, 1);
    
    // innocence tokens not redacted during the accusations
    test($redacted["players"][1]["tokens"], ["guilt" => [-1,-1],"innocence" => [ 1, 2]], "Expected p1 innocence tokens to be intact.");
    test($redacted["players"][2]["tokens"], ["guilt" => [-1,-1],"innocence" => [ 1, 2]], "Expected p2 innocence tokens to be intact.");

    
    
    // epilogue redactions differ
    $data = $data_template;
    $data["act"] = 4;
    $data["epilogue_order"] = [0,1];
    $redacted = redact_for_player($data, 1);

    // tokens not redacted in the 4th act (epilogue)
    test($redacted["players"][1]["tokens"], ["guilt" => [ 1, 2],"innocence" => [ 1, 2]], "Expected p1 tokens to be intact.");
    test($redacted["players"][2]["tokens"], ["guilt" => [ 1, 2],"innocence" => [ 1, 2]], "Expected p2 tokens to be intact.");
    
    test(array_keys($redacted["players"]),[1,2],"redact should not change the player uids");
}

function test_move_detail()
{
    global $data;

    $data_template = [
        "players" => [
            "aa" => [
                "play" => ["objects" => [1,2]],
            ],
            "bb" => [
                "play" => []
            ]
        ],
    ];
    $data_expected = [
        "players" => [
            "aa" => [
                "play" => ["objects" => [2]],
            ],
            "bb" => [
                "play" => ["objects" => [1]],
            ]
        ],
    ];
    $data = $data_template;
    gawm_move_detail($data,'aa',"objects",1,'bb');
    test($data,$data_expected,"object 1 should be moved from player aa to player bb");
    
    $data_expected = [
        "players" => [
            "aa" => [
                "play" => [],
            ],
            "bb" => [
                "play" => ["objects" => [1,2]],
            ]
        ],
    ];    
    gawm_move_detail($data,'aa',"objects",2,'bb');
    test($data,$data_expected,"object 2 should be moved from player aa to player bb");
    
}

function test_setup_epilogue_order()
{
    global $data;

    $data_template = [
        "players" => [
            "a" => ["fate" => "gawm"],
            "b" => ["fate" => "got_framed"],
            "c" => ["fate" => "got_it_wrong"],
            "d" => ["fate" => "got_out_alive"]
        ],
    ];

    $data = $data_template;
    setup_epilogue_order($data);
    test($data["epilogue_order"],[3,1,2,0],"Incorrect epilogue order for fates.");
    
    $data_template = [
        "players" => [
            "a" => ["fate" => "got_caught"],
            "b" => ["fate" => "got_out_alive"],
            "c" => ["fate" => "got_it_right"]
        ],
    ];

    $data = $data_template;
    setup_epilogue_order($data);
    test($data["epilogue_order"],[1,2,0],"Incorrect epilogue order for fates.");
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
        $other_players = array_filter( $player_ids,
            function($id)use($player_id){return $id!=$player_id;}
        );
        
        test(gawm_is_player_active($data, $player_id), true, "players should be active in setup");
        test(gawm_player_has_details_left_to_play($data, $player_id), true, "players have details to play in setup");
        
        $targets = [$player_id];
        if ($detail == 'relationships') $targets[] = current($other_players);
        
        gawm_play_detail(
            $data, $player_id, $detail,
            current($data["players"][$player_id]["hand"][$detail]),
            $targets
        );

        // todo: "voting scene: methos (like in js)
        $act = $data["act"];
        if ($act>0)
        {
            vote_scene($data);
        }
        
        gawm_request_next_scene($data, $player_id);

        if ($act>0)
        {
            // token gifting
            test(isset($data["players"][$player_id]["unassigned_token"]),true,"after the scene ends the play should have an unassigned token");
            $token = $data["players"][$player_id]["unassigned_token"];
            gawm_give_token($data, $player_id, $token, current($other_players));
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
        array_push( $player_ids, gawm_add_player($data,"player"));
        
    test(count($data["players"]), $c, "Expected ".$c." players.");
    test(count(gawm_get_player_names($data)), $c, "Expected ".$c." player names.");
    test(count( array_unique(gawm_get_player_names($data)) ), $c, "Expected ".$c." unique player names.");
    
    test(gawm_is_detail_active($data, "aliases"), true, "aliases should be active in setup");
    test(gawm_is_detail_active($data, "objects"), false, "objects should not be active in setup");
    test(gawm_is_detail_active($data, "relationships"), false, "relationships should not be active in setup");
    test(gawm_is_detail_active($data, "motives"), false, "motives should not be active in setup");
    test(gawm_is_detail_active($data, "wildcards"), false, "wildcards should not be active in setup");

    play_scenes($data, $player_ids, "aliases");

    // advance from setup to act I
    test($data["act"], 1, "Act 1 should follow set up.");
    test($data["scene"], 0, "Act 1 starts with Scene 0.");

    test(gawm_is_detail_active($data, "motives"), false, "motives should not be active in act I");
    test(gawm_is_detail_active($data, "objects"), true, "objects should be active in act I");
    test(gawm_is_detail_active($data, "relationships"), true, "relationships should be active in act I");
    test(gawm_is_detail_active($data, "wildcards"), true, "wildcards should be active in act I");

    // play out act I
    play_scenes($data, $player_ids,"relationships");

    // Scene progression test coverage:
    test(gawm_is_extrascene($data), true, "Extra Scene expected.");
    test(gawm_is_firstbreak($data), false, "First break should follow Extra Scene");
    test(gawm_is_twist($data), false, "Twist should follow player scenes in Act II");
    test(gawm_is_lastbreak($data), false, "Last Break should follow 2x player scenes in Act III");
    test(gawm_is_epilogue($data), false, "Epilogue (Act 4) should follow Last Break.");
    test(gawm_is_epilogue_inprogress_or_complete($data), false, "Epilogue (Act 4) should follow Last Break.");    
    
    test(isset($data["victim"]), true, "The victim should have been selected");
    test(isset($data["victim"]["player_id"]), true, "The victim should have a player_id");
    test(count($data["victim"]["play"])>0, true, "The victim should have at leasr 1 detail in play (alias).");
    
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

    // active player in extra scene should select their new alias and a new detail
    gawm_play_detail(
        $data, $active_player, "aliases",
        current($data["players"][$active_player]["hand"]["aliases"]),
        $active_player
    );

    $other_players = array_filter( $player_ids,
        function($id)use($active_player){return $id!=$active_player;}
    );

    gawm_play_detail(
        $data, $active_player, "relationships",
        current($data["players"][$active_player]["hand"]["relationships"]),
        [$active_player, current($other_players)]
    );
    vote_scene($data);
    gawm_request_next_scene($data,$active_player);
    $token = $data["players"][$active_player]["unassigned_token"];
    gawm_give_token($data,$active_player,$token,0);

    // Scene progression test coverage:
    test(gawm_is_extrascene($data), false, "Extra Scene unexpected.");
    test(gawm_is_firstbreak($data), true, "First break should follow Extra Scene");
    test(gawm_is_twist($data), false, "Twist should follow player scenes in Act II");
    test(gawm_is_lastbreak($data), false, "Last Break should follow 2x player scenes in Act III");
    test(gawm_is_epilogue($data), false, "Epilogue (Act 4) should follow Last Break.");
    test(gawm_is_epilogue_inprogress_or_complete($data), false, "Epilogue (Act 4) should follow Last Break.");   
    
    $vdc = count($data["victim"]["play"]);

    // make play_detail calls for the muder details
    gawm_play_detail(
        $data, gawm_player_id_victim, "murder_cause",
        current($data["victim"]["hand"]["murder_cause"]),
        gawm_player_id_victim
    );
    gawm_play_detail(
        $data, gawm_player_id_victim, "murder_discovery",
        current($data["victim"]["hand"]["murder_discovery"]),
        gawm_player_id_victim
    );
    test(count($data["victim"]["play"]), $vdc+2,"The victim should have gained 2 murder details.");

    gawm_request_next_scene($data,$data["victim"]["player_id"]);

    // advance from first break to act II
    test($data["act"], 2, "Act II should follow first break.");
    test($data["scene"], 0, "Act II starts with Scene 0.");
    test(gawm_is_detail_active($data, "motives"), true, "motives should now be active in Act II");

    // play out act II
    play_scenes($data, $player_ids,"objects");
    
    // Scene progression test coverage:
    test(gawm_is_extrascene($data), false, "Extra Scene unexpected.");
    test(gawm_is_firstbreak($data), false, "First break should follow Extra Scene");
    test(gawm_is_twist($data), true, "Twist should follow player scenes in Act II");
    test(gawm_is_lastbreak($data), false, "Last Break should follow 2x player scenes in Act III");
    test(gawm_is_epilogue($data), false, "Epilogue (Act 4) should follow Last Break.");
    test(gawm_is_epilogue_inprogress_or_complete($data), false, "Epilogue (Act 4) should follow Last Break.");  
    
    test( gawm_is_player_active($data, gawm_player_id_victim), false, "The victim should not be active in the Twist.");
    // every player twists 1 motive
    foreach( $player_ids as $player_id )
    {
        gawm_twist_detail(
            $data, $player_id, "motives",
            current($data["players"][$player_id]["hand"]["motives"])
        );
        $r = gawm_request_next_scene($data, $player_id);
    }

    test($data["act"], 3, "Act III should follow Twist.");
    test($data["scene"], 0, "Act III starts with Scene 0.");

    // play out act III
    play_scenes($data, $player_ids,"wildcards");
    play_scenes($data, $player_ids,"motives");

    // Scene progression test coverage:
    test(gawm_is_extrascene($data), false, "Extra Scene unexpected.");
    test(gawm_is_firstbreak($data), false, "First break should follow Extra Scene");
    test(gawm_is_twist($data), false, "Twist should follow player scenes in Act II");
    test(gawm_is_lastbreak($data), true, "Last Break should follow 2x player scenes in Act III");
    test(gawm_is_epilogue($data), false, "Epilogue (Act 4) should follow Last Break.");
    test(gawm_is_epilogue_inprogress_or_complete($data), false, "Epilogue (Act 4) should follow Last Break.");
    
    test( isset($data["most_innocent"]), true, "The Most Innocent must exist in the Last Break" );
    test( gawm_is_player_active($data, $data["most_innocent"]), true, "The Most Innocent should be the active player in the Last Break" );

    $other_players = array_filter( $player_ids, 
        function($id)use($data){return $id!=$data["most_innocent"];}
    );

    gawm_record_accused($data, $data["most_innocent"], current($other_players));

    gawm_request_next_scene($data, $data["most_innocent"]);

    // Scene progression test coverage:
    test(gawm_is_extrascene($data), false, "Extra Scene unexpected.");
    test(gawm_is_firstbreak($data), false, "First break should follow Extra Scene");
    test(gawm_is_twist($data), false, "Twist should follow player scenes in Act II");
    test(gawm_is_lastbreak($data), false, "Last Break should follow 2x player scenes in Act III");
    test(gawm_is_epilogue($data), true, "Epilogue (Act 4) should follow Last Break.");
    test(gawm_is_epilogue_inprogress_or_complete($data), true, "Epilogue (Act 4) should follow Last Break."); 
    
    test($data["scene"], 0, "Epilogue starts with Scene 0.");

    $fates=[
        "got_caught" => 0,
        "gawm" => 0,
        "got_it_right" => 0,
        "got_it_wrong" => 0,
        "got_framed" => 0,
        "got_out_alive" => 0
    ];
    // one scene per player
    for ($i=0;$i!=$c;$i=$i+1)
    {
        test(isset($data["players"][active_player_id($data)]["fate"]),true,"Every player should have a fate for the Epilogue");
        $fates[$data["players"][active_player_id($data)]["fate"]]++;
        gawm_request_next_scene($data, active_player_id($data));
    }
    test($fates["got_caught"]+$fates["gawm"],1,"There can only be one guilty fate ".json_encode($fates));

    // Scene progression test coverage:
    test(gawm_is_extrascene($data), false, "Extra Scene unexpected.");
    test(gawm_is_firstbreak($data), false, "First break should follow Extra Scene");
    test(gawm_is_twist($data), false, "Twist should follow player scenes in Act II");
    test(gawm_is_lastbreak($data), false, "Last Break should follow 2x player scenes in Act III");
    test(gawm_is_epilogue($data), false, "Epilogue over...");
    test(gawm_is_epilogue_inprogress_or_complete($data), true, "Epilogue over.");
}

echo "Testing... ";

try{
    test_setup_epilogue_order();
    test_innocence_and_guilt_ranking();
    test_tally_votes();
    test_redact();
    test_move_detail();
    test_playthrough(4);
    test_playthrough(5);
    test_playthrough(6);

    echo "Passed ".$test_count." tests.\n";
}
catch (Exception $e) {
    echo("Caught ".$e."\nWith: ".json_encode($data) ."\n");
    exit(1);
}
?>
