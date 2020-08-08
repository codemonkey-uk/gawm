<?php
require_once '../api/gawm.php';

$random_accusation = function ($data)
{
    $other_players = array_filter( array_keys($data["players"]), 
        function($id)use($data){return $id!=$data["most_innocent"];}
    );
    $vk = array_rand($other_players);
    return $other_players[$vk];
};

function vote_scene( &$data )
{
    $inactive_players = array_filter(
        array_keys($data["players"]),
        function($id){global $data; return !gawm_is_player_active($data,$id);}
    );
    test( count($inactive_players), count($data["players"])-1, "All bar 1 players should be active in the scene." );
    $v = [gawm_vote_guilty, gawm_vote_innocent];
    $vk = array_rand($v);
    gawm_vote($data, current($inactive_players), $v[$vk]);
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
            
            // include victim in token gifting (discards)
            $other_players[] = gawm_player_id_victim;
            $vk = array_rand($other_players);
            gawm_give_token($data, $player_id, $other_players[$vk]);
            
            test(isset($data["players"][$player_id]["unassigned_token"]),false,"after giving a token, the player should have one");
        }
    }
}

function test_playthrough($c, $rules, $accusation_fn)
{
    global $data;

    $data = gawm_new_game($rules);
    test(gawm_is_setup($data), true, "New game should start in Setup");

    $player_ids = [];
    for ($i=0;$i!=$c;$i=$i+1)
        array_push( $player_ids, gawm_add_player($data,"player", $rules) );
        
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
    gawm_give_token($data,$active_player,0);

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

    $accused = $accusation_fn($data);
    gawm_record_accused($data, $data["most_innocent"],$accused);

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
?>