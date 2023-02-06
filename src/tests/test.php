<?php
require_once '../api/gawm.php';
require_once 'testing.php';
require_once 'playthrough.php';

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

    $data = gawm_new_game();

    // add 4 players to the game
    $player_ids = [];
    array_push( $player_ids, gawm_add_player($data, 1) );
    array_push( $player_ids, gawm_add_player($data, 2) );
    array_push( $player_ids, gawm_add_player($data, 3) );
    array_push( $player_ids, gawm_add_player($data, 4) );
    
    // shortcuts for player 1 & 2 ids
    $p1 = $player_ids[0];
    $p2 = $player_ids[1];

    // play an alias for each player
    foreach( $player_ids as $player_id )
    {
        $targets = [$player_id];
        gawm_play_detail(
            $data, $player_id, "aliases",
            current($data["players"][$player_id]["hand"]["aliases"]),
            $targets
        );    
    }
    
    complete_setup($data);

    // give everyone some out-of-sequence innocence and guilt tokens
    foreach( $player_ids as $player_id )
    {
        $data["players"][$player_id]["tokens"] = ["guilt" => [1,2],"innocence" => [1,2]];
    }

    // set p1 & p2 votes
    $data["players"][$p1]["vote"] = "guilt";
    $data["players"][$p2]["vote"] = "guilt";

    $redacted = redact_for_player($data, $p1);
    
    // hands for other players only, are redacted
    test(
        $redacted["players"][$p1]["hand"], 
        $data["players"][$p1]["hand"], 
        "Expected p1 hand to be intact.");

    test(
        $redacted["players"][$p2]["hand"], 
        ["relationships" => [-1,-1,-1],"objects" => [-1,-1,-1],"motives" => [-1,-1,-1],"wildcards" => [-1,-1,-1]],
        "Expected p2 hand to be redacted.");
    
    // tokens are delt face down, redacted for all
    
    test(
        $redacted["players"][$p1]["tokens"], 
        ["guilt" => [-1,-1],"innocence" => [-1,-1]], 
        "Expected p1 tokens to be redacted.");

    test(
        $redacted["players"][$p2]["tokens"], 
        ["guilt" => [-1,-1],"innocence" => [-1,-1]], 
        "Expected p2 tokens to be redacted.");
    

    // other players vote status is redacted
    test(isset($redacted["players"][$p1]["vote"]),true,"player 1 vote should be intact.");
    test(isset($redacted["players"][$p2]["vote"]),false,"player 2 vote should be redacted.");

    // victim redacted before extrascene
    test(isset($redacted["victim"]),false,"victim should be redacted in act 1");

    // victim is NOT redacted in extrascene
    $data["scene"] = 4;
    test(gawm_is_extrascene($data),true,"(meta) act 1 scene 2 in a 2 player game should be an extrasceene");
    
    setup_extrascene($data);
    $redacted = redact_for_player($data, $p1);
    test(isset($redacted["victim"]),true,"victim should not be redacted in act 1 extrascene");

    // last break redactions differ
    // $data = $data_template;
    $data["act"] = 3;
    $data["scene"] = 8;
    $data["most_innocent"] = 1;
    $data["players"][$p2]["unassigned_token"] = "guilt";

    test(gawm_is_lastbreak($data),true,"expected to be last break");
    $redacted = redact_for_player($data, $p1);

    // innocence tokens are redacted until the last one is assigned...
    test($redacted["players"][$p1]["tokens"], ["guilt" => [-1,-1],"innocence" => [-1,-1]], "Expected p1 innocence tokens to be redacted.");
    test($redacted["players"][$p2]["tokens"], ["guilt" => [-1,-1],"innocence" => [-1,-1]], "Expected p2 innocence tokens to be redacted.");


    unset($data["players"][$p2]["unassigned_token"]);
    $redacted = redact_for_player($data, 1);
    
    // innocence tokens not redacted during the accusations
    test($redacted["players"][$p1]["tokens"], ["guilt" => [-1,-1],"innocence" => [ 1, 2]], "Expected p1 innocence tokens to be intact.");
    test($redacted["players"][$p2]["tokens"], ["guilt" => [-1,-1],"innocence" => [ 1, 2]], "Expected p2 innocence tokens to be intact.");
    
    // epilogue redactions differ
    // $data = $data_template;
    $data["act"] = 4;
    $data["scene"] = 1;
    $data["epilogue_order"] = $player_ids;
    $redacted = redact_for_player($data, $p1);

    // tokens not redacted in the 4th act (epilogue)
    test($redacted["players"][$p1]["tokens"], ["guilt" => [ 1, 2],"innocence" => [ 1, 2]], "Expected p1 tokens to be intact.");
    test($redacted["players"][$p2]["tokens"], ["guilt" => [ 1, 2],"innocence" => [ 1, 2]], "Expected p2 tokens to be intact.");
    
    test(array_keys($redacted["players"]),$player_ids,"redact should not change the player uids");
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

function test_setup_extrascene()
{
    global $data;

    $data = gawm_new_game();
    $data["notes"] = ["player" => ["aa" => "note - bb", "bb" => "note - aa"]];
    $data["players"] = [
        "aa" => [
            "play" => ["aliases" => [1]],
            "hand" => [],
            "tokens" => []
        ],
        "bb" => [
            "play" => ["aliases" => [2]],
            "hand" => [],
            "tokens" => []
        ]
    ];

    $data["victim"]=array();
    $data["victim"]["player_id"]=array_rand($data["players"]);  
    
    setup_extrascene($data);
    $o = ["aa"=>"bb","bb"=>"aa"];
    $nv = $o[$data["victim"]["player_id"]];
    test( $data["notes"]["player"][$nv], ("note - ".gawm_player_id_victim), "expected non-victim note to refer to victim id, not old alias owner" );
}

function test_token_bias()
{
    global $data;
    global $random_accusation;
    
    test_playthrough(4, gawm_default_rules, $random_accusation, 100);
    test(token_count($data,"innocence"),0,"Expected 0 Innocence Tokens with 100% guilt bias.");

    test_playthrough(5, gawm_default_rules, $random_accusation, 0);
    test(token_count($data,"guilt"),0,"Expected 0 Guilt Tokens with 0% guilt bias.");
}

echo "Testing... ";

try{
    test_setup_extrascene();
    test_setup_epilogue_order();
    test_innocence_and_guilt_ranking();
    test_tally_votes();
    test_redact();
    test_move_detail();
    test_token_bias();
    
    test_playthrough(4, gawm_default_rules, $random_accusation);
    test_playthrough(5, gawm_default_rules, $random_accusation);
    test_playthrough(6, gawm_default_rules, $random_accusation);
    
    echo "Passed ".$test_count." tests.\n";
}
catch (Exception $e) {
    echo("Caught ".$e."\nWith: ".json_encode($data) ."\n");
    exit(1);
}
?>
